#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * 移行の合否判定。
 *
 *   php -S 127.0.0.1:8000 -t public &
 *   php bin/verify-urls.php --base=http://127.0.0.1:8000
 *
 * Hugo がビルドした website/public の中身がそのまま「20 年ぶんの URL 集」
 * になっている。そこにある index.html を全部拾って新サイトに投げる。
 *
 * ただし手元の public は `--buildDrafts` 付きでビルドされていて、
 * **本番には無いページも含む**。`make_draft.sh` が 2004〜2007 年の記事を
 * まとめて `draft: true` にしており、polidog.jp 上でそれらは 404 を返す。
 * そこで DB を正解表として使い、
 *   - 公開されている記事の URL → 200 でなければ失敗（既存 URL が壊れた）
 *   - 下書きの URL           → 404 が正しい（公開されてはいけない）
 * と判定する。
 *
 * ページャの中間ページ（/page/N/）は記事の増減でずれるので対象外。
 */

$options = \getopt('', ['base:', 'public:', 'limit:', 'show:', 'include-drafts', 'help']);
if (isset($options['help'])) {
    echo <<<'TXT'
        Usage: php bin/verify-urls.php [options]

          --base=URL      検証先 (default: http://127.0.0.1:8000)
          --public=DIR    Hugo の public (default: ../website/public)
          --limit=N       先頭 N 件だけ試す
          --show=N        失敗の表示件数 (default: 40)

        TXT;

    exit(0);
}

$base = \rtrim((string) ($options['base'] ?? 'http://127.0.0.1:8000'), '/');
$publicDir = \rtrim((string) ($options['public'] ?? \dirname(__DIR__) . '/../website/public'), '/');
$limit = isset($options['limit']) ? (int) $options['limit'] : 0;
$show = isset($options['show']) ? (int) $options['show'] : 40;

if (!\is_dir($publicDir)) {
    \fwrite(\STDERR, "public ディレクトリが見つかりません: {$publicDir}\n");

    exit(1);
}

// DB を正解表として読む。CLI から直接開くのは、HTTP 越しでは
// 「404 が正しい 404」なのか「壊れた 404」なのか区別できないため。
$databasePath = \getenv('DATABASE_PATH');
if (!\is_string($databasePath) || '' === $databasePath) {
    $databasePath = \dirname(__DIR__) . '/var/cms.db';
}

/** @var array<string, string> $statusByPath */
$statusByPath = [];
/** @var array<string, true> $knownTerms */
$knownTerms = [];

if (\is_file($databasePath)) {
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach ($pdo->query('SELECT path, status FROM "Post"') as $row) {
        $statusByPath[(string) $row['path']] = (string) $row['status'];
    }
    foreach ($pdo->query('SELECT slug FROM "Tag"') as $row) {
        $knownTerms['/tags/' . $row['slug']] = true;
    }
    foreach ($pdo->query('SELECT slug FROM "Category"') as $row) {
        $knownTerms['/categories/' . $row['slug']] = true;
    }
}

echo "base   : {$base}\n";
echo "public : {$publicDir}\n";
echo \sprintf("db     : %s（%d 件）\n\n", $databasePath, \count($statusByPath));

// --- URL の収集 -------------------------------------------------------------

$paths = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || 'index.html' !== $file->getFilename()) {
        continue;
    }

    $relative = \substr($file->getPath(), \strlen($publicDir));
    $path = '' === $relative ? '/' : $relative . '/';

    // ページャの中間ページ（/page/2/ など）は Hugo の出力都合で数が変わる。
    // 記事が増減すれば当然ずれるので、URL の恒久性を測る対象にしない。
    if (\preg_match('#/page/\d+/$#', $path)) {
        continue;
    }

    $paths[] = $path;
}

\sort($paths);
if ($limit > 0) {
    $paths = \array_slice($paths, 0, $limit);
}

$total = \count($paths);
echo "検証対象: {$total} URL\n\n";

// --- 検証 -------------------------------------------------------------------
//
// curl の multi ハンドルで並列に投げる。1 本ずつだと 1300 URL で分単位に
// なってしまう。

$concurrency = 20;
$failures = [];
$counts = [];
$expectedDrafts = 0;
$done = 0;

/**
 * その URL に期待するステータス。
 *
 * 手元の public は「draft 込み」かつ「削除済み記事の残骸込み」なので、
 * ファイルがあること自体は公開の根拠にならない。DB を正解表として、
 *   - 記事 URL（/YYYY/MM/DD/slug/）… DB に無い＝記事が消された → 404
 *                                     下書き → 404、公開 → 200
 *   - タグ / カテゴリ URL         … DB に無い＝使われなくなった → 404
 *   - それ以外（/, /blog/, …）    … 200
 * と判定する。
 */
$expectedFor = static function (string $path) use ($statusByPath, $knownTerms): int {
    $key = '/' === $path ? '/' : \rtrim($path, '/');

    if (isset($statusByPath[$key])) {
        return 'draft' === $statusByPath[$key] ? 404 : 200;
    }

    if (1 === \preg_match('#^/\d{4}/\d{2}/\d{2}/#', $key)) {
        return 404;
    }

    if (1 === \preg_match('#^/(tags|categories)/.+#', $key)) {
        return isset($knownTerms[$key]) ? 200 : 404;
    }

    return 200;
};

$chunks = \array_chunk($paths, $concurrency);

foreach ($chunks as $chunk) {
    $multi = \curl_multi_init();
    $handles = [];

    foreach ($chunk as $path) {
        $handle = \curl_init();
        \curl_setopt_array($handle, [
            // パスに日本語がそのまま入っているので、セグメント単位で encode する。
            \CURLOPT_URL => $base . \implode('/', \array_map(\rawurlencode(...), \explode('/', $path))),
            \CURLOPT_NOBODY => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_TIMEOUT => 20,
        ]);
        \curl_multi_add_handle($multi, $handle);
        $handles[] = [$handle, $path];
    }

    do {
        $status = \curl_multi_exec($multi, $running);
        if ($running > 0) {
            \curl_multi_select($multi, 0.5);
        }
    } while ($running > 0 && \CURLM_OK === $status);

    foreach ($handles as [$handle, $path]) {
        $code = (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
        $expected = $expectedFor($path);

        $counts[$code] = ($counts[$code] ?? 0) + 1;
        if (404 === $expected) {
            ++$expectedDrafts;
        }
        if ($code !== $expected) {
            $failures[] = ['path' => $path, 'code' => $code, 'expected' => $expected];
        }
        \curl_multi_remove_handle($multi, $handle);
    }

    \curl_multi_close($multi);

    $done += \count($chunk);
    if (0 === $done % 200 || $done === $total) {
        echo "  {$done}/{$total}\n";
    }
}

// --- 結果 -------------------------------------------------------------------

echo "\n--- ステータス別 ---\n";
\ksort($counts);
foreach ($counts as $code => $count) {
    echo \sprintf("  %d : %d\n", $code, $count);
}
echo \sprintf("\n下書き（404 が正解）: %d 件\n", $expectedDrafts);

if ([] === $failures) {
    echo "\n期待どおりでした。公開 URL は 1 本も壊れていません。\n";

    exit(0);
}

echo \sprintf("\n[期待と違うもの %d 件]\n", \count($failures));
foreach (\array_slice($failures, 0, $show) as $failure) {
    echo \sprintf("  expected %d, got %d  %s\n", $failure['expected'], $failure['code'], $failure['path']);
}
if (\count($failures) > $show) {
    echo \sprintf("  ...ほか %d 件\n", \count($failures) - $show);
}

exit(1);

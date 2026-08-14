#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Hugo（polidog/website）から記事を取り込む。
 *
 *   php bin/import-hugo.php --content=../website/content [--dry-run]
 *
 * 設計上いちばん大事なのは **URL を 1 本も変えないこと**。だから
 *   - 記事の path は content からの相対パスをそのまま写す
 *   - タグ・カテゴリの slug は Hugo の urlize を再現し、生成結果を
 *     website/public/tags（Hugo が実際に吐いたディレクトリ名）と
 *     突き合わせて検証する
 *   - Disqus の identifier（`.File.UniqueID` = 相対パスの md5）も保存する
 * という 3 点を守る。1 つでもずれると外部から見た URL か、
 * 記事に紐づくコメントが壊れる。
 */

use App\AppConfigurator;
use App\Service\MediaStorage;
use App\Service\PostWriter;
use App\Support\HugoSlug;
use App\Support\PostInput;
use App\Support\SiteConfig;
use Polidog\Relayer\Relayer;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';

// Hugo の front matter は +09:00 で書かれており、URL の年月日もその
// タイムゾーンで決まっていた。UTC のまま組み立てると 1 日ずれる。
\date_default_timezone_set(SiteConfig::TIMEZONE);

$projectRoot = \dirname(__DIR__);

$options = \getopt('', ['content:', 'public:', 'images:', 'uploads:', 'limit:', 'dry-run', 'help']);
if (isset($options['help'])) {
    echo <<<'TXT'
        Usage: php bin/import-hugo.php [options]

          --content=DIR   Hugo の content ディレクトリ (default: ../website/content)
          --public=DIR    Hugo のビルド済み public ディレクトリ。タグ URL の照合に使う
                          (default: <content>/../public)
          --images=DIR    Hugo の static/images (default: <content>/../static/images)
          --uploads=DIR   画像のコピー先 (default: UPLOADS_DIR か var/uploads)
          --limit=N       最初の N 件だけ取り込む（動作確認用）
          --dry-run       DB に書かず、件数と検出した問題だけ出す

        TXT;

    exit(0);
}

/**
 * getopt() の値は「文字列」とは限らない —— 同じオプションが 2 回渡されると
 * 配列に、値なしオプションでは false になる。ここではどれも 1 個の値しか
 * 意味を持たないので、文字列に落として扱う。
 *
 * @param array<string, false|list<string>|string> $options
 */
$option = static function (array $options, string $name, string $default): string {
    $value = $options[$name] ?? null;
    if (\is_array($value)) {
        $value = $value[0] ?? null;
    }

    return \is_string($value) ? $value : $default;
};

$contentDir = \rtrim($option($options, 'content', $projectRoot . '/../website/content'), '/');
if (!\is_dir($contentDir)) {
    \fwrite(\STDERR, "content ディレクトリが見つかりません: {$contentDir}\n");

    exit(1);
}

$publicDir = \rtrim($option($options, 'public', \dirname($contentDir) . '/public'), '/');
$imagesDir = \rtrim($option($options, 'images', \dirname($contentDir) . '/static/images'), '/');
$dryRun = isset($options['dry-run']);
$limit = (int) $option($options, 'limit', '0');

// AppRouter は使わないが、boot() がコンテナを組み立ててくれるのでそれを借りる。
Relayer::boot($projectRoot, new AppConfigurator($projectRoot));
$container = Relayer::container();

/** @var PostWriter $writer */
$writer = $container->get(PostWriter::class);
$writer->deferInvalidation();

// PSR-11 の InjectorContainer にはパラメータを引く口が無いので、
// 置き場を知っている MediaStorage 本人に聞く。
/** @var MediaStorage $media */
$media = $container->get(MediaStorage::class);
$uploadsDir = $option($options, 'uploads', '');
$imagesDestination = '' !== $uploadsDir
    ? \rtrim($uploadsDir, '/') . '/images'
    : $media->imagesRoot();

echo "content : {$contentDir}\n";
echo "public  : {$publicDir}\n";
echo "images  : {$imagesDir}\n";
echo "uploads : {$imagesDestination}\n";
echo $dryRun ? "mode    : dry-run（DB には書きません）\n\n" : "mode    : import\n\n";

/**
 * front matter を切り出す。Hugo は YAML(`---`) / TOML(`+++`) / JSON を
 * 許すが、このリポジトリは全件 YAML なので YAML だけ見る。
 *
 * @return array{array<string, mixed>, string}
 */
$splitFrontMatter = static function (string $raw): array {
    if (!\str_starts_with($raw, "---\n") && !\str_starts_with($raw, "---\r\n")) {
        return [[], $raw];
    }

    $end = \preg_match('/^---\r?$/m', $raw, $m, \PREG_OFFSET_CAPTURE, 4);
    if (1 !== $end) {
        return [[], $raw];
    }

    $offset = $m[0][1];
    $frontMatter = \substr($raw, 4, $offset - 4);
    $body = \substr($raw, $offset + \strlen($m[0][0]));

    try {
        $parsed = Yaml::parse($frontMatter);
    } catch (\Throwable) {
        return [[], $raw];
    }

    return [\is_array($parsed) ? $parsed : [], \ltrim($body, "\r\n")];
};

/**
 * front matter の値をリストに均す。`tags: php` と `tags: [php, symfony]`
 * の両方が実在する。`categoreis` のような綴り違いも吸収する。
 *
 * @param array<string, mixed> $frontMatter
 * @param list<string>         $keys
 *
 * @return list<string>
 */
$terms = static function (array $frontMatter, array $keys): array {
    $values = [];

    foreach ($keys as $key) {
        $raw = $frontMatter[$key] ?? null;
        if (null === $raw) {
            continue;
        }
        foreach ((array) $raw as $value) {
            if (\is_scalar($value)) {
                $trimmed = \trim((string) $value);
                if ('' !== $trimmed) {
                    $values[] = $trimmed;
                }
            }
        }
    }

    return \array_values(\array_unique($values));
};

// --- 走査 -----------------------------------------------------------------

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($contentDir, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && 'md' === $file->getExtension()) {
        $files[] = $file->getPathname();
    }
}

\sort($files);

$stats = ['posts' => 0, 'pages' => 0, 'drafts' => 0, 'skipped' => 0];
$slugs = ['Tag' => [], 'Category' => []];
$problems = [];
$processed = 0;

foreach ($files as $absolutePath) {
    $relativePath = \substr($absolutePath, \strlen($contentDir) + 1);

    // `_index.md` はセクションの見出し。ページとしては取り込まない
    // （/archives と /tech-tags は Relayer 側のページが持っている）。
    if (\str_starts_with(\basename($relativePath), '_index.')) {
        ++$stats['skipped'];

        continue;
    }

    $raw = \file_get_contents($absolutePath);
    if (false === $raw) {
        $problems[] = "読み込めません: {$relativePath}";

        continue;
    }

    [$frontMatter, $body] = $splitFrontMatter($raw);
    if ([] === $frontMatter) {
        $problems[] = "front matter を読めません: {$relativePath}";

        continue;
    }

    $withoutExtension = \preg_replace('/\.md$/', '', $relativePath) ?? $relativePath;
    $isPost = \str_starts_with($relativePath, 'blog/');
    $kind = $isPost ? 'post' : 'page';

    $draft = (bool) ($frontMatter['draft'] ?? false);
    $status = $draft ? 'draft' : 'published';

    // symfony/yaml は ISO-8601 の日付を（PARSE_DATETIME を付けない限り）
    // **Unix タイムスタンプの int** に変換する。文字列前提で strtotime に
    // 渡すと全件失敗するので、型で分岐する。
    $publishedAt = null;
    $date = $frontMatter['date'] ?? null;
    $timestamp = match (true) {
        \is_int($date) => $date,
        $date instanceof DateTimeInterface => $date->getTimestamp(),
        \is_string($date) && '' !== $date => \strtotime($date),
        default => false,
    };
    if (false !== $timestamp) {
        $publishedAt = (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(\date_default_timezone_get()))
        ;
    }
    if (null === $publishedAt) {
        $problems[] = "date が読めません: {$relativePath}";
    }

    // URL は `config/_default/permalinks.toml` が決める:
    //   blog = "/:year/:month/:day/:filename/"
    //   page = "/:slug/"
    // 記事はディレクトリ構造ではなく **front matter の date** から
    // 組み立てられる（`content/blog/2024/12/look-back-on-year.md` は
    // `/2024/12/28/look-back-on-year/`）。`:filename` にも urlize が
    // かかるので、`合格！.md` は `/合格/` になる。
    if ($isPost) {
        if (null === $publishedAt) {
            $problems[] = "date が無いので URL を決められません: {$relativePath}";

            continue;
        }
        $path = \sprintf(
            '/%s/%s/%s/%s',
            $publishedAt->format('Y'),
            $publishedAt->format('m'),
            $publishedAt->format('d'),
            HugoSlug::urlize(\basename($withoutExtension)),
        );
    } else {
        // 固定ページは leaf bundle（`about/index.md`）で、`:slug` は
        // ディレクトリ名になる。
        $path = '/' . \basename(\str_ends_with($withoutExtension, '/index')
            ? \dirname($withoutExtension)
            : $withoutExtension);
    }

    $tags = $terms($frontMatter, ['tags']);
    $categories = $terms($frontMatter, ['categories', 'category', 'categoreis']);

    foreach ($tags as $name) {
        $slugs['Tag'][HugoSlug::urlize($name)] = true;
    }
    foreach ($categories as $name) {
        $slugs['Category'][HugoSlug::urlize($name)] = true;
    }

    $eyecatch = null;
    foreach (['image', 'eyecatch'] as $key) {
        $value = $frontMatter[$key] ?? null;
        if (\is_string($value) && '' !== \trim($value)) {
            $eyecatch = \trim($value);

            break;
        }
    }

    $input = new PostInput(
        kind: $kind,
        path: PostInput::normalizePath($path),
        title: (string) ($frontMatter['title'] ?? \basename($withoutExtension)),
        body: $body,
        status: $status,
        publishedAt: $publishedAt,
        eyecatch: $eyecatch,
        tags: $tags,
        categories: $categories,
        // Hugo の `.File.UniqueID`。content からの相対パス（スラッシュ区切り）の md5。
        disqusId: \md5(\str_replace(\DIRECTORY_SEPARATOR, '/', $relativePath)),
    );

    if (!$dryRun) {
        try {
            $writer->save($input);
        } catch (\Throwable $e) {
            $problems[] = \sprintf('保存に失敗: %s (%s)', $relativePath, $e->getMessage());

            continue;
        }
    }

    ++$stats[$isPost ? 'posts' : 'pages'];
    if ($draft) {
        ++$stats['drafts'];
    }

    ++$processed;
    if (0 === $processed % 100) {
        echo "  {$processed} 件...\n";
    }
    if ($limit > 0 && $processed >= $limit) {
        break;
    }
}

// --- slug の照合 -----------------------------------------------------------
//
// Hugo が実際に吐いたディレクトリ名が正解データ。生成した slug がそこに
// 無ければ、その URL は移行後に 404 になる。

$verifySlugs = static function (string $kind, array $generated, string $publicSubdir) use ($publicDir): array {
    $directory = $publicDir . '/' . $publicSubdir;
    if (!\is_dir($directory)) {
        return ["  {$publicSubdir}/ が見つからないので照合をスキップしました"];
    }

    // slug にスラッシュを含むもの（`php/input`）があるので、1 階層だけ
    // 見るのでは足りない。index.html のある階層をすべて term として拾う。
    $actual = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || 'index.html' !== $file->getFilename()) {
            continue;
        }
        $slug = \trim(\substr($file->getPath(), \strlen($directory)), '/');
        // ページャ（`<slug>/page/2`）は term ではない。
        if ('' !== $slug && !\preg_match('#(^|/)page/\d+$#', $slug)) {
            $actual[$slug] = true;
        }
    }

    $missing = \array_diff_key($generated, $actual);
    if ([] === $missing) {
        return [];
    }

    $messages = [\sprintf('  %s: Hugo 側に無い slug が %d 件', $kind, \count($missing))];
    foreach (\array_slice(\array_keys($missing), 0, 10) as $slug) {
        $messages[] = '    - ' . $slug;
    }

    return $messages;
};

$slugProblems = [
    ...$verifySlugs('Tag', $slugs['Tag'], 'tags'),
    ...$verifySlugs('Category', $slugs['Category'], 'categories'),
];

// --- 画像のコピー -----------------------------------------------------------

$copiedImages = 0;
if (!$dryRun && \is_dir($imagesDir)) {
    $destinationRoot = $imagesDestination;
    $imageIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($imagesDir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($imageIterator as $image) {
        if (!$image instanceof SplFileInfo || !$image->isFile()) {
            continue;
        }

        $relative = \substr($image->getPathname(), \strlen($imagesDir) + 1);
        $destination = $destinationRoot . '/' . $relative;
        $directory = \dirname($destination);

        if (!\is_dir($directory) && !@\mkdir($directory, 0o775, true) && !\is_dir($directory)) {
            $problems[] = "画像のコピー先を作れません: {$directory}";

            continue;
        }

        // 既にあるものは触らない。移行を何度回しても同じ結果になるように。
        if (!\file_exists($destination) && \copy($image->getPathname(), $destination)) {
            ++$copiedImages;
        }
    }
}

// --- 後処理 ---------------------------------------------------------------

if (!$dryRun) {
    echo "\nETag を作り直しています...\n";
    $writer->refreshAllCaches();
}

echo "\n--- 結果 ---\n";
echo \sprintf("記事      : %d 件（うち下書き %d 件）\n", $stats['posts'], $stats['drafts']);
echo \sprintf("固定ページ: %d 件\n", $stats['pages']);
echo \sprintf("スキップ  : %d 件（_index.md）\n", $stats['skipped']);
echo \sprintf("タグ      : %d 種\n", \count($slugs['Tag']));
echo \sprintf("カテゴリ  : %d 種\n", \count($slugs['Category']));
if (!$dryRun) {
    echo \sprintf("画像      : %d 件コピー\n", $copiedImages);
}

if ([] !== $slugProblems) {
    echo "\n[slug の不一致]\n" . \implode("\n", $slugProblems) . "\n";
}

if ([] !== $problems) {
    echo \sprintf("\n[問題 %d 件]\n", \count($problems));
    foreach (\array_slice($problems, 0, 20) as $problem) {
        echo '  - ' . $problem . "\n";
    }
    if (\count($problems) > 20) {
        echo \sprintf("  ...ほか %d 件\n", \count($problems) - 20);
    }

    exit(1);
}

echo "\n完了しました。\n";

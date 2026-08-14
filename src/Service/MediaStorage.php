<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use RuntimeException;

/**
 * 画像の保存と一覧。
 *
 * Hugo 時代の画像は `/images/2012/07/11/foo.jpg` という URL で公開されて
 * いたので、移行後もその URL のまま配信できるよう、置き場のディレクトリ
 * 構造をそのまま引き継ぐ（`<uploadsDir>/images/2012/07/11/foo.jpg`）。
 * 配信は PHP を通さず Caddy の file_server が直接行う。
 */
final class MediaStorage
{
    private const string PUBLIC_PREFIX = '/images';

    /**
     * ブラウザから直接表示できて、かつスクリプトを実行しない形式だけ。
     *
     * **SVG は入れない。** SVG は XML なので `<script>` や `on*` 属性を
     * 持てて、`/images/...` を直接開かれると polidog.jp のオリジンで
     * それが動く（= 管理画面のセッションと同じオリジン）。
     * アップロードできるのは自分だけなので踏まれる筋書きは薄いが、
     * 20 年ぶんの記事で SVG は 1 枚も使われておらず、許可する理由がない。
     * 必要になったら、サニタイザを通すか別ドメインから配信すること。
     */
    private const array ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public function __construct(private readonly string $uploadsDir) {}

    /**
     * アップロードされた一時ファイルを取り込み、公開 URL を返す。
     *
     * @throws RuntimeException 対応していない形式・書き込み失敗
     */
    public function store(string $temporaryPath, string $originalName): string
    {
        $mime = $this->detectMime($temporaryPath);
        $extension = self::ALLOWED[$mime] ?? throw new RuntimeException(
            \sprintf('対応していない画像形式です: %s', $mime),
        );

        $now = new DateTimeImmutable();
        $relative = \sprintf(
            '%s/%s/%s.%s',
            $now->format('Y'),
            $now->format('m'),
            $this->safeBasename($originalName),
            $extension,
        );

        $destination = $this->absolutePath($relative);
        $directory = \dirname($destination);
        if (!\is_dir($directory) && !@\mkdir($directory, 0o775, true) && !\is_dir($directory)) {
            throw new RuntimeException("アップロード先を作成できません: {$directory}");
        }

        // 同名があれば連番を足す。上書きすると過去記事の画像が差し替わる。
        $destination = $this->uniquePath($destination);

        $moved = \is_uploaded_file($temporaryPath)
            ? \move_uploaded_file($temporaryPath, $destination)
            : \rename($temporaryPath, $destination);

        if (!$moved) {
            throw new RuntimeException('画像の保存に失敗しました。');
        }
        \chmod($destination, 0o644);

        return self::PUBLIC_PREFIX . '/' . \ltrim(
            \substr($destination, \strlen($this->imagesRoot()) + 1),
            '/',
        );
    }

    /**
     * 新しい順の一覧。管理画面で URL をコピーするためだけのものなので、
     * DB には持たずファイルシステムを直接読む。
     *
     * @return list<array{url: string, size: int, modifiedAt: int}>
     */
    public function recent(int $limit = 200): array
    {
        $root = $this->imagesRoot();
        if (!\is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $files[] = [
                'url' => self::PUBLIC_PREFIX . '/' . \ltrim(
                    \substr($file->getPathname(), \strlen($root) + 1),
                    '/',
                ),
                'size' => (int) $file->getSize(),
                'modifiedAt' => (int) $file->getMTime(),
            ];
        }

        \usort($files, static fn (array $a, array $b): int => $b['modifiedAt'] <=> $a['modifiedAt']);

        return \array_slice($files, 0, $limit);
    }

    public function imagesRoot(): string
    {
        return \rtrim($this->uploadsDir, '/') . '/images';
    }

    private function absolutePath(string $relative): string
    {
        return $this->imagesRoot() . '/' . $relative;
    }

    private function uniquePath(string $path): string
    {
        if (!\file_exists($path)) {
            return $path;
        }

        $extension = \pathinfo($path, \PATHINFO_EXTENSION);
        $base = \substr($path, 0, -(\strlen($extension) + 1));

        for ($i = 2; $i < 1000; ++$i) {
            $candidate = \sprintf('%s-%d.%s', $base, $i, $extension);
            if (!\file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('同名ファイルが多すぎます。');
    }

    /**
     * 日本語ファイル名も URL に載るので、パス区切りと制御文字だけ落として
     * あとは残す（Hugo 時代の画像にも日本語名がある）。
     */
    private function safeBasename(string $name): string
    {
        $name = \pathinfo($name, \PATHINFO_FILENAME);
        $name = (string) \preg_replace('#[/\\\\\x00-\x1F\x7F]#u', '', $name);
        $name = \trim((string) \preg_replace('/\s+/u', '-', $name), '-.');

        return '' === $name ? 'image' : \mb_substr($name, 0, 80, 'UTF-8');
    }

    /**
     * 中身を見て判定する。拡張子や送られてきた Content-Type は信じない
     * （どちらもクライアントの自己申告なので、`.png` という名前の HTML を
     * 置かれうる）。ALLOWED に無い形式はここで弾かれる。
     */
    private function detectMime(string $path): string
    {
        $info = @\getimagesize($path);
        if (false !== $info && isset($info['mime'])) {
            return (string) $info['mime'];
        }

        // getimagesize が読めない形式（新しいコンテナなど）は finfo に
        // 落とす。それでも ALLOWED に無ければ store() が拒否する。
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return false === $mime ? 'application/octet-stream' : $mime;
    }
}

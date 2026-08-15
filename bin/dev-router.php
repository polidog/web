<?php

declare(strict_types=1);

/*
 * `php -S` 用のルータ。bin/dev.sh から渡される。
 *
 * 本番では Caddy が /images/* を volume 上の実ファイルへ直接マップしている
 * （Caddyfile の handle_path）。ビルトインサーバは public/ の外を配信でき
 * ないので、同じ対応をここで肩代わりする。これが無いと **ローカルだけ
 * 画像が全部 404 になり**、本番で直っているのか壊れているのか分からなくなる。
 *
 * それ以外のリクエストは public/index.php にそのまま流す。
 */

$projectRoot = \dirname(__DIR__);
$publicRoot = $projectRoot . '/public';

$path = \rawurldecode(\parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), \PHP_URL_PATH) ?: '/');

if (\str_starts_with($path, '/images/')) {
    // UPLOADS_DIR は .env にしか無いことがある（config/services.yaml と
    // 同じ既定値に揃える）。画像リクエストのときだけ読めば十分。
    require_once $projectRoot . '/vendor/autoload.php';
    if (\file_exists($projectRoot . '/.env')) {
        (new Symfony\Component\Dotenv\Dotenv())->loadEnv($projectRoot . '/.env');
    }

    $uploadsDir = $_ENV['UPLOADS_DIR'] ?? \getenv('UPLOADS_DIR') ?: null;
    $imagesRoot = \realpath(\rtrim(\is_string($uploadsDir) && '' !== $uploadsDir
        ? $uploadsDir
        : $projectRoot . '/var/uploads', '/') . '/images');

    // realpath で解決してから接頭辞を見る。`..` を含むパスで images の
    // 外に出られないようにするため。
    $target = false !== $imagesRoot
        ? \realpath($imagesRoot . '/' . \substr($path, \strlen('/images/')))
        : false;

    if (false === $target || !\str_starts_with($target, $imagesRoot . \DIRECTORY_SEPARATOR) || !\is_file($target)) {
        \http_response_code(404);
        echo "404 Not Found\n";

        return true;
    }

    $types = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];
    $extension = \strtolower(\pathinfo($target, \PATHINFO_EXTENSION));

    \header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
    \header('Content-Length: ' . (string) \filesize($target));
    // Caddy 側と揃える。画像ディレクトリから何かが実行されないための保険。
    \header('X-Content-Type-Options: nosniff');
    \header("Content-Security-Policy: default-src 'none'; sandbox");
    \readfile($target);

    return true;
}

// public/ にある実ファイル（assets/style.css など）はビルトインサーバの
// 静的配信に任せる。
if ('/' !== $path && \is_file($publicRoot . $path)) {
    return false;
}

require $publicRoot . '/index.php';

return true;

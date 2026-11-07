<?php

declare(strict_types=1);

namespace App\Support;

/**
 * `public/` 配下のアセットに、中身のハッシュを `?v=` で付けた URL を返す。
 *
 * Caddy は `/assets/*` に `max-age=86400` を付けるので、URL が同じままだと
 * デプロイ後も読者のブラウザに最長 1 日古いファイルが残る。Cloudflare の
 * purge はブラウザのキャッシュには届かない。JS とサーバの取り決め（フォームの
 * フィールド名など）が変わるときに古い JS が動くと、無言で壊れる
 * （Relayer 1.0 で `_usephp_csrf` が `_rosanna_csrf` になったときがそれ）。
 *
 * ハッシュはプロセスの中でだけ覚える。worker モードでは 1 度しか計算しない。
 * ファイルが読めなければ素の URL を返す（キャッシュ対策が効かないだけで、
 * ページは壊さない）。
 */
final class AssetUrl
{
    private const string PUBLIC_DIR = __DIR__ . '/../../public';

    /** @var array<string, string> */
    private static array $memo = [];

    /**
     * @param string $path `/assets/admin.js` のような、ルートからのパス
     */
    public static function versioned(string $path): string
    {
        if (isset(self::$memo[$path])) {
            return self::$memo[$path];
        }

        $hash = @\hash_file('xxh3', self::PUBLIC_DIR . $path);

        return self::$memo[$path] = false === $hash ? $path : $path . '?v=' . $hash;
    }
}

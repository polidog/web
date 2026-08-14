<?php

declare(strict_types=1);

namespace App\Support;

use Polidog\Relayer\Http\Cache;

/**
 * このサイトのキャッシュ方針を 1 箇所に集めたもの。
 *
 * 前提: Cloudflare を前段に置き、エッジに持たせて purge で落とす。
 * ブラウザには持たせない（`max-age=0`）——手元のブラウザが古い記事を
 * 握っていると purge しても直らないため。エッジは `s-maxage` で持つ。
 *
 * 記事詳細と一覧で寿命を変えているのは purge のコストが理由:
 *
 * - **記事詳細** — 更新の契機が「その記事を保存したとき」だけなので
 *   1 週間持たせて、保存時にその URL を purge すれば常に正しい。
 * - **一覧・タグ・アーカイブ** — 記事を 1 本足すと 53 ページぶんの
 *   ページングが全部ずれる。Cloudflare の files purge は 1 回 30 URL
 *   までで、数え上げて消すのは割に合わない。5 分で自然に切れるほうに賭ける。
 * - **トップと RSS** — 更新頻度が高く、かつ URL が 1 本ずつしかないので
 *   長く持たせて purge する。
 */
final class PageCache
{
    private const int WEEK = 604800;
    private const int FIVE_MINUTES = 300;

    public static function post(string $path): Cache
    {
        return new Cache(
            maxAge: 0,
            sMaxAge: self::WEEK,
            public: true,
            etagKey: self::etagKey($path),
        );
    }

    /**
     * 一覧系。ETag は「公開済みコンテンツ全体の版」なので、どの一覧でも
     * 同じキーを共有する（記事が 1 本増えれば全部の一覧が変わる）。
     */
    public static function list(): Cache
    {
        return new Cache(
            maxAge: 0,
            sMaxAge: self::FIVE_MINUTES,
            public: true,
            etagKey: 'content-version',
        );
    }

    public static function home(): Cache
    {
        return new Cache(
            maxAge: 0,
            sMaxAge: self::WEEK,
            public: true,
            etagKey: 'content-version',
        );
    }

    public static function feed(): Cache
    {
        return new Cache(
            maxAge: 0,
            sMaxAge: self::WEEK,
            public: true,
            etagKey: 'content-version',
        );
    }

    /**
     * 管理画面。共有キャッシュに 1 バイトも載せない。
     */
    public static function admin(): Cache
    {
        return new Cache(private: true, noStore: true);
    }

    public static function etagKey(string $path): string
    {
        return 'page:' . $path;
    }
}

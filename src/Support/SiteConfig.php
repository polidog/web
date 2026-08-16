<?php

declare(strict_types=1);

namespace App\Support;

/**
 * サイト全体の定数。ページやレイアウトが `getenv()` を触らずに
 * 絶対 URL や Disqus の設定を取れるようにするためだけの入れ物。
 */
final readonly class SiteConfig
{
    /**
     * 記事の URL は公開日から作られる（`/2024/12/28/slug/`）ので、
     * タイムゾーンがずれると URL そのものが変わる。Hugo 時代の URL は
     * front matter の `+09:00` で決まっていたため、JST に固定する。
     * 各エントリポイント（public/index.php・bin/*.php）が起動直後に
     * この値で `date_default_timezone_set()` する。
     */
    public const string TIMEZONE = 'Asia/Tokyo';

    public function __construct(
        public string $siteUrl = 'https://polidog.jp',
        public string $disqusShortname = '',
        public string $googleAnalyticsId = '',
        public string $appEnv = 'production',
        public string $title = 'polidog lab',
        public string $description = 'polidog の個人サイト。技術のことと、日々のこと。',
        public string $author = 'polidog',
    ) {}

    public function googleAnalyticsEnabled(): bool
    {
        return 'production' === $this->appEnv && '' !== $this->googleAnalyticsId;
    }

    /**
     * canonical・OGP・RSS で使う絶対 URL。
     *
     * Hugo 時代の URL は末尾スラッシュ付きだったので、canonical も
     * そちらに揃える。Relayer のルーターは両方を同じページとして扱う
     * （末尾スラッシュを落として照合する）ので、揃えないと同じ内容が
     * 2 つの URL で見えてしまう。
     */
    public function absoluteUrl(string $path): string
    {
        $base = \rtrim($this->siteUrl, '/');
        $path = '/' . \ltrim($path, '/');

        if ('/' === $path) {
            return $base . '/';
        }

        // .xml のような拡張子付きは Hugo でもスラッシュを付けない。
        if (\str_contains(\basename($path), '.')) {
            return $base . $path;
        }

        return $base . \rtrim($path, '/') . '/';
    }
}

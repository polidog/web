<?php

declare(strict_types=1);

namespace App\Service;

use App\Http\SiteDocument;
use App\Support\SiteConfig;

/**
 * ページから `<head>` を 1 行で設定するための窓口。
 *
 * `PageContext::metadata()` は使わない。あれはフレームワーク既定の
 * HtmlDocument にしか届かず、このアプリは canonical のために
 * SiteDocument に差し替えているため（AppRouter は
 * `instanceof HtmlDocument` を見てから metadata を渡す）。
 * 経路を 1 本に絞っておかないと「title が反映されない」で必ず躓く。
 */
final class PageMeta
{
    public function __construct(
        private readonly SiteDocument $document,
        private readonly SiteConfig $site,
    ) {}

    public function apply(
        string $path,
        string $title = '',
        string $description = '',
        string $ogType = 'website',
        ?string $image = null,
    ): void {
        $this->document
            ->setTitle($title)
            ->setDescription($description)
            ->setCanonical($this->site->absoluteUrl($path))
            ->setOgType($ogType)
        ;

        if (null !== $image && '' !== $image) {
            $this->document->setOgImage($this->site->absoluteUrl($image));
        }
    }

    /**
     * 一覧の 2 ページ目以降を検索結果に出さない。Hugo 時代は paginator が
     * rel=prev/next を出していたが、今は Google が見ないので noindex で足りる。
     */
    public function noindex(): void
    {
        $this->document->addHeadHtml('<meta name="robots" content="noindex,follow">');
    }
}

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
        private readonly OgpImageGenerator $ogp,
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

        if (null !== $image && '' !== $image && $this->usableAsOgImage($image)) {
            $this->document->setOgImage($this->site->absoluteUrl($image));

            return;
        }

        if ($this->shouldGenerateOgp($path)) {
            try {
                $this->document->setOgImage(
                    $this->site->absoluteUrl($this->ogp->generate($path, $title)),
                    OgpImageGenerator::WIDTH,
                    OgpImageGenerator::HEIGHT,
                );
            } catch (\Throwable) {
                // OGP 生成の失敗でページ本文までは落とさない。
            }
        }
    }

    private function shouldGenerateOgp(string $path): bool
    {
        return !\str_starts_with($path, '/admin') && !\str_starts_with($path, '/oauth');
    }

    /**
     * OGP に出して大丈夫な画像形式か。
     *
     * アップロードは WebP と AVIF も受け付ける（記事本文では使える）が、
     * X(Twitter) のカードクローラはどちらも読めず、指定するとカードが
     * 丸ごと出なくなる。アイキャッチがその形式だったときは og:image に
     * 使わず、自動生成の PNG に落とす。
     */
    private function usableAsOgImage(string $image): bool
    {
        $path = \parse_url($image, \PHP_URL_PATH);
        $extension = \strtolower(\pathinfo(\is_string($path) ? $path : $image, \PATHINFO_EXTENSION));

        return \in_array($extension, ['png', 'jpg', 'jpeg', 'gif'], true);
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

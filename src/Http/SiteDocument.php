<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\SiteConfig;
use Polidog\Relayer\Router\Document\DocumentInterface;

/**
 * `<html>` の外殻。フレームワーク既定の HtmlDocument を差し替えて使う
 * （public/index.php の `$router->setDocument(...)`）。
 *
 * 差し替える理由は 3 つ:
 *   1. canonical と RSS の `<link>` は `<head>` にしか置けない。
 *      既定の Document は metadata を `<meta>` にしか変換しない。
 *   2. Tailwind のダークモードは `<html class="dark">` を見るので、
 *      本文より前に localStorage を読んでクラスを付けないと初回描画で
 *      白く光る。
 *   3. `<html>` `<body>` に Tailwind のクラスを載せたい。
 *
 * DI に登録してあるので、ページからは PageMeta 経由で触る。
 */
final class SiteDocument implements DocumentInterface
{
    private string $title = '';
    private string $description = '';
    private string $canonical = '';
    private string $ogType = 'website';
    private string $ogImage = '';

    /** @var list<string> */
    private array $extraHead = [];

    public function __construct(private readonly SiteConfig $site) {}

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setCanonical(string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function setOgType(string $type): self
    {
        $this->ogType = $type;

        return $this;
    }

    public function setOgImage(string $url): self
    {
        $this->ogImage = $url;

        return $this;
    }

    public function addHeadHtml(string $html): self
    {
        $this->extraHead[] = $html;

        return $this;
    }

    public function render(string $content): string
    {
        return $this->document($content);
    }

    public function renderError(int $statusCode, string $message): string
    {
        // error.psx がある限りここは通らない（AppRouter はエラーページを
        // 通常のページとしてレンダリングして render() に渡す）。
        // 保険として、素の形だけ返す。
        $this->title = \sprintf('%d %s', $statusCode, $message);

        return $this->document(\sprintf(
            '<main class="mx-auto max-w-measure px-5 py-32"><p class="font-mono text-5xl font-light text-faint">%d</p>'
            . '<p class="mt-6 text-lg text-ink">%s</p>'
            . '<p class="mt-10"><a class="text-accent underline underline-offset-4 hover:text-accent-strong" href="/">トップへ戻る</a></p></main>',
            $statusCode,
            $this->esc($message),
        ));
    }

    private function document(string $content): string
    {
        $title = '' !== $this->title
            ? $this->title . ' | ' . $this->site->title
            : $this->site->title;
        $description = '' !== $this->description ? $this->description : $this->site->description;
        $canonical = '' !== $this->canonical ? $this->canonical : $this->site->siteUrl;
        $image = '' !== $this->ogImage ? $this->ogImage : '';

        $head = [
            '<meta charset="UTF-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
            \sprintf('<title>%s</title>', $this->esc($title)),
            \sprintf('<meta name="description" content="%s">', $this->esc($description)),
            \sprintf('<link rel="canonical" href="%s">', $this->esc($canonical)),
            \sprintf('<meta property="og:title" content="%s">', $this->esc($title)),
            \sprintf('<meta property="og:description" content="%s">', $this->esc($description)),
            \sprintf('<meta property="og:url" content="%s">', $this->esc($canonical)),
            \sprintf('<meta property="og:type" content="%s">', $this->esc($this->ogType)),
            \sprintf('<meta property="og:site_name" content="%s">', $this->esc($this->site->title)),
            '<meta name="twitter:card" content="summary_large_image">',
            \sprintf('<meta name="twitter:creator" content="@%s">', $this->esc($this->site->author)),
            \sprintf(
                '<link rel="alternate" type="application/rss+xml" title="%s" href="%s">',
                $this->esc($this->site->title),
                $this->esc($this->site->absoluteUrl('/index.xml')),
            ),
            '<link rel="stylesheet" href="/assets/style.css">',
        ];

        if ('' !== $image) {
            $head[] = \sprintf('<meta property="og:image" content="%s">', $this->esc($image));
        }

        foreach ($this->extraHead as $html) {
            $head[] = $html;
        }

        // 本文より先に走らせないと初回描画が白く光るので、ここだけインライン。
        $head[] = '<script>'
            . '(function(){var s=null;try{s=localStorage.getItem("theme")}catch(e){}'
            . 'if(s==="dark"||(s===null&&window.matchMedia("(prefers-color-scheme: dark)").matches))'
            . '{document.documentElement.classList.add("dark")}})();'
            . '</script>';

        $headHtml = \implode("\n    ", $head);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="ja" class="h-full antialiased">
            <head>
                {$headHtml}
            </head>
            <body class="flex min-h-full flex-col bg-surface font-sans text-ink">
            {$content}
            <script src="/assets/site.js" defer></script>
            </body>
            </html>
            HTML;
    }

    private function esc(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}

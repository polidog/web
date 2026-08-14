<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Shortcodes;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown → HTML。**保存時にしか呼ばれない** のが肝で、レンダリング
 * 結果は Post.html に持つ。表示側は 1 行 SELECT して echo するだけなので、
 * CDN のキャッシュを外れたリクエストでもオリジンはほぼ無仕事で済む。
 */
final class MarkdownRenderer
{
    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        // Hugo 側は goldmark を unsafe: true で使っていたので、既存記事に
        // 直書きされた HTML（iframe・table・script 抜きの装飾）を落とさない
        // よう html_input を allow にする。移行元も投稿者も自分ひとりなので、
        // ここでサニタイズしても守れる相手がいない。
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "\n"],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        // goldmark の GFM 相当。既存記事に表と打ち消し線と裸 URL がある。
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new AutolinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        return $this->converter->convert(Shortcodes::expand($markdown))->getContent();
    }

    /**
     * 一覧・OGP・RSS の説明文に使う抜粋。HTML を落として詰めるだけ。
     */
    public function excerpt(string $html, int $length = 160): string
    {
        $text = \html_entity_decode(\strip_tags($html), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $text = \trim((string) \preg_replace('/\s+/u', ' ', $text));

        if (\mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return \mb_substr($text, 0, $length, 'UTF-8') . '…';
    }
}

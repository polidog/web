<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\MarpDeck;
use App\Support\ScopedCss;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Symfony\Component\Yaml\Exception\ExceptionInterface as YamlException;
use Symfony\Component\Yaml\Yaml;

/**
 * Marp 形式の Markdown → スライドの HTML。
 *
 * 本家 marp-core は Node のライブラリで、本番イメージには Node が
 * 入っていない（Tailwind のビルドステージにしか無い）。スライドは
 * 管理画面から保存するもので、その時点で変換が要るので、Marp の記法を
 * PHP で解釈し直している。記事と同じ league/commonmark の上に載せ、
 * Marp 固有の部分——`---` でのページ分割、ディレクティブ、`![bg]`、
 * 画像のサイズ指定——だけをここで足す。
 *
 * ## 扱う記法
 *
 * - **front matter**（`marp` / `theme` / `size` / `paginate` /
 *   `headingDivider` / `style` / `title` / `description` …）
 * - **ページ分割**: 最上位の水平線（`---` など）と `headingDivider`
 * - **ディレクティブ**（HTML コメント）: `paginate` / `header` / `footer` /
 *   `class` / `backgroundColor` / `backgroundImage` / `color` など。
 *   `_` 付きはそのページだけ、無しはそのページ以降（Marpit と同じ）
 * - **背景画像** `![bg](…)`: `left` / `right`（`right:40%` の比率つき）、
 *   `vertical`、複数枚の並置、`cover` / `contain` / `fit` / `auto` / `50%`、
 *   フィルタ（`blur` / `brightness` / `grayscale` …）
 * - **画像サイズ** `![w:300 h:200](…)` とフィルタ
 * - **テーマ**: `default` / `gaia` / `uncover`（CSS は public/assets/marp.css
 *   にあり、本家の見た目に寄せた近似。`style:` と本文の `<style>` で上書きできる）
 *
 * 扱わないもの: 数式（KaTeX）、`<!-- fit -->` の自動縮小、絵文字の
 * ショートコード、`<style scoped>` のページ単位スコープ（デッキ全体に
 * 効く）。発表者ノート（ディレクティブでないコメント）は出力から落とす。
 *
 * ## 出力の形
 *
 * 1 ページは Marp の `inlineSVG` と同じく `<svg viewBox>` +
 * `<foreignObject>` で包む。section は 1280×720（4:3 なら 960×720）の
 * 固定寸法で組み、SVG の viewBox が親の幅に合わせて丸ごと縮尺する——
 * JS もリサイズ監視も要らない。背景画像があるページは Marpit の
 * advanced background と同様、図版だけの section を content の下に
 * もう 1 枚重ねる（content 側は透明にする）。
 */
final class MarpRenderer
{
    /** @var array<string, array{int, int}> */
    private const array SIZES = [
        '16:9' => [1280, 720],
        '4:3' => [960, 720],
    ];

    private const array THEMES = ['default', 'gaia', 'uncover'];

    private const array GLOBAL_DIRECTIVES = ['theme', 'size', 'headingDivider', 'style', 'title', 'description'];

    private const array LOCAL_DIRECTIVES = [
        'paginate', 'header', 'footer', 'class', 'color',
        'backgroundColor', 'backgroundImage', 'backgroundPosition', 'backgroundRepeat', 'backgroundSize',
    ];

    /** Marp の画像フィルタと、値を省略したときの既定。 */
    private const array FILTERS = [
        'blur' => '10px',
        'brightness' => '1.5',
        'contrast' => '2',
        'drop-shadow' => '0 5px 10px rgba(0, 0, 0, 0.4)',
        'grayscale' => '1',
        'hue-rotate' => '180deg',
        'invert' => '1',
        'opacity' => '0.5',
        'saturate' => '2',
        'sepia' => '1',
    ];

    private readonly MarkdownParser $parser;
    private readonly HtmlRenderer $renderer;
    private readonly FrontMatterParser $frontMatter;

    public function __construct()
    {
        // 記事の MarkdownRenderer と同じ構成。HTML は通す（書くのは自分だけ）。
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "\n"],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new AutolinkExtension());

        $this->parser = new MarkdownParser($environment);
        $this->renderer = new HtmlRenderer($environment);
        $this->frontMatter = new FrontMatterParser(new SymfonyYamlFrontMatterParser());
    }

    public function render(string $markdown): MarpDeck
    {
        [$front, $content] = $this->splitFrontMatter($markdown);
        $document = $this->parser->parse($content);

        // グローバルディレクティブは front matter とコメントのどこにあっても
        // デッキ全体に効く。ページ分割の前に集める（headingDivider が要る）。
        $global = $this->pickGlobal($front);
        foreach ($this->commentNodes($document) as $comment) {
            foreach (self::comments($comment) as $body) {
                $global = $this->pickGlobal(self::directives($body)) + $global;
            }
        }

        $theme = \is_string($global['theme'] ?? null) && \in_array($global['theme'], self::THEMES, true)
            ? $global['theme']
            : 'default';
        $size = \is_string($global['size'] ?? null) && isset(self::SIZES[$global['size']])
            ? $global['size']
            : '16:9';
        [$width, $height] = self::SIZES[$size];

        $css = [];
        if (\is_string($global['style'] ?? null) && '' !== \trim($global['style'])) {
            $css[] = $global['style'];
        }

        $slides = $this->split($document, self::dividerLevels($global['headingDivider'] ?? null));

        // front matter のローカルディレクティブは 1 ページ目のコメントと同じ扱い。
        $carry = [];
        $pageDirectives = [];
        foreach ($slides as $index => $nodes) {
            $collected = 0 === $index ? self::pickLocal($front) : [];
            foreach ($nodes as $node) {
                foreach ($this->commentNodes($node) as $comment) {
                    foreach (self::comments($comment) as $body) {
                        $collected = self::pickLocal(self::directives($body)) + $collected;
                    }
                    self::detachInline($comment);
                }
                foreach ($this->styleNodes($node) as $style) {
                    $css[] = self::styleContents($style->getLiteral());
                    $style->detach();
                }
            }

            $spot = [];
            foreach ($collected as $key => $value) {
                if (\str_starts_with($key, '_')) {
                    $spot[\substr($key, 1)] = $value;
                } else {
                    $carry[$key] = $value;
                }
            }
            $pageDirectives[$index] = $spot + $carry;
        }

        // ページ番号。`skip` は数えず、`hold` は前の番号を据え置く（Marpit 3.x）。
        $numbers = [];
        $counter = 0;
        foreach ($pageDirectives as $index => $directives) {
            $paginate = $directives['paginate'] ?? false;
            if ('skip' === $paginate) {
                $numbers[$index] = null;
            } elseif ('hold' === $paginate) {
                $numbers[$index] = \max(1, $counter);
            } else {
                ++$counter;
                $numbers[$index] = true === $paginate || 'true' === $paginate ? $counter : null;
            }
        }
        $total = \max(1, $counter);

        $pages = [];
        foreach ($slides as $index => $nodes) {
            $pages[] = $this->renderPage(
                $index + 1,
                $nodes,
                $pageDirectives[$index],
                $numbers[$index],
                $total,
                $width,
                $height,
            );
        }

        $html = \sprintf(
            "<div class=\"marp\" data-marp-theme=\"%s\" data-marp-size=\"%s\">\n%s\n</div>",
            self::attr($theme),
            self::attr($size),
            \implode("\n", $pages),
        );

        return new MarpDeck(
            html: $html,
            // 接頭辞を `.marp.marp` と重ねるのは詳細度のため。テーマ側の
            // `.marp[data-marp-theme="gaia"] section h1` は (0,2,2) あり、
            // `.marp section h1` (0,1,2) では `style:` で書いた上書きが負ける。
            // 同じ (0,2,2) に揃えれば、あとから読まれるこちらが勝つ。
            css: ScopedCss::scope(\implode("\n", $css), '.marp.marp'),
            pages: \count($pages),
            title: \is_string($global['title'] ?? null) ? \trim($global['title']) : null,
            description: \is_string($global['description'] ?? null) ? \trim($global['description']) : null,
        );
    }

    /**
     * @param list<Node>           $nodes
     * @param array<string, mixed> $directives
     */
    private function renderPage(
        int $page,
        array $nodes,
        array $directives,
        ?int $number,
        int $total,
        int $width,
        int $height,
    ): string {
        $backgrounds = [];
        foreach ($nodes as $node) {
            foreach ($this->imageNodes($node) as $image) {
                $background = $this->processImage($image);
                if (null !== $background) {
                    $backgrounds[] = $background;
                }
            }
        }

        $classes = self::classList($directives['class'] ?? null);
        $style = [\sprintf('width:%dpx', $width), \sprintf('height:%dpx', $height)];
        foreach ([
            'color' => 'color',
            'backgroundColor' => 'background-color',
            'backgroundImage' => 'background-image',
            'backgroundPosition' => 'background-position',
            'backgroundRepeat' => 'background-repeat',
            'backgroundSize' => 'background-size',
        ] as $directive => $property) {
            $value = $directives[$directive] ?? null;
            if (\is_scalar($value) && '' !== (string) $value) {
                $style[] = $property . ':' . self::cssValue((string) $value);
            }
        }

        $attributes = [
            'id' => (string) $page,
            'data-marp-layer' => 'content',
        ];
        if ([] !== $classes) {
            $attributes['class'] = \implode(' ', $classes);
        }
        if (null !== $number) {
            $attributes['data-marpit-pagination'] = (string) $number;
            $attributes['data-marpit-pagination-total'] = (string) $total;
        }

        $layers = [];
        if ([] !== $backgrounds) {
            [$backgroundLayer, $contentStyle] = $this->backgroundLayer($backgrounds, $classes, $style, $width, $height);
            $layers[] = $backgroundLayer;
            $style = $contentStyle;
        }
        $attributes['style'] = \implode(';', $style);

        $inner = [];
        $header = $directives['header'] ?? null;
        if (\is_scalar($header) && '' !== (string) $header) {
            $inner[] = '<header>' . $this->renderInline((string) $header) . '</header>';
        }
        // コメント・<style>・背景画像を抜いた結果、Document から外れたノードは
        // もう描かない（この配列は最上位ノードの参照を持ったままなので、
        // detach() だけでは消えない）。
        $inner[] = $this->renderer->renderNodes(\array_values(\array_filter(
            $nodes,
            static fn (Node $node): bool => null !== $node->parent(),
        )));
        $footer = $directives['footer'] ?? null;
        if (\is_scalar($footer) && '' !== (string) $footer) {
            $inner[] = '<footer>' . $this->renderInline((string) $footer) . '</footer>';
        }

        $layers[] = \sprintf(
            '<foreignObject width="%d" height="%d"><section%s>%s</section></foreignObject>',
            $width,
            $height,
            self::attributes($attributes),
            "\n" . \implode("\n", $inner) . "\n",
        );

        return \sprintf(
            '<svg class="marp-slide" data-marp-page="%d" viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg">%s</svg>',
            $page,
            $width,
            $height,
            "\n" . \implode("\n", $layers) . "\n",
        );
    }

    /**
     * 背景画像だけの section。content 側の section は透明にし、split の
     * ときは画像の反対側に寄せる（Marpit の advanced background と同じ）。
     *
     * @param list<array{url: string, size: string, filter: string, split: null|string, ratio: null|string, vertical: bool}> $backgrounds
     * @param list<string>                                                                                                    $classes
     * @param list<string>                                                                                                    $style
     *
     * @return array{string, list<string>}
     */
    private function backgroundLayer(array $backgrounds, array $classes, array $style, int $width, int $height): array
    {
        $split = null;
        $ratio = '50%';
        $vertical = false;
        foreach ($backgrounds as $background) {
            if (null !== $background['split']) {
                $split = $background['split'];
                $ratio = $background['ratio'] ?? $ratio;
            }
            $vertical = $vertical || $background['vertical'];
        }

        $figures = \array_map(
            static fn (array $background): string => \sprintf(
                '<figure style="%s"></figure>',
                self::attr(\implode(';', \array_filter([
                    'background-image:url(' . self::cssUrl($background['url']) . ')',
                    'background-size:' . $background['size'],
                    '' !== $background['filter'] ? 'filter:' . $background['filter'] : '',
                ]))),
            ),
            $backgrounds,
        );

        $splitWidth = null !== $split ? self::ratioToPixels($ratio, $width) : $width;

        $wrapperStyle = [
            'display:flex',
            'flex-direction:' . ($vertical ? 'column' : 'row'),
            \sprintf('width:%dpx', $splitWidth),
            'height:100%',
            'margin-left:' . ('right' === $split ? 'auto' : '0'),
        ];

        $layer = \sprintf(
            '<foreignObject width="%d" height="%d"><section%s><div class="marp-bg" style="%s">%s</div></section></foreignObject>',
            $width,
            $height,
            self::attributes(\array_filter([
                'data-marp-layer' => 'background',
                'class' => \implode(' ', $classes),
                'style' => \implode(';', [...$style, 'padding:0', 'display:block']),
            ], static fn (string $value): bool => '' !== $value)),
            self::attr(\implode(';', $wrapperStyle)),
            \implode('', $figures),
        );

        // 寸法は content 側で組み直すので、いったん width を外す。
        $contentStyle = \array_values(\array_filter(
            $style,
            static fn (string $declaration): bool => null === $split || !\str_starts_with($declaration, 'width:'),
        ));
        $contentStyle[] = 'background:transparent';
        if ('left' === $split) {
            $contentStyle[] = \sprintf('margin-left:%dpx', $splitWidth);
            $contentStyle[] = \sprintf('width:%dpx', $width - $splitWidth);
        } elseif ('right' === $split) {
            $contentStyle[] = \sprintf('width:%dpx', $width - $splitWidth);
        }

        return [$layer, $contentStyle];
    }

    /**
     * 画像の alt に書かれた Marp のキーワードを解釈する。
     *
     * `bg` で始まれば背景画像——ノードを本文から外し、背景レイヤー用の
     * 情報を返す。それ以外はサイズとフィルタを style に写し、キーワードを
     * 除いた alt を残す。
     *
     * @return null|array{url: string, size: string, filter: string, split: null|string, ratio: null|string, vertical: bool}
     */
    private function processImage(Image $image): ?array
    {
        $alt = self::textOf($image);
        $tokens = \preg_split('/\s+/u', \trim($alt)) ?: [];
        $tokens = \array_values(\array_filter($tokens, static fn (string $token): bool => '' !== $token));

        if ([] !== $tokens && 'bg' === $tokens[0]) {
            $background = [
                'url' => $image->getUrl(),
                'size' => 'cover',
                'filter' => '',
                'split' => null,
                'ratio' => null,
                'vertical' => false,
            ];
            $filters = [];

            foreach (\array_slice($tokens, 1) as $token) {
                [$key, $value] = \array_pad(\explode(':', $token, 2), 2, null);
                if ('left' === $key || 'right' === $key) {
                    $background['split'] = $key;
                    $background['ratio'] = null !== $value && \preg_match('/^\d+(\.\d+)?%$/', $value) ? $value : null;
                } elseif ('vertical' === $token) {
                    $background['vertical'] = true;
                } elseif ('fit' === $token || 'contain' === $token) {
                    $background['size'] = 'contain';
                } elseif ('cover' === $token || 'auto' === $token) {
                    $background['size'] = $token;
                } elseif (\preg_match('/^\d+(\.\d+)?%$/', $token)) {
                    $background['size'] = $token;
                } elseif (null !== ($filter = self::filter($key ?? '', $value))) {
                    $filters[] = $filter;
                }
            }
            $background['filter'] = \implode(' ', $filters);

            $parent = $image->parent();
            $image->detach();
            self::pruneEmptyParagraph($parent);

            return $background;
        }

        $style = [];
        $filters = [];
        $rest = [];
        foreach ($tokens as $token) {
            [$key, $value] = \array_pad(\explode(':', $token, 2), 2, null);
            if (null !== $value && \in_array($key, ['w', 'width'], true)) {
                $style[] = 'width:' . self::length($value);
            } elseif (null !== $value && \in_array($key, ['h', 'height'], true)) {
                $style[] = 'height:' . self::length($value);
            } elseif (null !== ($filter = self::filter($key ?? '', $value))) {
                $filters[] = $filter;
            } else {
                $rest[] = $token;
            }
        }
        if ([] !== $filters) {
            $style[] = 'filter:' . \implode(' ', $filters);
        }

        if ([] !== $style) {
            /** @var array<string, mixed> $attributes */
            $attributes = $image->data->get('attributes');
            $attributes['style'] = \implode(';', $style);
            $image->data->set('attributes', $attributes);
        }
        if (\count($rest) !== \count($tokens)) {
            $image->detachChildren();
            $image->appendChild(new Text(\implode(' ', $rest)));
        }

        return null;
    }

    /**
     * 最上位のノード列をページに切る。水平線は捨て、見出し区切りは
     * 新しいページの先頭に残す。先頭の区切りで空ページは作らない。
     *
     * @param list<int> $dividerLevels
     *
     * @return list<list<Node>>
     */
    private function split(Node $document, array $dividerLevels): array
    {
        $slides = [[]];
        $current = 0;

        foreach (\iterator_to_array($document->children(), false) as $node) {
            if ($node instanceof ThematicBreak) {
                $node->detach();
                $slides[++$current] = [];

                continue;
            }

            if ($node instanceof Heading && \in_array($node->getLevel(), $dividerLevels, true) && [] !== $slides[$current]) {
                $slides[++$current] = [];
            }

            $slides[$current][] = $node;
        }

        // 末尾の `---` で終わる原稿から空ページを出さない。先頭も同様。
        $slides = \array_values(\array_filter($slides, static fn (array $nodes): bool => [] !== $nodes));

        return [] === $slides ? [[]] : $slides;
    }

    /**
     * @return array{array<string, mixed>, string}
     */
    private function splitFrontMatter(string $markdown): array
    {
        // 閉じの `---` の直後で原稿が終わっていると正規表現が届かないので、
        // 改行を 1 つ足しておく（Markdown 側には影響しない）。
        try {
            $input = $this->frontMatter->parse($markdown . "\n");
        } catch (InvalidFrontMatterException) {
            return [[], $markdown];
        }

        $front = $input->getFrontMatter();

        return [\is_array($front) ? self::stringKeys($front) : [], $input->getContent()];
    }

    /**
     * コメントの中身を YAML として読む。ディレクティブでなければ空配列。
     *
     * `key: #fff` のような色は YAML ではコメントになって null に化けるので、
     * Marpit の loose parsing と同じく先に引用符で包む。
     *
     * @return array<string, mixed>
     */
    private static function directives(string $body): array
    {
        $body = (string) \preg_replace('/^(\s*_?[A-Za-z]+\s*:\s*)(#[^\s]*)\s*$/m', '$1"$2"', \trim($body));

        try {
            $data = Yaml::parse($body);
        } catch (YamlException) {
            return [];
        }

        return \is_array($data) ? self::stringKeys($data) : [];
    }

    /**
     * @param array<string, mixed> $directives
     *
     * @return array<string, mixed>
     */
    private function pickGlobal(array $directives): array
    {
        return \array_intersect_key($directives, \array_flip(self::GLOBAL_DIRECTIVES));
    }

    /**
     * @param array<string, mixed> $directives
     *
     * @return array<string, mixed>
     */
    private static function pickLocal(array $directives): array
    {
        $keys = [...self::LOCAL_DIRECTIVES, ...\array_map(static fn (string $key): string => '_' . $key, self::LOCAL_DIRECTIVES)];

        return \array_intersect_key($directives, \array_flip($keys));
    }

    /**
     * @return list<int>
     */
    private static function dividerLevels(mixed $value): array
    {
        if (\is_int($value)) {
            return \range(1, \max(1, \min(6, $value)));
        }
        if (\is_array($value)) {
            $levels = [];
            foreach ($value as $level) {
                if (\is_int($level) && $level >= 1 && $level <= 6) {
                    $levels[] = $level;
                }
            }

            return $levels;
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function classList(mixed $value): array
    {
        if (\is_array($value)) {
            $value = \implode(' ', \array_filter($value, 'is_scalar'));
        }
        if (!\is_string($value)) {
            return [];
        }

        $classes = \preg_split('/\s+/', \trim($value)) ?: [];

        return \array_values(\array_filter($classes, static fn (string $class): bool => '' !== $class));
    }

    /**
     * 部分木に含まれるコメント（HtmlBlock / HtmlInline）。
     *
     * @return list<HtmlBlock|HtmlInline>
     */
    private function commentNodes(Node $root): array
    {
        $found = [];
        foreach ($root->iterator() as $node) {
            if (($node instanceof HtmlBlock || $node instanceof HtmlInline)
                && \preg_match('/^(?:\s*<!--.*?-->\s*)+$/s', $node->getLiteral())
            ) {
                $found[] = $node;
            }
        }

        return $found;
    }

    /**
     * @return list<HtmlBlock>
     */
    private function styleNodes(Node $root): array
    {
        $found = [];
        foreach ($root->iterator() as $node) {
            if ($node instanceof HtmlBlock && \preg_match('/^\s*<style\b/i', $node->getLiteral())) {
                $found[] = $node;
            }
        }

        return $found;
    }

    /**
     * @return list<Image>
     */
    private function imageNodes(Node $root): array
    {
        $found = [];
        foreach ($root->iterator() as $node) {
            if ($node instanceof Image) {
                $found[] = $node;
            }
        }

        return $found;
    }

    /**
     * 1 つのノードに複数のコメントが並ぶことがある（`<!-- a --><!-- b -->`）。
     *
     * @return list<string>
     */
    private static function comments(HtmlBlock|HtmlInline $node): array
    {
        \preg_match_all('/<!--(.*?)-->/s', $node->getLiteral(), $matches);

        return $matches[1];
    }

    private static function styleContents(string $literal): string
    {
        return \preg_match('#<style\b[^>]*>(.*?)</style>#is', $literal, $match) ? $match[1] : '';
    }

    private static function detachInline(Node $node): void
    {
        $parent = $node->parent();
        $node->detach();
        self::pruneEmptyParagraph($parent);
    }

    /**
     * 画像やコメントを抜いた結果、中身の無くなった段落を消す
     * （残すと空の `<p>` が余白として出る）。
     */
    private static function pruneEmptyParagraph(?Node $parent): void
    {
        if (!$parent instanceof Paragraph) {
            return;
        }

        foreach ($parent->children() as $child) {
            if ($child instanceof Newline) {
                continue;
            }
            if ($child instanceof Text && '' === \trim($child->getLiteral())) {
                continue;
            }

            return;
        }

        $parent->detach();
    }

    private static function textOf(Node $node): string
    {
        $text = '';
        foreach ($node->iterator() as $child) {
            if ($child !== $node && $child instanceof StringContainerInterface) {
                $text .= $child->getLiteral();
            }
        }

        return $text;
    }

    private function renderInline(string $markdown): string
    {
        $document = $this->parser->parse($markdown);
        $first = $document->firstChild();

        if ($first instanceof Paragraph && null === $first->next()) {
            return $this->renderer->renderNodes($first->children());
        }

        return $this->renderer->renderNodes($document->children());
    }

    private static function filter(string $name, ?string $value): ?string
    {
        if (!isset(self::FILTERS[$name])) {
            return null;
        }

        return \sprintf('%s(%s)', $name, self::cssValue($value ?? self::FILTERS[$name]));
    }

    /** 単位の無い数は px。 */
    private static function length(string $value): string
    {
        return \preg_match('/^\d+(\.\d+)?$/', $value) ? $value . 'px' : self::cssValue($value);
    }

    private static function ratioToPixels(string $ratio, int $width): int
    {
        $percent = (float) \rtrim($ratio, '%');

        return (int) \round($width * \max(0.0, \min(100.0, $percent)) / 100);
    }

    /**
     * インライン style に混ぜても宣言の外へ出られない形にする。
     */
    private static function cssValue(string $value): string
    {
        return \trim((string) \preg_replace('/[;{}\r\n]/', '', $value));
    }

    private static function cssUrl(string $url): string
    {
        return '"' . \str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $url) . '"';
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function attributes(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            $out .= \sprintf(' %s="%s"', $name, self::attr($value));
        }

        return $out;
    }

    private static function attr(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function stringKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }
}

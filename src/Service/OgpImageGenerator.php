<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\SiteConfig;
use GdImage;
use RuntimeException;

/**
 * 記事ごとの OGP 画像を生成する。
 *
 * 生成結果は /images/ogp/*.png に置き、Caddy の静的配信に任せる。
 *
 * **出力は PNG。WebP にしてはいけない。** X(Twitter) のカードクローラは
 * WebP の og:image を取得できず、カードが丸ごと表示されなくなる
 * （ドキュメント上は対応形式に WebP が並んでいるが、実際には出ない）。
 * 手描きの /assets/ogp/top.jpg も同じ理由で JPEG にしてある。
 *
 * 配色はサイトのライトテーマ（assets/tailwind.css の `:root` 側）と
 * 同じトークンを使う。片方だけ変えると OGP だけ浮くので、色を変える
 * ときは両方を合わせること。
 */
final readonly class OgpImageGenerator
{
    /** og:image:width / og:image:height にそのまま出すので public。 */
    public const int WIDTH = 1200;
    public const int HEIGHT = 630;

    /** 出力の見た目を変えたら上げる。ファイル名に混ぜてあるので、古い画像と衝突しない。 */
    private const string VERSION = '3';

    /** サイトのライトテーマのトークン（--color-surface / -ink / -muted / -hairline / -accent）。 */
    private const string COLOR_BG = 'FCFCFA';
    private const string COLOR_INK = '17171B';
    private const string COLOR_MUTED = '63636B';
    private const string COLOR_HAIRLINE = 'E4E4DE';
    private const string COLOR_ACCENT = '1B4965';

    private const int MARGIN_X = 84;

    /** フッターを仕切る罫線の y。タイトルはこの上の領域で縦中央に置く。 */
    private const int FOOTER_Y = 486;

    public function __construct(
        private MediaStorage $media,
        private SiteConfig $site,
        private string $projectRoot,
    ) {}

    public function generate(string $path, string $title): string
    {
        $logoPath = $this->projectRoot . '/assets/ogp/logo.png';
        $fontPath = $this->projectRoot . '/assets/ogp/NotoSansJP-Bold.ttf';

        if (!\is_file($logoPath) || !\is_file($fontPath)) {
            throw new RuntimeException('OGP 画像の生成素材が見つかりません。');
        }

        $relative = \sprintf(
            'ogp/%s.png',
            \substr(\sha1(\implode("\0", [
                $path,
                $title,
                $this->site->title,
                self::VERSION,
                (string) \filemtime($logoPath),
                (string) \filemtime($fontPath),
            ])), 0, 24),
        );
        $destination = $this->media->imagesRoot() . '/' . $relative;

        if (\is_file($destination)) {
            return '/images/' . $relative;
        }

        $directory = \dirname($destination);
        if (!\is_dir($directory) && !@\mkdir($directory, 0o775, true) && !\is_dir($directory)) {
            throw new RuntimeException("OGP 画像の保存先を作成できません: {$directory}");
        }

        $image = $this->createCanvas();

        if ('' !== \trim($title)) {
            $this->drawArticle($image, $fontPath, $title, $this->dateLabel($path));
        } else {
            $this->drawCover($image, $fontPath);
        }

        $this->drawFooter($image, $fontPath, $logoPath);

        $temporary = $destination . '.tmp.' . \bin2hex(\random_bytes(4));
        if (!\imagepng($image, $temporary, 6)) {
            throw new RuntimeException('OGP 画像を書き込めません。');
        }
        \chmod($temporary, 0o644);
        \rename($temporary, $destination);

        return '/images/' . $relative;
    }

    private function createCanvas(): GdImage
    {
        $image = \imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        \imagealphablending($image, true);
        \imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $this->color($image, self::COLOR_BG));

        // 上端のアクセントライン。無地の暗い面が続くとタイムラインで沈むので、
        // 1 本だけ色を入れて「カードの上端」を見せる。
        \imagefilledrectangle($image, 0, 0, self::WIDTH, 8, $this->color($image, self::COLOR_ACCENT));

        return $image;
    }

    /**
     * 日付 + タイトル。2 つで 1 ブロックとして、アクセントラインと
     * フッター罫線の間で縦中央に置く。
     */
    private function drawArticle(GdImage $image, string $fontPath, string $title, string $date): void
    {
        $maxWidth = self::WIDTH - self::MARGIN_X * 2;

        $fontSize = 52;
        $lines = $this->wrap($title, $fontPath, $fontSize, $maxWidth, 4);
        while (\count($lines) > 3 && $fontSize > 40) {
            $fontSize -= 4;
            $lines = $this->wrap($title, $fontPath, $fontSize, $maxWidth, 4);
        }

        $lineHeight = (int) \round($fontSize * 1.55);
        // 日付ラベルの高さ + タイトルとの間隔。
        $dateHeight = '' !== $date ? 82 : 0;
        $areaTop = 60;
        $blockHeight = $dateHeight + \count($lines) * $lineHeight;
        $top = $areaTop + (int) \round(((self::FOOTER_Y - $areaTop) - $blockHeight) / 2);

        if ('' !== $date) {
            $this->drawText($image, $fontPath, $date, 26, self::MARGIN_X, $top + 26, self::COLOR_MUTED, $maxWidth);
        }

        $y = $top + $dateHeight + $fontSize;
        foreach ($lines as $line) {
            $this->drawText($image, $fontPath, $line, $fontSize, self::MARGIN_X, $y, self::COLOR_INK, $maxWidth);
            $y += $lineHeight;
        }
    }

    /**
     * タイトルのないページ向けのフォールバック。サイト名だけを大きく置く。
     */
    private function drawCover(GdImage $image, string $fontPath): void
    {
        $text = $this->site->title;
        $fontSize = 96;
        $width = $this->textBox($text, $fontPath, $fontSize)['width'];
        $x = (int) \max(self::MARGIN_X, \round((self::WIDTH - $width) / 2));

        $this->drawText($image, $fontPath, $text, $fontSize, $x, 300, self::COLOR_INK, self::WIDTH - 80);
    }

    /**
     * 罫線 + アイコン + サイト名。どのページでも同じ形で入れて、
     * 一連の画像が同じサイトのものだと分かるようにする。
     */
    private function drawFooter(GdImage $image, string $fontPath, string $logoPath): void
    {
        \imagefilledrectangle(
            $image,
            self::MARGIN_X,
            self::FOOTER_Y,
            self::WIDTH - self::MARGIN_X,
            self::FOOTER_Y + 1,
            $this->color($image, self::COLOR_HAIRLINE),
        );

        $logoSize = 88;
        $logoY = self::FOOTER_Y + 30;
        $logo = $this->logo($logoPath, $logoSize, 16);
        \imagecopy($image, $logo, self::MARGIN_X, $logoY, 0, 0, $logoSize, $logoSize);

        $textX = self::MARGIN_X + $logoSize + 26;
        $textWidth = self::WIDTH - $textX - self::MARGIN_X;
        $this->drawText($image, $fontPath, $this->site->title, 32, $textX, $logoY + 38, self::COLOR_INK, $textWidth);
        $this->drawText($image, $fontPath, $this->siteHost(), 23, $textX, $logoY + 74, self::COLOR_MUTED, $textWidth);
    }

    /**
     * アイコンを角丸で縮小する。
     *
     * ドット絵なので imagecopyresampled（線形補間）ではなく
     * imagecopyresized（最近傍）を使う。補間するとドットの角が溶ける。
     *
     * @param positive-int $size
     * @param positive-int $radius
     */
    private function logo(string $path, int $size, int $radius): GdImage
    {
        $source = \imagecreatefrompng($path);
        if (!$source instanceof GdImage) {
            throw new RuntimeException('OGP のアイコン画像を読み込めません。');
        }

        $logo = \imagecreatetruecolor($size, $size);
        \imagealphablending($logo, false);
        \imagesavealpha($logo, true);

        $transparent = \imagecolorallocatealpha($logo, 0, 0, 0, 127);
        if (false === $transparent) {
            throw new RuntimeException('OGP のアイコンの透明色を確保できません。');
        }
        \imagefill($logo, 0, 0, $transparent);

        \imagealphablending($logo, true);
        \imagecopyresized($logo, $source, 0, 0, 0, 0, $size, $size, \imagesx($source), \imagesy($source));

        // 四隅を角丸に落とす。中心からの距離が半径を超える画素だけ抜く。
        for ($y = 0; $y < $size; ++$y) {
            for ($x = 0; $x < $size; ++$x) {
                $centerX = $x < $radius ? $radius : ($x >= $size - $radius ? $size - $radius - 1 : $x);
                $centerY = $y < $radius ? $radius : ($y >= $size - $radius ? $size - $radius - 1 : $y);
                if (\sqrt(($x - $centerX) ** 2 + ($y - $centerY) ** 2) > $radius) {
                    \imagesetpixel($logo, $x, $y, $transparent);
                }
            }
        }

        \imagesavealpha($logo, true);

        return $logo;
    }

    /**
     * URL のパスから日付ラベルを作る。記事の URL は Hugo 時代から
     * `/2026/08/10/slug` の形なので、ここから取れば呼び出し側に
     * 日付を渡してもらわずに済む。一覧やタグのページは日付を持たない。
     */
    private function dateLabel(string $path): string
    {
        if (1 !== \preg_match('#^/(\d{4})/(\d{2})/(\d{2})(/|$)#', $path, $matches)) {
            return '';
        }

        return \sprintf('%s.%s.%s', $matches[1], $matches[2], $matches[3]);
    }

    private function siteHost(): string
    {
        $host = \parse_url($this->site->siteUrl, \PHP_URL_HOST);

        return \is_string($host) ? $host : $this->site->siteUrl;
    }

    private function drawText(
        GdImage $image,
        string $fontPath,
        string $text,
        int $fontSize,
        int $x,
        int $baselineY,
        string $hex,
        int $maxWidth,
    ): void {
        while ($fontSize > 20 && $this->textBox($text, $fontPath, $fontSize)['width'] > $maxWidth) {
            --$fontSize;
        }

        \imagettftext($image, $fontSize, 0, $x, $baselineY, $this->color($image, $hex), $fontPath, $text);
    }

    private function color(GdImage $image, string $hex): int
    {
        $value = \hexdec($hex);
        $color = \imagecolorallocate(
            $image,
            (int) (($value >> 16) & 0xFF),
            (int) (($value >> 8) & 0xFF),
            (int) ($value & 0xFF),
        );

        if (false === $color) {
            throw new RuntimeException('OGP 画像の色を確保できません。');
        }

        return $color;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $fontPath, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $text = \trim(\preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ('' === $text) {
            return [];
        }

        $lines = [];
        $line = '';
        $truncated = false;
        \preg_match_all('/[A-Za-z0-9][A-Za-z0-9._+#-]*|\s+|./u', $text, $matches);
        $tokens = $matches[0];
        $total = \count($tokens);

        for ($i = 0; $i < $total; ++$i) {
            $token = $tokens[$i];
            $candidate = $line . $token;

            if ('' !== $line && $this->textBox($candidate, $fontPath, $fontSize)['width'] > $maxWidth) {
                $lines[] = \rtrim($line);
                $line = '';

                if (\count($lines) >= $maxLines) {
                    // 入りきらなかったぶんが残っているときだけ省略記号を付ける。
                    // 「行数の上限で打ち切った」ことを、残りトークンの有無で判定する。
                    $truncated = '' !== \trim(\implode('', \array_slice($tokens, $i)));
                    break;
                }

                $line = \ltrim($token);
                continue;
            }

            $line = $candidate;
        }

        if ('' !== $line && \count($lines) < $maxLines) {
            $lines[] = \rtrim($line);
        }

        if ($truncated && [] !== $lines) {
            $last = \array_key_last($lines);
            $lines[$last] = \rtrim(\mb_substr($lines[$last], 0, -1, 'UTF-8')) . '...';
        }

        return $lines;
    }

    /**
     * @return array{width: int, height: int}
     */
    private function textBox(string $text, string $fontPath, int $fontSize): array
    {
        $box = \imagettfbbox($fontSize, 0, $fontPath, $text);
        if (false === $box) {
            throw new RuntimeException('OGP 画像の文字サイズを計算できません。');
        }

        return [
            'width' => \abs($box[2] - $box[0]),
            'height' => \abs($box[7] - $box[1]),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\SiteConfig;
use GdImage;
use RuntimeException;

/**
 * Hugo 時代の OGP 生成を Relayer 側で再現する。
 *
 * 生成結果は /images/ogp/*.webp に置き、Caddy の静的配信に任せる。
 */
final readonly class OgpImageGenerator
{
    private const int WIDTH = 1200;
    private const int HEIGHT = 630;
    private const string VERSION = '2';

    public function __construct(
        private MediaStorage $media,
        private SiteConfig $site,
        private string $projectRoot,
    ) {}

    public function generate(string $path, string $title): string
    {
        $basePath = $this->projectRoot . '/assets/ogp/base.png';
        $fontPath = $this->projectRoot . '/assets/ogp/NotoSansJP-Bold.ttf';

        if (!\is_file($basePath) || !\is_file($fontPath)) {
            throw new RuntimeException('OGP 画像の生成素材が見つかりません。');
        }

        $relative = \sprintf(
            'ogp/%s.webp',
            \substr(\sha1(\implode("\0", [
                $path,
                $title,
                $this->site->title,
                self::VERSION,
                (string) \filemtime($basePath),
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

        $image = \imagecreatefrompng($basePath);
        if (!$image instanceof GdImage) {
            throw new RuntimeException('OGP ベース画像を読み込めません。');
        }

        if ('' !== \trim($title)) {
            $this->drawTitle($image, $fontPath, $title);
            $this->drawText($image, $fontPath, $this->site->title, 50, 50, 555, 1100);
        } else {
            $this->drawCenteredSiteTitle($image, $fontPath);
        }

        $temporary = $destination . '.tmp.' . \bin2hex(\random_bytes(4));
        if (!\imagewebp($image, $temporary, 85)) {
            throw new RuntimeException('OGP 画像を書き込めません。');
        }
        \chmod($temporary, 0o644);
        \rename($temporary, $destination);

        return '/images/' . $relative;
    }

    private function drawTitle(GdImage $image, string $fontPath, string $title): void
    {
        $fontSize = 55;
        $lines = $this->wrap($title, $fontPath, $fontSize, 1100, 6);

        while (\count($lines) > 5 && $fontSize > 42) {
            $fontSize -= 3;
            $lines = $this->wrap($title, $fontPath, $fontSize, 1100, 6);
        }

        $lineHeight = (int) \round($fontSize * 1.35);
        $y = 100;
        foreach ($lines as $line) {
            $this->drawText($image, $fontPath, $line, $fontSize, 50, $y, 1100);
            $y += $lineHeight;
        }
    }

    private function drawCenteredSiteTitle(GdImage $image, string $fontPath): void
    {
        $fontSize = 100;
        $text = $this->site->title;
        $box = $this->textBox($text, $fontPath, $fontSize);
        $x = (int) \max(40, (self::WIDTH - $box['width']) / 2);
        $this->drawText($image, $fontPath, $text, $fontSize, $x, (int) \round(self::HEIGHT * 0.53), 1120);
    }

    private function drawText(
        GdImage $image,
        string $fontPath,
        string $text,
        int $fontSize,
        int $x,
        int $baselineY,
        int $maxWidth,
    ): void {
        while ($fontSize > 24 && $this->textBox($text, $fontPath, $fontSize)['width'] > $maxWidth) {
            --$fontSize;
        }

        $color = \imagecolorallocate($image, 255, 255, 255);
        if (false === $color) {
            throw new RuntimeException('OGP 画像の文字色を確保できません。');
        }

        \imagettftext($image, $fontSize, 0, $x, $baselineY, $color, $fontPath, $text);
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
        \preg_match_all('/[A-Za-z0-9][A-Za-z0-9._+#-]*|\s+|./u', $text, $matches);
        $tokens = $matches[0];

        foreach ($tokens as $token) {
            $candidate = $line . $token;
            if ('' !== $line && $this->textBox($candidate, $fontPath, $fontSize)['width'] > $maxWidth) {
                $lines[] = \rtrim($line);
                $line = \ltrim($token);
                if (\count($lines) >= $maxLines) {
                    break;
                }
                continue;
            }
            $line = $candidate;
        }

        if ('' !== $line && \count($lines) < $maxLines) {
            $lines[] = \rtrim($line);
        }

        if (\mb_strlen($text, 'UTF-8') > \mb_strlen(\implode('', $lines), 'UTF-8') && [] !== $lines) {
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

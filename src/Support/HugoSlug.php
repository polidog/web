<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Hugo の `urlize` 相当。タグ・カテゴリの URL を Hugo 時代と 1 文字も
 * 変えないために必要。
 *
 * Hugo は内部で `UnicodeSanitize` → `ToLower` を通す:
 *   - 前後の空白を落とし、連続する空白を 1 つの "-" にする
 *   - 文字・数字・結合文字と `_ - . / \ # + ~` 以外を捨てる
 *   - 小文字化する（`disablePathToLower` は未設定なので有効）
 *
 * その結果 `.comマスター` や `--with-expatbuiltin` のような
 * 一見おかしな URL がそのまま正解になる。移行スクリプトは生成結果を
 * website/public/tags の実ディレクトリ名と突き合わせて検証する。
 */
final class HugoSlug
{
    public static function urlize(string $value): string
    {
        $value = \trim($value);
        $value = (string) \preg_replace('/\s+/u', '-', $value);
        $value = (string) \preg_replace('#[^\p{L}\p{N}\p{M}_\-./\\\\\#+~]#u', '', $value);
        $value = \mb_strtolower($value, 'UTF-8');

        // スラッシュは潰さずに残す。`php://input` というタグが実在し、
        // Hugo はそれを `/tags/php/input/` として公開していて、その URL は
        // 今も生きている（連続スラッシュだけ 1 つにまとめる）。
        // 前後のハイフンも落とさない。`--with-expatbuiltin` や `-enable-so`
        // のような configure オプションがそのままタグになっており、Hugo も
        // その形で URL を作っている。
        return (string) \preg_replace('#[/\\\\]{2,}#', '/', $value);
    }

    /**
     * slug を URL のパスに埋める。スラッシュを含む slug（`php/input`）が
     * あるので、まるごと rawurlencode すると `php%2Finput` になって
     * URL が変わってしまう。セグメントごとに encode する。
     */
    public static function toPath(string $basePath, string $slug): string
    {
        $encoded = \implode('/', \array_map(\rawurlencode(...), \explode('/', $slug)));

        return \rtrim($basePath, '/') . '/' . $encoded . '/';
    }
}

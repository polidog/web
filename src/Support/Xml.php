<?php

declare(strict_types=1);

namespace App\Support;

/**
 * RSS 用の最小ヘルパ。`route.php` は毎リクエスト評価されるファイルで、
 * その中で関数を宣言できない（フレームワークの規約）ので、こちらに置く。
 */
final class Xml
{
    public static function escape(string $value): string
    {
        return \htmlspecialchars($value, \ENT_XML1 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * CDATA の中に `]]>` が現れると、そこでセクションが閉じて XML が壊れる。
     * 20 年ぶんの記事にはコードブロックが山ほどあるので、実際に起こりうる。
     */
    public static function cdata(string $value): string
    {
        return \str_replace(']]>', ']]]]><![CDATA[>', $value);
    }
}

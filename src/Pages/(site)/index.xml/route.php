<?php

declare(strict_types=1);

use App\Service\PostRepository;
use App\Support\SiteConfig;
use App\Support\Xml;
use Polidog\Relayer\Http\Response;

/**
 * RSS。Hugo は `outputs.home = ["HTML", "RSS"]` で `/index.xml` に吐いて
 * いたので、購読者を切らないよう同じ URL・同じ形式で出す。
 *
 * ページではなく route.php なのは、XML を返すのに HTML レイアウトを
 * 通したくないため。route.php はディレクトリ名がそのまま静的セグメントに
 * なるので、`index.xml/` というディレクトリで `/index.xml` に届く。
 */
return [
    'GET' => function (PostRepository $posts, SiteConfig $site): Response {
        $items = [];

        foreach ($posts->feed(20) as $post) {
            $url = $site->absoluteUrl((string) $post['path']);
            $publishedAt = (string) ($post['publishedAt'] ?? '');
            $timestamp = '' !== $publishedAt ? \strtotime($publishedAt) : false;

            $items[] = \sprintf(
                "    <item>\n"
                . "      <title>%s</title>\n"
                . "      <link>%s</link>\n"
                . "      <guid isPermaLink=\"true\">%s</guid>\n"
                . "      <pubDate>%s</pubDate>\n"
                . "      <description>%s</description>\n"
                . "      <content:encoded><![CDATA[%s]]></content:encoded>\n"
                . '    </item>',
                Xml::escape((string) $post['title']),
                Xml::escape($url),
                Xml::escape($url),
                false !== $timestamp ? \date(\DATE_RSS, $timestamp) : '',
                Xml::escape((string) ($post['excerpt'] ?? '')),
                Xml::cdata((string) $post['html']),
            );
        }

        $xml = \sprintf(
            "<?xml version=\"1.0\" encoding=\"utf-8\" standalone=\"yes\"?>\n"
            . "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\">\n"
            . "  <channel>\n"
            . "    <title>%s</title>\n"
            . "    <link>%s</link>\n"
            . "    <description>%s</description>\n"
            . "    <language>ja-jp</language>\n"
            . "    <atom:link href=\"%s\" rel=\"self\" type=\"application/rss+xml\"/>\n"
            . "%s\n"
            . "  </channel>\n"
            . '</rss>',
            Xml::escape($site->title),
            Xml::escape($site->absoluteUrl('/')),
            Xml::escape($site->description),
            Xml::escape($site->absoluteUrl('/index.xml')),
            \implode("\n", $items),
        );

        return Response::text($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
            // 記事を保存するたびに PostWriter が /index.xml を purge するので、
            // エッジには長く持たせてよい。
            'Cache-Control' => 'public, max-age=0, s-maxage=604800',
        ]);
    },
];

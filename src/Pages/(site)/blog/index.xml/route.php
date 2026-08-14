<?php

declare(strict_types=1);

use App\Service\PostRepository;
use App\Support\SiteConfig;
use App\Support\Xml;
use Polidog\Relayer\Http\Response;

/**
 * `/blog/index.xml` — セクションの RSS。Hugo は
 * `outputs.section = ["HTML", "RSS"]` でこれも吐いていた。中身は
 * `/index.xml` と同じ（このサイトのセクションは blog だけ）だが、
 * こちらの URL で購読している人を切らないために残す。
 *
 * タグ・カテゴリごとの RSS（`/tags/php/index.xml` など 741 本）は
 * 引き継いでいない。1 タグ 1 ルートを作るのは割に合わないと判断した。
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
                . '    </item>',
                Xml::escape((string) $post['title']),
                Xml::escape($url),
                Xml::escape($url),
                false !== $timestamp ? \date(\DATE_RSS, $timestamp) : '',
                Xml::escape((string) ($post['excerpt'] ?? '')),
            );
        }

        $xml = \sprintf(
            "<?xml version=\"1.0\" encoding=\"utf-8\" standalone=\"yes\"?>\n"
            . "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n"
            . "  <channel>\n"
            . "    <title>Blogs | %s</title>\n"
            . "    <link>%s</link>\n"
            . "    <description>%s</description>\n"
            . "    <language>ja-jp</language>\n"
            . "    <atom:link href=\"%s\" rel=\"self\" type=\"application/rss+xml\"/>\n"
            . "%s\n"
            . "  </channel>\n"
            . '</rss>',
            Xml::escape($site->title),
            Xml::escape($site->absoluteUrl('/blog')),
            Xml::escape($site->description),
            Xml::escape($site->absoluteUrl('/blog/index.xml')),
            \implode("\n", $items),
        );

        return Response::text($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=0, s-maxage=604800',
        ]);
    },
];

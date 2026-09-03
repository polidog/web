<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\SiteConfig;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Polidog\Relayer\Http\Request;
use Polidog\Relayer\Http\Response;

/**
 * 公開記事を JSON で返す口。
 *
 * HTML と **同じ URL** に `Accept: application/json` を付けて取りに来た
 * ときだけこちらが応える。入口は `src/Pages/middleware.php`。
 *
 * ```
 * GET /archives/            Accept: application/json  → 全記事の索引
 * GET /2026/09/01/git-dmb/  Accept: application/json  → その記事 1 本
 * ```
 *
 * ## なぜ URL を増やさないのか
 *
 * `/index.json` のような別 URL を生やすと、記事 1 本ごとに「HTML の URL」と
 * 「JSON の URL」の 2 つを持つことになる。このサイトは Hugo 時代から
 * **URL を 1 本も変えない**ことを軸にしているので、表現が増えるたびに
 * URL 空間が増えるほうを避けた。`/index.xml`（RSS）だけが別 URL なのは
 * 購読者がその URL を登録済みだからで、新しく作るものには当てはまらない。
 *
 * ## なぜエッジに載せないのか（`Cache-Control: no-store`）
 *
 * Cloudflare は `Accept` をキャッシュキーに入れない。`Vary: Accept` も
 * 見ない（尊重されるのは `Accept-Encoding` と、Enterprise の Custom Cache
 * Key だけ）。つまり **JSON をエッジに載せた瞬間、次にその URL を開いた
 * 読者のブラウザに JSON が降る**。ここは 1 バイトも共有キャッシュに置かず、
 * オリジンで毎回組み立てる。
 *
 * 逆向き——既にエッジにある HTML が JSON 要求にも HIT する——はオリジンに
 * リクエストが届かないので、ここでは防げない。Cloudflare 側に
 * 「`Accept` に `application/json` を含むなら Bypass cache」の Cache Rule
 * が要る（Free プランでも作れる bypass ルール。README 参照）。
 *
 * オリジンで組み立てるコストは、索引が 1,300 行の SELECT 2 本、記事詳細が
 * 主キー相当の 1 行引き。読み手は機械（`kyoten` の定時便）1 つで、
 * 索引の版を見て変わったものだけ取りに来る想定なので、これで足りる。
 */
final readonly class JsonApi
{
    /**
     * 記事 URL の形。Hugo の permalinks
     * （`blog = "/:year/:month/:day/:filename/"`）がそのまま生きている。
     */
    private const string POST_PATH = '#^/(\d{4})/(\d{2})/(\d{2})/([^/]+)$#';

    /**
     * 索引を出す URL。月別アーカイブ（全記事を新しい順に並べたページ）の
     * JSON 表現、という位置づけ。`/archives/page/2/` には付けない——
     * JSON は全件を 1 度に返すので、ページングに対応する表現が無い。
     */
    private const string INDEX_PATH = '/archives';

    public function __construct(
        private PostRepository $posts,
        private SiteConfig $site,
    ) {}

    /**
     * JSON を求められていればその応答を、そうでなければ null（＝通常の
     * HTML へ進ませる）を返す。
     */
    public function respond(Request $request): ?Response
    {
        if (!$this->wantsJson($request)) {
            return null;
        }

        $path = $this->normalize($request->path);

        if (self::INDEX_PATH === $path) {
            return $this->index();
        }

        if (1 === \preg_match(self::POST_PATH, $path)) {
            return $this->post($path);
        }

        // 一覧・タグ・管理画面など、JSON 表現を持たない URL。HTML を返す。
        return null;
    }

    /**
     * JSON を明示的に求めているか。
     *
     * `application/json` を含み、かつ `text/html` を含まないときだけ真。
     * ブラウザの `Accept` は `text/html,application/xhtml+xml,…,*&#47;*;q=0.8`
     * なので `application/json` は入らないが、両方を並べてくるクライアント
     * （`fetch` に手で付けた場合など）は HTML を望んでいる可能性がある。
     * 迷ったら人間向けの表現を返すほうに倒す。
     *
     * GET だけを見る。HEAD は本文を持たないので JSON にする意味が無く、
     * POST 以降はこのサイトに公開の書き込み口が無い。
     */
    private function wantsJson(Request $request): bool
    {
        if (!$request->isGet()) {
            return false;
        }

        $accept = \strtolower($request->header('accept') ?? '');

        return \str_contains($accept, 'application/json')
            && !\str_contains($accept, 'text/html');
    }

    /**
     * ルーターと同じ正規化。末尾スラッシュを落とし、日本語スラッグのために
     * セグメントごとの URL エンコードを解く（`/tags/php/input/` のような
     * スラッシュを含む slug があるので、パス全体を一度に decode しない）。
     */
    private function normalize(string $path): string
    {
        $segments = \array_map(
            static fn (string $segment): string => \rawurldecode($segment),
            \explode('/', \trim($path, '/')),
        );

        $joined = \implode('/', \array_filter($segments, static fn (string $s): bool => '' !== $s));

        return '' === $joined ? '/' : '/' . $joined;
    }

    private function index(): Response
    {
        $tags = $this->posts->tagSlugsByPost();
        $posts = [];

        foreach ($this->posts->indexAll() as $post) {
            $posts[] = [
                'path' => $post['path'],
                'url' => $this->site->absoluteUrl($post['path']),
                'title' => $post['title'],
                'publishedAt' => self::iso($post['publishedAt']),
                'updatedAt' => self::iso($post['updatedAt']),
                'tags' => $tags[$post['id']] ?? [],
            ];
        }

        return self::json([
            'site' => $this->site->absoluteUrl('/'),
            'title' => $this->site->title,
            // 公開済みコンテンツ全体の版。1 本でも保存されれば変わるので、
            // 前回と同じなら記事を 1 件も取り直さなくてよい。
            'version' => $this->posts->contentVersion(),
            'count' => \count($posts),
            'posts' => $posts,
        ]);
    }

    private function post(string $path): Response
    {
        $post = $this->posts->findPublishedByPath($path, 'post');

        if (null === $post) {
            return self::json(['error' => 'not_found', 'path' => $path], 404);
        }

        $id = $post['id'];

        return self::json([
            'path' => $post['path'],
            'url' => $this->site->absoluteUrl($post['path']),
            'title' => $post['title'],
            'publishedAt' => self::iso($post['publishedAt']),
            'updatedAt' => self::iso($post['updatedAt']),
            'excerpt' => $post['excerpt'],
            'eyecatch' => null !== $post['eyecatch']
                ? $this->site->absoluteUrl($post['eyecatch'])
                : null,
            'tags' => self::slugs($this->posts->tagsOf($id)),
            'categories' => self::slugs($this->posts->categoriesOf($id)),
            // 書いたままの Markdown と、保存時に変換済みの HTML。読み手が
            // 原文を欲しがることも、そのまま表示したがることもあるので両方出す。
            'markdown' => $post['body'],
            'html' => $post['html'],
        ]);
    }

    /**
     * SQLite の TEXT（`2026-08-31 13:02:49`）を ISO 8601 にする。
     *
     * 保存されている値にタイムゾーンが無く、このサイトは JST 固定
     * （記事の URL が公開日から作られるので、ずれると URL が変わる）。
     * 生の文字列のまま出すと読み手が UTC と解釈しかねないので、
     * オフセットまで書いた形（`2026-08-31T13:02:49+09:00`）で渡す。
     */
    private static function iso(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            $at = new DateTimeImmutable($value, new DateTimeZone(SiteConfig::TIMEZONE));
        } catch (Exception) {
            // 読めない値をここで捨てると欠測と区別が付かないので、
            // 原文をそのまま渡して読み手に判断させる。
            return $value;
        }

        return $at->format(DateTimeInterface::ATOM);
    }

    /**
     * @param list<array{id: int, name: string, slug: string}> $terms
     *
     * @return list<array{name: string, slug: string}>
     */
    private static function slugs(array $terms): array
    {
        return \array_map(
            static fn (array $term): array => ['name' => $term['name'], 'slug' => $term['slug']],
            $terms,
        );
    }

    private static function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status, [
            // 共有キャッシュに載せない理由はクラスの説明を参照。
            'Cache-Control' => 'no-store',
            // Cloudflare は見ないが、間に入る他のキャッシュのために正しい
            // ことを言っておく。
            'Vary' => 'Accept',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use Polidog\Relayer\Http\Client\HttpClient;
use Polidog\Relayer\Http\Client\HttpClientException;
use Psr\Log\LoggerInterface;

/**
 * Cloudflare のエッジキャッシュを URL 単位で捨てる。
 *
 * このサイトのコスト構造は「記事詳細を長期キャッシュ（s-maxage 1 週間）
 * させ、更新時だけ purge する」で決まる。一覧・タグ・アーカイブは
 * 記事が増えるたびに中身が変わり、purge 対象を数え上げると 53 ページ分に
 * なってしまうので、そちらは purge せず s-maxage を数分に留める方で
 * 折り合いをつけている（PageCache 参照）。
 *
 * zone_id / api_token が未設定なら黙って何もしない。ローカル開発と、
 * Cloudflare を挟まない状態でのデプロイをそのまま動かすため。
 */
final class CachePurger
{
    /** Cloudflare の files purge は 1 リクエスト 30 URL まで。 */
    private const int CHUNK = 30;

    public function __construct(
        private readonly HttpClient $http,
        private readonly LoggerInterface $logger,
        private readonly string $zoneId = '',
        private readonly string $apiToken = '',
        private readonly string $siteUrl = '',
    ) {}

    public function enabled(): bool
    {
        return '' !== $this->zoneId && '' !== $this->apiToken && '' !== $this->siteUrl;
    }

    /**
     * @param list<string> $paths サイトルートからのパス（'/blog/2024/12/x' 形式）
     */
    public function purge(array $paths): void
    {
        if (!$this->enabled() || [] === $paths) {
            return;
        }

        foreach (\array_chunk($this->toUrls($paths), self::CHUNK) as $chunk) {
            $this->send(['files' => $chunk]);
        }
    }

    /**
     * 移行スクリプトのように何千件も触ったあとで使う全消し。
     */
    public function purgeEverything(): void
    {
        if (!$this->enabled()) {
            return;
        }

        $this->send(['purge_everything' => true]);
    }

    /**
     * Hugo 時代の URL は末尾スラッシュ付きで、Relayer はどちらでも同じ
     * ページを返す。Cloudflare のキャッシュキーは URL 完全一致なので、
     * 両方を purge しないと片方が生き残る。
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function toUrls(array $paths): array
    {
        $base = \rtrim($this->siteUrl, '/');
        $urls = [];

        foreach ($paths as $path) {
            $path = '/' . \ltrim($path, '/');
            $bare = '/' !== $path ? \rtrim($path, '/') : '/';

            $urls[] = $base . $bare;
            if ('/' !== $bare) {
                $urls[] = $base . $bare . '/';
            }
        }

        return \array_values(\array_unique($urls));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(array $payload): void
    {
        $url = \sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', \rawurlencode($this->zoneId));

        try {
            $response = $this->http->request(
                'POST',
                $url,
                [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                \json_encode($payload, \JSON_THROW_ON_ERROR),
            );
        } catch (HttpClientException $e) {
            // purge の失敗で保存自体を落とさない。エッジは s-maxage の
            // 期限が来れば必ず取り直すので、遅れるだけで壊れはしない。
            $this->logger->error('Cloudflare purge failed (transport)', ['error' => $e->getMessage()]);

            return;
        }

        if (!$response->ok()) {
            $this->logger->error('Cloudflare purge rejected', [
                'status' => $response->status,
                'body' => $response->body,
            ]);
        }
    }
}

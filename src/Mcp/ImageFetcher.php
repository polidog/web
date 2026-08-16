<?php

declare(strict_types=1);

namespace App\Mcp;

/**
 * URL から画像を落としてくる。
 *
 * これは「外から渡された URL をサーバーに取りに行かせる」処理なので、
 * 素朴に書くと SSRF になる —— このサーバーは fly.io の内部ネットワークにも
 * SQLite にも手が届く場所にいる。防ぎ方は 3 つ:
 *
 *  1. https のみ。
 *  2. **名前解決した IP を自分で見て**、プライベート・ループバック・
 *     リンクローカルなら拒否する。
 *  3. curl にリダイレクトを追わせず（CURLOPT_FOLLOWLOCATION は使わない）、
 *     ホップごとに 2 をやり直す。リダイレクト先が 127.0.0.1 に化ける
 *     手口はこれで塞がる。加えて解決済みの IP を CURLOPT_RESOLVE で
 *     固定し、検査と接続の間に DNS の答えが変わる隙間を無くす。
 *
 * ファイル形式は見ない。中身から判定して弾くのは MediaStorage の仕事で、
 * こちらが Content-Type を信じて先回りしても二重になるだけ。
 */
final readonly class ImageFetcher
{
    private const int MAX_BYTES = 10 * 1024 * 1024;

    private const int MAX_REDIRECTS = 3;

    private const int TIMEOUT = 20;

    public function __construct(
        private string $uploadsDir,
    ) {}

    /**
     * @return array{path: string, filename: string} 一時ファイル。呼び出し側が必ず片付ける
     */
    public function fetch(string $url, string $filename = ''): array
    {
        $body = $this->download($url, self::MAX_REDIRECTS);

        $name = '' !== $filename ? $filename : self::filenameFromUrl($url);
        $temporary = $this->temporaryPath();

        if (false === \file_put_contents($temporary, $body)) {
            throw new McpToolException('画像を一時ファイルに保存できませんでした。');
        }

        return ['path' => $temporary, 'filename' => $name];
    }

    private function download(string $url, int $redirectsLeft): string
    {
        if ($redirectsLeft < 0) {
            throw new McpToolException('リダイレクトが多すぎます。');
        }

        [$host, $port] = $this->inspect($url);
        $ip = $this->resolvePublicIp($host);

        $handle = \curl_init($url);
        if (false === $handle) {
            throw new McpToolException('画像の取得を開始できませんでした。');
        }

        $received = 0;
        $body = '';

        \curl_setopt_array($handle, [
            \CURLOPT_RETURNTRANSFER => false,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_CONNECTTIMEOUT => 10,
            \CURLOPT_TIMEOUT => self::TIMEOUT,
            \CURLOPT_IPRESOLVE => \CURL_IPRESOLVE_V4,
            // 検査した IP そのものに繋ぐ。ここを curl の再解決に任せると、
            // 検査と接続の間に別の答えを返す DNS を防げない。
            \CURLOPT_RESOLVE => [\sprintf('%s:%d:%s', $host, $port, $ip)],
            \CURLOPT_USERAGENT => 'polidog.jp-mcp/1.0',
            \CURLOPT_HEADER => false,
            \CURLOPT_WRITEFUNCTION => static function ($_handle, string $chunk) use (&$received, &$body): int {
                $received += \strlen($chunk);

                if ($received > self::MAX_BYTES) {
                    // 0 を返すと curl は転送を中止する。
                    return 0;
                }

                $body .= $chunk;

                return \strlen($chunk);
            },
        ]);

        $ok = \curl_exec($handle);
        $status = (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
        $location = \curl_getinfo($handle, \CURLINFO_REDIRECT_URL);
        $error = \curl_error($handle);
        // curl_close() は呼ばない。PHP 8.0 以降は効果が無く、8.5 で deprecated
        // になった。呼ぶと警告がレスポンス本文の先頭に混ざり、JSON が壊れる
        // （ハンドルは参照が切れた時点で解放される）。

        if ($received > self::MAX_BYTES) {
            throw new McpToolException(\sprintf('画像が大きすぎます（上限 %d MB）。', \intdiv(self::MAX_BYTES, 1024 * 1024)));
        }

        if (false === $ok) {
            throw new McpToolException(\sprintf('画像を取得できませんでした（%s）。', '' !== $error ? $error : '通信エラー'));
        }

        if ($status >= 300 && $status < 400) {
            if (!\is_string($location) || '' === $location) {
                throw new McpToolException('リダイレクト先が分かりませんでした。');
            }

            return $this->download($location, $redirectsLeft - 1);
        }

        if (200 !== $status) {
            throw new McpToolException(\sprintf('画像の取得に失敗しました（HTTP %d）。', $status));
        }

        if ('' === $body) {
            throw new McpToolException('取得した画像が空でした。');
        }

        return $body;
    }

    /**
     * URL を検査してホストとポートを返す。
     *
     * @return array{0: string, 1: int}
     */
    private function inspect(string $url): array
    {
        $parts = \parse_url($url);

        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new McpToolException('画像の URL が正しくありません。');
        }

        if ('https' !== \strtolower($parts['scheme'])) {
            throw new McpToolException('画像の URL は https で指定してください。');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : 443;

        return [$parts['host'], $port];
    }

    /**
     * ホスト名を解決し、公開されたアドレスであることを確かめる。
     */
    private function resolvePublicIp(string $host): string
    {
        // ホスト名が最初から IP リテラルの場合もそのまま検査に回る。
        $addresses = \filter_var($host, \FILTER_VALIDATE_IP) ? [$host] : \gethostbynamel($host);

        if (false === $addresses || [] === $addresses) {
            throw new McpToolException(\sprintf('%s を解決できませんでした。', $host));
        }

        foreach ($addresses as $address) {
            // 1 つでも内側を向いていたら使わない。DNS が複数のアドレスを
            // 返すとき、どれに繋がるかを当てにできないため。
            if (!\filter_var(
                $address,
                \FILTER_VALIDATE_IP,
                \FILTER_FLAG_IPV4 | \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            )) {
                throw new McpToolException(\sprintf(
                    '%s は内部ネットワークのアドレスを指しています。外部に公開された画像の URL を渡してください。',
                    $host,
                ));
            }
        }

        return $addresses[0];
    }

    /**
     * 一時ファイルは **uploads と同じファイルシステムに置く**。
     *
     * MediaStorage::store() は `is_uploaded_file()` でないファイルを
     * `rename()` で動かすが、rename はデバイスをまたげない。本番の
     * /tmp はイメージの中、uploads は volume の上なので、システムの
     * 一時ディレクトリを使うと本番だけ保存に失敗する。
     */
    private function temporaryPath(): string
    {
        $directory = \rtrim($this->uploadsDir, '/') . '/tmp';

        if (!\is_dir($directory) && !@\mkdir($directory, 0o775, true) && !\is_dir($directory)) {
            throw new McpToolException('一時ディレクトリを作成できませんでした。');
        }

        return $directory . '/' . \bin2hex(\random_bytes(8));
    }

    private static function filenameFromUrl(string $url): string
    {
        $path = \parse_url($url, \PHP_URL_PATH);

        if (!\is_string($path) || '' === $path) {
            return 'image';
        }

        $name = \rawurldecode(\basename($path));

        return '' !== $name ? $name : 'image';
    }
}

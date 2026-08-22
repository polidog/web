<?php

declare(strict_types=1);

use App\AppConfigurator;
use App\Http\SiteDocument;
use App\Support\SiteConfig;
use Polidog\Relayer\Relayer;

require_once __DIR__ . '/../vendor/autoload.php';

// 記事の URL は公開日から組み立てられるので、タイムゾーンがずれると
// URL が変わってしまう。php.ini にも書いてあるが、それを読まない環境
// （`php -S` での開発）でも同じ結果になるようここでも固定する。
\date_default_timezone_set(SiteConfig::TIMEZONE);

$projectRoot = __DIR__ . '/..';

$router = Relayer::boot($projectRoot, new AppConfigurator($projectRoot));

// 既定の HtmlDocument を差し替える。canonical の <link> と、ダークモードの
// ちらつきを消す head インラインスクリプトが要るため（src/Http/SiteDocument.php）。
// コンテナ経由で取るので、ページ側に注入されるのと同じインスタンスになる。
$document = Relayer::container()->get(SiteDocument::class);
$router->setDocument($document);

// 1 リクエストぶんの処理。classic モードでは 1 回、worker モードでは
// 同じプロセスで繰り返し呼ばれる。
//
// Relayer の run() は毎回 Request::fromGlobals() で組み直し、finally で
// 自分の静的状態を掃除するので、ここで面倒を見るのはアプリ側の
// シングルトンに溜まる状態だけ。
$handle = static function () use ($router, $document): void {
    // SiteDocument は DI のシングルトンで、title や og:image、
    // addHeadHtml() で足した <meta robots> が前のリクエストから残る。
    // ページが毎回 PageMeta を通す保証は無いので、入口で白紙に戻す。
    $document->reset();

    try {
        $router->run();
    } catch (\Throwable $e) {
        // ページから漏れた例外をここで止める。worker モードで素通しすると
        // worker ごと落ちて再 boot になる（応答自体は 500 で返るが、
        // 次のリクエストが boot 待ちになる）。classic モードでも、PHP の
        // 既定より素っ気ない 500 を返すだけで挙動は変わらない。
        \error_log(\sprintf('Unhandled %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
        if (!\headers_sent()) {
            \http_response_code(500);
            \header('Content-Type: text/plain; charset=UTF-8');
        }
        echo "500 Internal Server Error\n";
    } finally {
        // フレームワークと usePHP が static に持つリクエスト由来の状態
        // （コンポーネント状態・ストレージ・RenderContext・翻訳）を捨てる。
        // run() の finally でも大半は消えるが、exit 経路では finally が
        // 走らないので、ここでもう一度呼ぶ（冪等）。
        Relayer::endRequest();
    }
};

// FrankenPHP の worker モード。Caddyfile の `worker { file ... }` が
// このファイルを指しているときだけ FRANKENPHP_WORKER が立つ。
// `frankenphp_handle_request()` は worker 以外で呼ぶと例外なので、
// function_exists ではなくこの変数で分岐する。
if (isset($_SERVER['FRANKENPHP_WORKER'])) {
    // false が返るのは FrankenPHP が止まるときか、Caddyfile の
    // `max_requests` に達して worker を作り直すとき。ループを抜ければ
    // スクリプトが終わり、FrankenPHP がもう一度このファイルを起動する。
    while (\frankenphp_handle_request($handle)) {
        \gc_collect_cycles();
    }

    return;
}

$handle();

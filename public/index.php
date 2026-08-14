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
$router->setDocument(Relayer::container()->get(SiteDocument::class));

$router->run();

#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * 公開中の全ページぶんの ETag を作り直し、Cloudflare を全消しする。
 *
 *   php bin/refresh-caches.php
 *
 * ETag は volume 上の `EtagStore` に持っているので通常は消えないが、
 * 以下のときは無い状態から始まるので、明示的に埋め直す必要がある:
 *   - 新しい環境に移したとき（volume が空）
 *   - DB を入れ替えたとき（移行スクリプトは最後にこれと同じことをする）
 *   - ETag のディレクトリを手で消したとき
 *
 * ETag が無くても壊れはしない（`Cache-Control` は出るので CDN には載る）。
 * ただし 304 の短絡が効かず、キャッシュが切れるたびにオリジンが本文を
 * 作り直すことになる。
 */

use App\AppConfigurator;
use App\Service\PostRepository;
use App\Service\PostWriter;
use App\Support\SiteConfig;
use Polidog\Relayer\Relayer;

require __DIR__ . '/../vendor/autoload.php';

\date_default_timezone_set(SiteConfig::TIMEZONE);

$projectRoot = \dirname(__DIR__);
Relayer::boot($projectRoot, new AppConfigurator($projectRoot));
$container = Relayer::container();

/** @var PostRepository $posts */
$posts = $container->get(PostRepository::class);
/** @var PostWriter $writer */
$writer = $container->get(PostWriter::class);

$count = \count($posts->allPaths());
echo "公開中のページ: {$count} 件\n";

$writer->refreshAllCaches();

echo "ETag を作り直し、CDN のキャッシュを破棄しました。\n";

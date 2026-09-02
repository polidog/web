<?php

declare(strict_types=1);

use App\Service\JsonApi;
use Polidog\Relayer\Http\Request;
use Polidog\Relayer\Http\Response;
use Polidog\Relayer\Relayer;

/**
 * `Accept: application/json` で来たリクエストを JSON で返す。
 *
 * ページと `route.php` は同じディレクトリに置けないので、HTML と JSON を
 * 同じ URL で出し分ける場所はここしかない。判断も組み立ても
 * `App\Service\JsonApi` にあり、ここは「JSON なら送る、違えば通す」だけ。
 * middleware.php はリクエストごとに require され直すため宣言を置けない
 * （そのぶんロジックはサービスに寄せる必要がある）。
 *
 * コンテナから引くのは、middleware の引数が `Request` と `$next` に
 * 固定されていてオートワイヤが効かないため。
 */
return function (Request $request, Closure $next): void {
    $response = Relayer::container()->get(JsonApi::class)->respond($request);

    if (!$response instanceof Response) {
        $next($request);

        return;
    }

    $response->send();
};

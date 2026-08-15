<?php

declare(strict_types=1);

use App\Http\UploadedFiles;
use App\Service\MediaStorage;
use Polidog\Relayer\Auth\Authenticator;
use Polidog\Relayer\Http\Request;
use Polidog\Relayer\Http\Response;
use Polidog\Relayer\Router\Form\CsrfToken;

/**
 * エディタからの画像アップロード（ドロップ・貼り付け）。
 *
 * `/admin/media` のページと同じ MediaStorage を通すので、保存先も許可形式も
 * 一覧からのアップロードと完全に同じ。SVG は受けない（XML なのでスクリプトを
 * 持てて、`/images/*` は管理画面と同じオリジンから配信される）。
 *
 * **CSRF は自前で見る。** フォームの server action と違い、`route.php` の
 * ハンドラにはフレームワークの CSRF 検証が入らない。これは副作用のある POST
 * なので、admin.js がエディタのフォームに埋まっているトークンを一緒に送り、
 * ここで突き合わせている。
 */
return [
    'POST' => function (
        Request $request,
        MediaStorage $media,
        UploadedFiles $uploads,
        Authenticator $auth,
    ): Response {
        if (!$auth->hasRole('admin')) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        if (!CsrfToken::validate($request->post('_usephp_csrf') ?? '')) {
            return Response::json(['error' => 'CSRF トークンが不正です。'], 419);
        }

        $files = $uploads->all('file');
        if ([] === $files) {
            return Response::json(['error' => 'ファイルがありません。'], 400);
        }

        $file = $files[0];
        if (\UPLOAD_ERR_OK !== $file['error']) {
            return Response::json(
                ['error' => \sprintf('アップロードに失敗しました（コード %d）。', $file['error'])],
                400,
            );
        }

        try {
            return Response::json(['url' => $media->store($file['tmpName'], $file['name'])]);
        } catch (\RuntimeException $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        }
    },
];

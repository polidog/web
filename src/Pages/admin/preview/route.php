<?php

declare(strict_types=1);

use App\Service\MarkdownRenderer;
use Polidog\Relayer\Auth\Authenticator;
use Polidog\Relayer\Http\Request;
use Polidog\Relayer\Http\Response;

/**
 * 編集中の Markdown をレンダリングして返す。
 *
 * ブラウザ側に JS の Markdown パーサを置かないのは、保存時と表示時で
 * 変換結果がずれると「プレビューでは正しかったのに」が起きるため。
 * shortcode の展開も含めて、変換は常にサーバの MarkdownRenderer 1 本。
 */
return [
    'POST' => function (Request $request, MarkdownRenderer $markdown, Authenticator $auth): Response {
        if (!$auth->hasRole('admin')) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        return Response::json([
            'html' => $markdown->render($request->post('body') ?? ''),
        ]);
    },
];

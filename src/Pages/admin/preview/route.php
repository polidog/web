<?php

declare(strict_types=1);

use App\Service\MarkdownRenderer;
use App\Service\MarpRenderer;
use Polidog\Relayer\Auth\Authenticator;
use Polidog\Relayer\Http\Request;
use Polidog\Relayer\Http\Response;

/**
 * 編集中の Markdown をレンダリングして返す。
 *
 * ブラウザ側に JS の Markdown パーサを置かないのは、保存時と表示時で
 * 変換結果がずれると「プレビューでは正しかったのに」が起きるため。
 * shortcode の展開も含めて、変換は常にサーバの MarkdownRenderer 1 本。
 *
 * `kind=slide` なら MarpRenderer。こちらも保存時（PostWriter）と同じ
 * 変換器で、デッキの CSS は `<style>` として HTML に抱き合わせて返す
 * （プレビュー欄は innerHTML で流し込むので、そのまま効く）。
 */
return [
    'POST' => function (
        Request $request,
        MarkdownRenderer $markdown,
        MarpRenderer $marp,
        Authenticator $auth,
    ): Response {
        if (!$auth->hasRole('admin')) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $body = $request->post('body') ?? '';

        if ('slide' === $request->post('kind')) {
            $deck = $marp->render($body);

            return Response::json([
                'html' => $deck->storable(),
                'pages' => $deck->pages,
            ]);
        }

        return Response::json([
            'html' => $markdown->render($body),
        ]);
    },
];

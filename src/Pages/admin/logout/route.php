<?php

declare(strict_types=1);

use Polidog\Relayer\Auth\Authenticator;
use Polidog\Relayer\Http\Response;

/**
 * ログアウト。Authenticator がセッションから principal を消し、
 * セッション ID を再生成する。
 */
return [
    'GET' => function (Authenticator $auth): Response {
        $auth->logout();

        return Response::redirect('/admin/login', 302);
    },
];

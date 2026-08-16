<?php

declare(strict_types=1);

namespace App\Auth\Oauth;

use RuntimeException;

/**
 * 認可リクエストが受け付けられないときに投げる。
 *
 * メッセージは**そのまま同意画面に出す日本語**。この段階のエラーで
 * リダイレクトを返さないのは、redirect_uri がまだ信用できない
 * （登録値と一致していない可能性がある）ため —— 検証前の URL へ
 * 飛ばすと、認可サーバーがオープンリダイレクタになる。
 */
final class OauthException extends RuntimeException {}

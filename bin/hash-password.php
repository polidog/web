#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * 管理画面のパスワードハッシュを作る。
 *
 *   php bin/hash-password.php
 *   Password: （入力は表示されない）
 *   ADMIN_PASSWORD_HASH='$2y$12$...'
 *
 * 出た行をそのまま .env.local（ローカル）に貼るか、fly では
 *
 *   fly secrets set ADMIN_PASSWORD_HASH='$2y$12$...'
 *
 * として入れる。**シングルクォートは外さないこと** —— ハッシュに含まれる
 * `$` が、シェルや Dotenv の変数展開に食われる。
 *
 * 平文は引数で渡せない（`ps` と shell の履歴に残るため）。
 */

$isTty = \stream_isatty(\STDIN);

if ($isTty) {
    \fwrite(\STDOUT, 'Password: ');
    \shell_exec('stty -echo');
}

$password = \fgets(\STDIN);

if ($isTty) {
    \shell_exec('stty echo');
    \fwrite(\STDOUT, "\n");
}

$password = \is_string($password) ? \rtrim($password, "\r\n") : '';

if ('' === $password) {
    \fwrite(\STDERR, "パスワードが空です。\n");

    exit(1);
}

// PASSWORD_DEFAULT は PHP が「今いちばん強い」とみなすもの（現状 bcrypt）。
// Relayer の NativePasswordHasher も既定でこれを使うので、検証側と揃う。
$hash = \password_hash($password, \PASSWORD_DEFAULT);

\fwrite(\STDOUT, "ADMIN_PASSWORD_HASH='" . $hash . "'\n");

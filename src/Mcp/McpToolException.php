<?php

declare(strict_types=1);

namespace App\Mcp;

use RuntimeException;

/**
 * ツールが仕事をできなかったときに投げる。
 *
 * これは**プロトコルのエラーではない**ので、JSON-RPC の error にはしない。
 * HTTP 200 のまま `isError: true` を立てて返し、メッセージはモデルが読む。
 * 「その path は使用中です」「記事が見つかりません」のように、
 * 次に何をすればいいか分かる日本語で書くこと —— この文字列は最終的に
 * 会話に現れて、ユーザーが読む。
 */
final class McpToolException extends RuntimeException {}

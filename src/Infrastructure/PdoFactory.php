<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use RuntimeException;

/**
 * SQLite 接続の唯一の生成点。
 *
 * tehilim は「PDO は自分で用意しろ」という設計なので、接続属性の面倒を
 * ここに集約する。CDN 前段でキャッシュする構成上、読み取りはほとんど
 * オリジンに届かない一方、管理画面からの書き込みは記事保存のたびに走る。
 * WAL にしておくと書き込み中も読み取りがブロックされない。
 */
final class PdoFactory
{
    public static function create(string $databasePath): PDO
    {
        $directory = \dirname($databasePath);
        if (!\is_dir($directory) && !@\mkdir($directory, 0o775, true) && !\is_dir($directory)) {
            throw new RuntimeException("Cannot create database directory: {$directory}");
        }

        $pdo = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // WAL は接続ごとではなく DB ファイルに永続する設定だが、新規
        // ファイルに対しては最初の接続で明示しないと DELETE のままになる。
        $pdo->exec('PRAGMA journal_mode = WAL');
        // WAL では NORMAL でも電源断以外のクラッシュに耐える。fsync が減る。
        $pdo->exec('PRAGMA synchronous = NORMAL');
        // 書き込みロック待ちで即座に SQLITE_BUSY を投げさせない。
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}

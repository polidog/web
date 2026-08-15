DROP TABLE "User";
CREATE TABLE "User" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "email" TEXT NOT NULL,
  "name" TEXT,
  "role" TEXT NOT NULL DEFAULT 'admin',
  "createdAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE ("email")
);

-- GitHub OAuth をやめ、管理者を「メールアドレス + パスワード」の 1 人に
-- 置き換える。User から githubId / login / avatarUrl が消え、email が入る。
--
-- SchemaDiff が出す ALTER 列（ADD COLUMN email → DROP COLUMN githubId …）は
-- そのままでは通らない。SQLite は UNIQUE 制約の付いた列を DROP COLUMN
-- できず `expressions prohibited in PRIMARY KEY and UNIQUE constraints` で
-- 落ちる。なのでテーブルごと作り直す。
--
-- 移す行は無い。GitHub でログインした人が居らず User は 0 行で、
-- Post.authorId も全行 NULL なので、DROP しても外部キーは壊れない。
--
-- 説明を文の「前」ではなく末尾に置いているのは Migrator::runStatements() の
-- 都合。`--` で始まる文は丸ごと読み飛ばされるので、文の頭にコメントを置くと
-- その文が音も無く消える（tehilim/migrations/*_indexes がこれで 3 本落ちた）。

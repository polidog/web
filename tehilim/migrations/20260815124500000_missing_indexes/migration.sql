CREATE INDEX IF NOT EXISTS "idx_post_listing" ON "Post" ("kind", "status", "publishedAt" DESC);
CREATE INDEX IF NOT EXISTS "idx_post_updated" ON "Post" ("updatedAt" DESC);
CREATE INDEX IF NOT EXISTS "idx_posttotag_tag" ON "_PostToTag" ("B");
CREATE INDEX IF NOT EXISTS "idx_categorytopost_post" ON "_CategoryToPost" ("B");

-- 20260814081700000_indexes で足したはずのインデックスを実際に作り直す。
-- あちらは 4 本のうち 3 本が DB に届いていなかった —— Migrator::runStatements()
-- は `--` で始まる文を丸ごと読み飛ばすので、各 CREATE INDEX の直前に置いた
-- 説明コメントが、その CREATE INDEX ごと文を飲み込んでいた
-- （生き残ったのは、直前にコメントが無かった idx_categorytopost_post だけ）。
--
-- 元のファイルは書き換えない。適用済みとして記録されており、書き換えても
-- 再実行されないうえ、初期構築時の記録としては当時のままが正しい。
-- IF NOT EXISTS なので、この移行は何度当てても、どの環境でも安全に通る。

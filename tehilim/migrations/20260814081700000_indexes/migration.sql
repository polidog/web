-- tehilim のスキーマ言語には @@index が無いので、インデックスだけ手書きで足す。
-- SchemaDiff が見るのは unique 制約と外部キーなので、ここで足したぶんを
-- 次の `migrate dev` が消しに来ることはない。

-- 一覧（kind + status で絞って publishedAt 降順）。トップ・タグ・
-- カテゴリ・アーカイブが全部この形。
CREATE INDEX IF NOT EXISTS "idx_post_listing" ON "Post" ("kind", "status", "publishedAt" DESC);

-- 管理画面の一覧は下書きも混ぜて更新順に並べる。
CREATE INDEX IF NOT EXISTS "idx_post_updated" ON "Post" ("updatedAt" DESC);

-- join テーブルの PK は (A, B) なので前方の列しか引けない。
-- 逆方向（タグ → 記事、記事 → カテゴリ）用に後方の列を足す。
CREATE INDEX IF NOT EXISTS "idx_posttotag_tag" ON "_PostToTag" ("B");
CREATE INDEX IF NOT EXISTS "idx_categorytopost_post" ON "_CategoryToPost" ("B");

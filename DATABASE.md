# Database Setup - Drizzle ORM + Turso

このプロジェクトでは、Drizzle ORMとTurso (libSQL) を使用してデータベースを管理しています。

## 📦 インストール済みパッケージ

- `drizzle-orm` - TypeScript ORM
- `drizzle-kit` - マイグレーション管理ツール
- `@libsql/client` - Turso/libSQLクライアント
- `dotenv` - 環境変数管理

## 🗄️ データベース情報

- **データベース名**: `web-drizzle-db`
- **ロケーション**: AWS ap-northeast-1 (東京)
- **接続URL**: `.env.local` に保存

## 📁 ファイル構成

```
src/db/
  ├── index.ts          # データベースクライアント初期化
  └── schema.ts         # テーブルスキーマ定義

drizzle/                # マイグレーションファイル
drizzle.config.ts       # Drizzle Kit設定
.env.local              # 接続情報 (Git管理外)
.env.example            # 環境変数テンプレート
```

## 🛠️ 利用可能なコマンド

### 開発時

```bash
# スキーマからマイグレーションファイル生成
pnpm db:generate

# マイグレーション適用
pnpm db:migrate

# スキーマを直接データベースにプッシュ (開発時のみ推奨)
pnpm db:push

# Drizzle Studio起動 (データベースGUI)
pnpm db:studio
```

### Turso CLI

```bash
# データベース一覧
turso db list

# データベース情報確認
turso db show web-drizzle-db

# SQLシェル起動
turso db shell web-drizzle-db

# 新しい認証トークン生成
turso db tokens create web-drizzle-db
```

## 📐 スキーマ定義例

現在の `users` テーブル:

```typescript
export const users = sqliteTable("users", {
  id: integer("id", { mode: "number" }).primaryKey({ autoIncrement: true }),
  name: text("name").notNull(),
  email: text("email").notNull().unique(),
  createdAt: integer("created_at", { mode: "timestamp" })
    .notNull()
    .default(sql\`(unixepoch())\`),
  updatedAt: integer("updated_at", { mode: "timestamp" })
    .notNull()
    .default(sql\`(unixepoch())\`)
    .$onUpdate(() => new Date()),
});

export type User = typeof users.$inferSelect;
export type NewUser = typeof users.$inferInsert;
```

## 🚀 使い方

### 1. データベースクライアントのインポート

```typescript
import { db } from "@/db";
import { users } from "@/db/schema";
```

### 2. クエリの実行

#### 全件取得

```typescript
const allUsers = await db.select().from(users);
```

#### 条件付き取得

```typescript
import { eq } from "drizzle-orm";

const user = await db.select().from(users).where(eq(users.id, 1));
```

#### 挿入

```typescript
const [newUser] = await db
  .insert(users)
  .values({ name: "John Doe", email: "john@example.com" })
  .returning();
```

#### 更新

```typescript
await db
  .update(users)
  .set({ name: "Jane Doe" })
  .where(eq(users.id, 1));
```

#### 削除

```typescript
await db.delete(users).where(eq(users.id, 1));
```

## 🧪 テスト用API

`src/app/api/users/route.ts` にサンプルAPIを実装済み:

```bash
# ユーザー作成
curl -X POST http://localhost:3000/api/users \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com"}'

# 全ユーザー取得
curl http://localhost:3000/api/users

# ユーザー削除
curl -X DELETE "http://localhost:3000/api/users?id=1"
```

## 📖 参考リンク

- [Drizzle ORM ドキュメント](https://orm.drizzle.team/docs/overview)
- [Turso ドキュメント](https://docs.turso.tech/)
- [Drizzle + Turso チュートリアル](https://orm.drizzle.team/docs/tutorials/drizzle-with-turso)

## ⚠️ 注意事項

1. `.env.local` は絶対にGitにコミットしないこと
2. 本番環境では `db:push` ではなく `db:generate` + `db:migrate` を使用すること
3. スキーマ変更時は必ずマイグレーションを生成すること
4. Turso の認証トークンは定期的にローテーションすることを推奨

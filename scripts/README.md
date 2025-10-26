# Import Scripts

## Hugo Blog Import

HugoサイトからNext.jsブログへ記事をインポートするスクリプトです。

### 前提条件

- Hugo Webサイトが `../website` に配置されている
- データベースマイグレーションが完了している
- 画像ファイルが `public/images` にコピーされている

### 使用方法

#### 1. ユーザーを作成する

まずブログ記事の著者となるユーザーを作成します:

```bash
pnpm tsx scripts/create-user.ts
```

プロンプトに従って名前とメールアドレスを入力してください。
作成されたUser IDをメモしておきます。

#### 2. Hugo記事をインポートする

```bash
pnpm tsx scripts/import-hugo.ts
```

プロンプトでUser IDの入力を求められるので、手順1で作成したUser IDを入力します。

### インポート内容

- **記事数**: 約1,270件 (2004年〜2024年)
- **メタデータ**: title, date, categories, tags, image
- **ステータス**: すべて `published` として登録
- **slug**: ファイルパスから自動生成 (例: `2024-01-haskell`)

### インポート後の確認

データベースを確認:

```bash
sqlite3 local.db "SELECT COUNT(*) FROM posts"
sqlite3 local.db "SELECT COUNT(*) FROM categories"
sqlite3 local.db "SELECT COUNT(*) FROM tags"
```

サンプル記事を確認:

```bash
sqlite3 local.db "SELECT id, title, slug, status FROM posts LIMIT 5"
```

### トラブルシューティング

#### ユーザーが作成できない

- データベースが正しく初期化されているか確認
- `local.db` ファイルが存在するか確認

#### インポートが失敗する

- Hugo content pathが正しいか確認: `../website/content/blog`
- ファイルの読み取り権限があるか確認
- エラーログを確認して該当ファイルをチェック

#### 重複エラー

すでにインポート済みの記事はスキップされます。
完全に再インポートする場合は、データベースをリセットしてください:

```bash
rm local.db
pnpm db:push
```

# 開発コマンド

## 基本コマンド

### 開発サーバー起動
```bash
npm run dev
```
- ローカル開発サーバーを起動（http://localhost:3000）

### プロダクションビルド
```bash
npm run build
```
- 最適化されたプロダクションビルドを作成

### プロダクションサーバー起動
```bash
npm start
```
- ビルド後のアプリケーションを起動（`npm run build`の後に実行）

### リント
```bash
npm run lint
```
- Biomeでコードをチェック

### フォーマット
```bash
npm run format
```
- Biomeでコードを自動フォーマット

## システムコマンド（Linux）

- `git`: バージョン管理
- `ls`: ファイル一覧
- `cd`: ディレクトリ移動
- `grep`: ファイル内検索
- `find`: ファイル検索
- `cat`: ファイル内容表示
- `mkdir`: ディレクトリ作成
- `rm`: ファイル/ディレクトリ削除
- `mv`: ファイル/ディレクトリ移動
- `cp`: ファイル/ディレクトリコピー

## パッケージマネージャー（pnpm）

```bash
pnpm install          # 依存パッケージのインストール
pnpm add <package>    # パッケージの追加
pnpm remove <package> # パッケージの削除
pnpm update           # パッケージの更新
```

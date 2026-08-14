# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 言語

**このリポジトリでのやり取りは日本語で行う。** 説明・コミットメッセージ・
PR 説明・コードコメントは日本語で書く。ただしコード内の識別子、技術用語、
CLI コマンド名はそのままの表記を保つ。RELAYER.md はフレームワーク同梱の
英語ドキュメントなので、翻訳せずそのまま参照すること。

## 最初に読むもの

これは [Relayer](https://github.com/polidog/relayer) アプリケーション。
コーディング規約の正典は **[RELAYER.md](./RELAYER.md)** にあり、
ページ・API ルート・ミドルウェア・アイランドを書く前に必ず読むこと。
RELAYER.md は `polidog/relayer` に同梱され、インストール済みフレームワークと
バージョンが一致するため、実際のランタイムと乖離しない。
このファイルと RELAYER.md が食い違う場合は RELAYER.md が優先される。

`.claude/skills/relayer-routing/`（ルーティング作業のレシピ）と
`.claude/agents/relayer-reviewer.md`（規約レビュー用エージェント）も
フレームワークと co-versioned なので、手で編集しない。

## コマンド

```bash
composer install
php -S 127.0.0.1:8000 -t public     # → http://127.0.0.1:8000
docker compose up --build           # → http://localhost:8000（ホストに PHP 不要）
```

PHP 8.5 は `mise.toml` で固定。実行イメージは FrankenPHP（`Dockerfile`）で、
`php.ini` は `conf.d` 経由で読み込まれる。

```bash
vendor/bin/relayer routes            # src/Pages 配下で検出されたルート一覧 — これが正
vendor/bin/relayer routes:compile    # ルートマップの事前コンパイル（デプロイ時）
vendor/bin/relayer container:compile # DI コンテナを PHP にダンプ（デプロイ時）
vendor/bin/relayer upgrade           # プロジェクト構造をフレームワークのバージョンへ移行
vendor/bin/relayer profiler:clear    # var/cache/profiler を削除
vendor/bin/usephp compile src/Pages  # .psx → コンパイル済み PHP（デプロイ時）
vendor/bin/yaml-lint config/services.yaml
```

このプロジェクトにはテストスイート・linter・Node/ビルドステップは存在しない。
RELAYER.md の方針どおり、安易に追加しないこと。

## アーキテクチャ

単一エントリポイント `public/index.php` が
`Relayer::boot(projectRoot, new App\AppConfigurator(projectRoot))->run()`
を呼ぶだけで、残りはすべて規約で解決される。

- **ルーティング** は `src/Pages/` のファイルベース（Next.js App Router 風）。
  編集すべきルート定義表は存在せず、ファイルを作ること自体がルート追加になる。
  ディレクトリ構成から推測せず、`vendor/bin/relayer routes` で確認する。
- **ビュー** は `.psx`（JSX 風構文の PHP）で、`polidog/usephp` が
  `var/cache/psx/` 配下の素の PHP にコンパイルする。`APP_ENV=dev` では
  リクエストごとに、本番では事前にコンパイルされる。`page.psx` はクロージャか
  `PageComponent` を返し、`layout.psx` は `LayoutComponent` を宣言して
  （`src/Pages/layout.psx` → `App\Layouts\RootLayout`）配下のページを包む。
- **DI** は Symfony DependencyInjection で、`autowire`/`public` がデフォルト。
  登録場所は自動読み込みされる 2 箇所 — `config/services.yaml`（宣言的）と
  `App\AppConfigurator::configure()`（プログラム的）。ページやハンドラの引数は
  **型で** autowire される。ページが superglobals に触らないのはこのため。
- **PSR-4**: `App\` → `src/`。ただし `src/Pages/` 配下はオートロード対象の
  クラスではなく、ルーターが探索して評価するファイル。

### 環境変数

`.env` がモード切り替えを担う。`APP_ENV=dev` は `.psx` のオンザフライ
コンパイル、リクエストプロファイリング（`var/cache/profiler/`）、
トレース可能なデコレータを有効にする。それ以外の値（または未設定）は本番扱い。
`DATABASE_DSN`（と `DATABASE_USER`/`DATABASE_PASSWORD`）を設定すると Db 層が
auto-wire される — `Polidog\Relayer\Db\Database` を型ヒントする。DSN は
プレースホルダ展開なしで PDO にそのまま渡されるため、SQLite は絶対パスが必要。

### デプロイ

各事前コンパイルは presence-gated（成果物が無ければ壊れるのではなく
ライブパスに縮退する）:

```bash
composer install --no-dev --classmap-authoritative
vendor/bin/usephp compile src/Pages
vendor/bin/relayer routes:compile
vendor/bin/relayer container:compile
```

あわせて OPcache の `validate_timestamps=0`（`php.ini` の production ブロックに
記載あり）を設定し、スナップショット状態をシリアライズするページがあるなら
`USEPHP_SNAPSHOT_SECRET` も設定する。

## 落とし穴

- `composer.json` の `extra.relayer.structure_version` は
  `relayer init`/`upgrade` が管理する。手で編集しない。
- `var/cache/`（psx・profiler）は生成物。古いものは `relayer profiler:clear`
  かディレクトリ削除でクリアする。
- `AGENTS.md` とこのファイルは `relayer init` が生成したポインタなので、
  `relayer upgrade` で上書きされる可能性がある。残したい知識は
  それに耐える場所にも置いておくこと。

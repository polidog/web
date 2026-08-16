# polidog.jp

2004 年から Hugo で運用してきた [polidog.jp](https://polidog.jp) を、
[Relayer](https://github.com/polidog/relayer) 製の CMS に置き換えたもの。

**動的に生成して CDN に持たせ、更新したときだけ捨てる** 構成にしてある。
Cloudflare のエッジでヒットしているあいだオリジンには 1 リクエストも来ないので、
静的サイトに近いランニングコストのまま、ブラウザから記事を書けるようになる。

```
[Browser] → [Cloudflare]  → [fly.io: FrankenPHP 1 台]
                ↑ HIT         ├ SQLite  /data/cms.db
                              └ 画像    /data/uploads
記事を保存 → ETag を更新 → 記事 URL・トップ・RSS を purge
```

既存 URL は 1 本も変えていない。`bin/verify-urls.php` が Hugo のビルド成果物
（20 年ぶんの URL）を実際に叩いて、それを毎回確かめる。

## 開発

```bash
composer install
npm install                       # Tailwind をビルドするときだけ
npm run build                     # public/assets/style.css を生成

vendor/bin/tehilim migrate deploy # var/cms.db を作る
php bin/import-hugo.php --content=../website/content   # 記事を取り込む

composer serve                    # → http://127.0.0.1:8000（CSS の watch 付き）
```

管理画面は `/admin`。ログインはメールアドレスとパスワードで、入れるのは
その 1 組を知っている 1 人だけ。`.gitignore` 済みの `.env.local` に
`ADMIN_EMAIL` と `ADMIN_PASSWORD_HASH` を置く（どちらかが空なら誰も入れない）。

```bash
php bin/hash-password.php    # 平文を訊いて ADMIN_PASSWORD_HASH=... を出す
```

ハッシュは `$` を含むので、貼るときはシングルクォートを外さないこと
（シェルと Dotenv の変数展開に食われる）。

本番と同じ経路（FrankenPHP + Caddy + volume）で動かしたいときは
`docker compose up --build`（→ http://localhost:8080）。

## Claude から書く（MCP コネクタ）

このサイト自身が **remote MCP サーバー**になっているので、Claude の
「設定 > コネクタ > カスタムコネクタを追加」に次の URL を入れると、
会話から記事を書いたり消したりできる。

```
https://polidog.jp/mcp
```

OAuth の入力欄は空のままでよい（クライアント登録は自動で行われる）。
追加すると管理者ログインと同意画面が出て、そこで許可した相手だけが
繋がる。使えるツールは 10 個:

| ツール | できること |
| --- | --- |
| `list_posts` / `get_post` | 記事の一覧・取得（下書きも含む） |
| `create_post` / `update_post` | 作成・更新（`update_post` は渡した項目だけ変える） |
| `publish_post` / `unpublish_post` | 公開・下書きに戻す |
| `delete_post` | 削除。`confirm_path` に path を渡さないと消えない |
| `list_tags` / `list_media` | 既存のタグ・画像を調べる |
| `upload_media_from_url` | 画像を URL から取り込んで `/images/...` にする |

書き込みは管理画面とまったく同じ経路（`PostFormMapper` → `PostWriter`）を
通るので、Markdown の変換・ETag の更新・Cloudflare の purge も同じように走る。

認証は polidog.jp 自身が OAuth 2.1 の認可サーバーになって処理している
（`/.well-known/*`・`/oauth/*`）。アクセストークンは 1 時間、リフレッシュは
使うたびに入れ替わる。設計の詳細は [CLAUDE.md](./CLAUDE.md) を参照。

## 移行

```bash
php bin/import-hugo.php --dry-run   # 件数と問題だけ見る
php bin/import-hugo.php             # 取り込む（記事・画像・ETag）
php bin/verify-urls.php --base=http://127.0.0.1:8000   # URL が壊れていないか
```

`verify-urls.php` は Hugo の `public/` にある全ページを叩き、DB の状態と
突き合わせる。手元の `public/` は `--buildDrafts` 付きでビルドされていて
本番に無いページも含むので、「下書きなら 404 が正解」「削除済み記事なら
404 が正解」として判定する。**期待と違うものが 1 件でもあれば失敗**（終了
コード 1）。

## デプロイ（fly.io）

```bash
fly volumes create polidog_data --region nrt --size 3
fly secrets set ADMIN_EMAIL=you@example.com \
                ADMIN_PASSWORD_HASH='$2y$12$...' \
                CLOUDFLARE_ZONE_ID=... CLOUDFLARE_API_TOKEN=... \
                USEPHP_SNAPSHOT_SECRET="$(openssl rand -hex 32)"
fly deploy
fly scale count 1        # SQLite の書き手を 1 つに保つ
```

ローカルの `var/cms.db` と `var/uploads` を本番 volume に投入するには:

```bash
bin/push-local-data-to-fly.sh
```

このスクリプトは `fly.toml` の `app` を読み、`sqlite3 .backup` で固めた DB を
`/data/cms.db` に差し替える。既存の本番 DB と uploads は
`/data/*.bak.<timestamp>` として残す。

`main` に push すると GitHub Actions が `flyctl deploy --remote-only` を実行する。
初回だけ Fly の deploy token を作り、GitHub の repository secret に
`FLY_API_TOKEN` として登録しておく:

```bash
fly tokens create deploy -a web-fzmoua -x 720h
gh secret set FLY_API_TOKEN
```

`app not found` で失敗する場合は、`FLY_API_TOKEN` が `web-fzmoua` 用ではないか、
期限切れ・別アカウント/別organizationの token になっている。token を作り直して
同じ secret 名で上書きする。

`.github/workflows/fly-deploy.yml` には `workflow_dispatch` もあるので、
GitHub の Actions 画面から手動デプロイもできる。SQLite volume は 1 台運用なので、
ワークフロー側でも同時デプロイは直列化している。

ビルド時に Tailwind の CSS、`.psx` のコンパイル、ルートマップ、DI コンテナを
全部作ってから配る。起動時には `docker-entrypoint.sh` が volume を初期化し、
`tehilim migrate deploy` を流す。

初回デプロイ後、または DB を入れ替えたあとは ETag を作り直す:

```bash
fly ssh console -C "php /app/bin/refresh-caches.php"
```

### Cloudflare 側の設定

無料プランは既定で HTML をキャッシュしないので、**Cache Rules で明示する**
必要がある。これをやらないと CDN 前提のコスト構造が成立しない。

1. **Cache Rules** に「Eligible for cache」のルールを追加
   - 対象: `(not starts_with(http.request.uri.path, "/admin"))`
   - Cache eligibility: *Eligible for cache*
   - Edge TTL: *Use cache-control header if present*（アプリの `s-maxage` に従わせる）
2. `/admin/*` には別ルールで *Bypass cache* を設定
3. purge 用の API トークンを発行し（Zone → Cache Purge の権限）、
   `CLOUDFLARE_ZONE_ID` と `CLOUDFLARE_API_TOKEN` を fly secrets に入れる

トークンを設定しないあいだ purge は no-op になる（`s-maxage` が切れるまで
古い内容が残るだけで、壊れはしない）。

## 構成

| 場所 | 役割 |
| --- | --- |
| `src/Pages/(site)/` | 公開側。`(site)` はルートグループなので URL には出ない |
| `src/Pages/admin/` | 管理画面 |
| `src/Pages/mcp/`・`oauth/`・`.well-known/` | Claude コネクタ（MCP と OAuth） |
| `src/Mcp/` | MCP のプロトコル層とツール |
| `src/Auth/Oauth/` | OAuth 2.1 認可サーバー |
| `src/Service/` | リポジトリ・保存・Markdown・purge |
| `src/Support/` | 値オブジェクトと純粋関数（キャッシュ方針・slug・HTML 変換） |
| `src/View/` | ページ間で共用する表示部品 |
| `src/Tehilim/` | スキーマからの生成物。手で編集しない |
| `tehilim/` | スキーマとマイグレーション |
| `bin/` | 移行・検証・キャッシュ再生成 |

設計判断の理由と落とし穴は [CLAUDE.md](./CLAUDE.md) に、フレームワークの
規約は [RELAYER.md](./RELAYER.md) にある。

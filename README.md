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
| `src/Service/` | リポジトリ・保存・Markdown・purge |
| `src/Support/` | 値オブジェクトと純粋関数（キャッシュ方針・slug・HTML 変換） |
| `src/View/` | ページ間で共用する表示部品 |
| `src/Tehilim/` | スキーマからの生成物。手で編集しない |
| `tehilim/` | スキーマとマイグレーション |
| `bin/` | 移行・検証・キャッシュ再生成 |

設計判断の理由と落とし穴は [CLAUDE.md](./CLAUDE.md) に、フレームワークの
規約は [RELAYER.md](./RELAYER.md) にある。

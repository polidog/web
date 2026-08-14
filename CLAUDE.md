# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 言語

**このリポジトリでのやり取りは日本語で行う。** 説明・コミットメッセージ・
PR 説明・コードコメントは日本語で書く。ただしコード内の識別子、技術用語、
CLI コマンド名はそのままの表記を保つ。RELAYER.md はフレームワーク同梱の
英語ドキュメントなので、翻訳せずそのまま参照すること。

## 最初に読むもの

これは [Relayer](https://github.com/polidog/relayer) アプリケーションで、
**polidog.jp の CMS** 。コーディング規約の正典は
**[RELAYER.md](./RELAYER.md)** にあり、ページ・API ルート・ミドルウェア・
アイランドを書く前に必ず読むこと。RELAYER.md は `polidog/relayer` に同梱され、
インストール済みフレームワークとバージョンが一致するため、実際のランタイムと
乖離しない。このファイルと RELAYER.md が食い違う場合は RELAYER.md が優先される
（ただし後述の「RELAYER.md からあえて外している点」は例外）。

`.claude/skills/relayer-routing/`（ルーティング作業のレシピ）と
`.claude/agents/relayer-reviewer.md`（規約レビュー用エージェント）も
フレームワークと co-versioned なので、手で編集しない。

## コマンド

```bash
composer install
npm install                          # Tailwind のみ（ランタイムには不要）
composer serve                       # → http://127.0.0.1:8000
                                     # bin/dev.sh 経由で Tailwind の watch も一緒に動く。
                                     # .psx にクラスを足したら CSS が自動で焼き直される
                                     # （手で npm run build を回すと忘れて「HTML には
                                     # 出ているのに見えない」事故になる）
composer serve 127.0.0.1:8001        # ポートを変えたいとき
docker compose up --build            # → http://localhost:8080（本番と同じ経路）
```

```bash
vendor/bin/relayer routes            # src/Pages 配下で検出されたルート一覧 — これが正
vendor/bin/relayer routes:compile    # ルートマップの事前コンパイル（デプロイ時）
vendor/bin/relayer container:compile # DI コンテナを PHP にダンプ（デプロイ時）
vendor/bin/relayer profiler:clear    # var/cache/profiler を削除
vendor/bin/usephp compile src/Pages  # .psx → コンパイル済み PHP（デプロイ時）
vendor/bin/tehilim generate          # スキーマ → 型付きクライアント（src/Tehilim）
vendor/bin/tehilim migrate dev --name <slug>   # スキーマ差分からマイグレーション作成
vendor/bin/tehilim migrate deploy    # 未適用のマイグレーションを適用
vendor/bin/yaml-lint config/services.yaml
composer stan                        # PHPStan（level 8）。エラー 0 が既定の状態
```

```bash
php bin/import-hugo.php --dry-run    # Hugo からの取り込み（下見）
php bin/import-hugo.php              # 実行
php bin/verify-urls.php --base=http://127.0.0.1:8000   # 既存 URL が壊れていないか
```

PHP 8.5 は `mise.toml` で固定。実行イメージは FrankenPHP（`Dockerfile`）。
自動テストは無い。代わりに **`bin/verify-urls.php` が回帰テストの役割**を持つ
（20 年ぶんの URL を実際に叩いて期待どおりか見る）。ルーティングや URL 生成を
触ったら必ず流すこと。

`composer stan` は level 8 でエラー 0 の状態を保っている。**ただし `.psx` は
検査されない** —— 独自構文で PHP パーサが読めず、PHPStan は既定で `.php` しか
見ないため、`src/Pages/` からは同居する `route.php` / `middleware.php` だけが
対象になる。ページの中身が守られるのは `src/Service/` と `src/View/` の
シグネチャまでで、そこから先は型が効かない。だから境界の型を緩めないこと。

## アーキテクチャ

単一エントリポイント `public/index.php` が `Relayer::boot()` を呼び、
既定の Document を `App\Http\SiteDocument` に差し替えてから `run()` する。

- **ルーティング** は `src/Pages/` のファイルベース。編集すべきルート定義表は
  存在せず、ファイルを作ること自体がルート追加になる。ディレクトリ構成から
  推測せず、`vendor/bin/relayer routes` で確認する。
  公開側は `(site)` ルートグループに入っている（グループは URL セグメントを
  増やさない）。これは管理画面 `/admin` と外殻レイアウトを分けるため。
- **ビュー** は `.psx`（JSX 風構文の PHP）。`APP_ENV=dev` ではリクエストごとに、
  本番では事前にコンパイルされる。共通部品は `src/View/` に素の PHP で置く
  （`usephp compile` が見るのは `src/Pages/` だけなので、その外の `.psx` は
  オートロードに乗らない）。
- **DB** は SQLite + [tehilim](https://github.com/polidog/tehilim)。
  スキーマは `tehilim/schema.tehilim`、生成物は `src/Tehilim/`（手で編集しない）。
- **DI** は Symfony DependencyInjection。`config/services.yaml` が正。

### 読みは SQL、書きは tehilim

`App\Service\PostRepository`（読み）だけ素の PDO で生 SQL を書き、
`App\Service\PostWriter`（書き）は tehilim を使う。書き込み側はタグの張り替えが
`set` 一発で済むので tehilim のほうが素直。この住み分けは意図的で、混ぜないこと。

読み側が tehilim に乗れないのは 3 か所だけだが、その 3 か所が主要導線にある:

- **タグ・カテゴリ別の一覧**（`listByTag` / `listByCategory`）。tehilim の
  `where` が解釈するのはスカラー演算子と AND/OR/NOT だけで
  （`Query\WhereCompiler`）、Prisma の `tags: {some: {slug: ...}}` にあたる
  **リレーションフィルタが無い**ため `post.findMany()` 側から絞れない。
  逆にタグ側から `include` すると今度は `include` が **orderBy を受け付けない**
  ので「新しい順に 25 件」が組めない。両方向とも塞がっている。
- **`terms()`** の `GROUP BY … HAVING COUNT(*) > 0`。`count()` 以外の集約 API が無い。
- **`listForAdmin`** の `ORDER BY COALESCE(publishedAt, updatedAt)`。
  `orderBy` は「カラム => 方向」しか取れず式が書けない。

残りは tehilim でも書けるが、上の 3 つがある限り 1 クラスに 2 つの流儀を
混ぜる意味が無いので、読みは PDO で揃えてある。

### 生 SQL の行に型を与えるのは PostRepository の中

PDO は列の型を知らせてこないので、生 SQL の結果は放っておくと `mixed` のまま
`.psx` まで流れる（そして `.psx` は PHPStan の検査対象外）。そこで
`PostRepository` は SELECT ごとの形を `@phpstan-type`（`PostRow`・`PostListRow`
など）で宣言し、`fetchAll()` / `fetchOne()` に渡すマッパで実際にその形へ
組み直している。**宣言だけ足してマッパを通さないと、静的にも実行時にも何も
保証されない**ので、SELECT の列を増減したら両方直すこと。

日時列が `\DateTimeImmutable` ではなく `string` なのは SQLite の TEXT が
そのまま返るため。tehilim の `PostRowScalar`（`src/Tehilim/Model/Post.php`）は
そこが `\DateTimeImmutable` で **shape が違う**ので、import して流用しないこと。

### 保存は必ず PostWriter を通す

記事の保存には 3 つの副作用が伴う——Markdown のレンダリング、ETag の更新、
Cloudflare の purge。どれか 1 つ欠けるとキャッシュが古いまま残るので、
`PostWriter::save()` を唯一の入口にしている。管理画面も移行スクリプトも
`App\Support\PostInput` を組み立ててここに渡す。

### キャッシュ（このサイトのコスト構造）

```
[Browser] → [Cloudflare] → [fly.io: FrankenPHP + SQLite]
                ↑ HIT はここで返る
```

方針は `App\Support\PageCache` に集約してある。要点:

- ブラウザには持たせない（`max-age=0`）。持たせると purge しても直らない。
- **記事詳細**は `s-maxage` 1 週間 + 保存時に purge。更新契機が「その記事を
  保存したとき」だけなので、これで常に正しい。
- **一覧・タグ・アーカイブ**は 5 分。記事を 1 本足すと 53 ページぶんの
  ページングが全部ずれ、purge の URL を数え上げるのが割に合わないため。
- `Cache(etagKey: ...)` を宣言すると、フレームワークが**ページを組み立てる前に**
  `EtagStore` を引いて 304 で短絡する（DB に触れない）。だから
  **重い処理は必ず内側の render closure に置く**。外側の factory は
  キャッシュ宣言とパラメータ参照だけ。

### URL は 1 本も変えない

Hugo 時代の URL がそのまま生きている。特に効いてくる事実:

- 記事の URL は `config/_default/permalinks.toml` の
  `blog = "/:year/:month/:day/:filename/"` で決まっていた。**ディレクトリ構造
  ではなく front matter の date から作られる**（`content/blog/2024/12/x.md` は
  `/2024/12/28/x/`）。だから記事ルートは `src/Pages/(site)/[year]/[month]/[day]/[slug]/`。
- 日付から URL ができる以上、**タイムゾーンがずれると URL が変わる**。
  JST 固定（`SiteConfig::TIMEZONE`）で、各エントリポイントが起動直後に
  `date_default_timezone_set()` する。
- 動的セグメントの値は **URL エンコードされたまま**渡ってくる。日本語スラッグの
  記事が大量にあるので、DB を引く前に必ず `rawurldecode()`。
- タグの slug は Hugo の `urlize` を再現している（`App\Support\HugoSlug`）。
  `.comマスター` や `--with-expatbuiltin` のような slug が実在し、
  `php://input` はスラッシュを含んだまま `/tags/php/input/` になる。
  URL に埋めるときは `HugoSlug::toPath()`（セグメントごとに encode）。
- ルーターは末尾スラッシュを落として照合するので `/x` と `/x/` は同じページ。
  canonical はスラッシュ付き（Hugo と同じ形）で出す。

### 生 HTML を出す唯一の方法

usePHP の Renderer は children の文字列を**必ず**エスケープする。設計であって
設定ではないので、記事本文（レンダリング済み HTML）をそのまま出す口が無い。
`App\Support\HtmlToElement` が HTML5 パーサ（PHP 8.4+ の `Dom\HTMLDocument`）で
Element ツリーに組み直している。同じ理由で**ページ内にインライン `<script>` は
書けない**——JS は `public/assets/site.js` に置き、値は data 属性で渡す。

## 環境変数

`.env` がモード切り替えを担う。`APP_ENV=dev` は `.psx` のオンザフライ
コンパイルとリクエストプロファイリングを有効にする。それ以外は本番扱い。

| 変数 | 既定 | 用途 |
| --- | --- | --- |
| `DATABASE_PATH` | `var/cms.db` | SQLite の場所（fly では `/data/cms.db`） |
| `UPLOADS_DIR` | `var/uploads` | 画像。Caddy が `/images/*` を直接配信する |
| `ETAGS_DIR` | `var/cache/etags` | **volume に置くこと**。イメージ内だとデプロイで消え、304 が効かなくなる |
| `SITE_URL` | `https://polidog.jp` | canonical / RSS / OAuth の redirect_uri |
| `GITHUB_CLIENT_ID` / `_SECRET` | — | 管理画面のログイン |
| `GITHUB_ALLOWED_LOGINS` | — | 許可する GitHub アカウント（カンマ区切り）。**空なら誰も入れない** |
| `CLOUDFLARE_ZONE_ID` / `_API_TOKEN` | — | purge。未設定なら no-op |
| `DISQUS_SHORTNAME` | — | 空ならコメント欄を出さない |

`config/services.yaml` の `%env(default:app.empty:VAR)%` という書き方は、
`%env(default::VAR)%` が未設定時に **null** を返すのを避けるため（受け側は
「空文字なら無効」で揃えてある）。

## 落とし穴

- **`container:compile` は env をビルド時に焼き込む。** secret は必ず
  `%env(VAR)%` 経由で参照すること（`$_ENV[...]` の直読みは空文字が焼き付く）。
- **`Authenticator` は `UserProvider` がバインドされていないと DI に登録されない。**
  GitHub OAuth しか使わないが、`App\Auth\GithubUserProvider`（常に null を返す）を
  バインドしているのはこのため。消すと `#[Auth]` も `requireAuth()` も動かなくなる。
- **`PageContext::metadata()` は使わない。** 既定の `HtmlDocument` にしか届かず、
  このアプリは canonical のために `SiteDocument` に差し替えている。
  head の設定は `App\Service\PageMeta` を通す。
- **tehilim に `@@index` は無い。** インデックスは
  `tehilim/migrations/*_indexes/migration.sql` に手書きしてある。
- **アップロードで SVG は受けない**（`MediaStorage::ALLOWED`）。SVG は XML なので
  スクリプトを持てて、`/images/...` は管理画面と同じオリジンで配信される。
  Caddy 側でも画像パスに `Content-Security-Policy: default-src 'none'; sandbox`
  と `nosniff` を付けて二重に止めている。必要になったらサニタイザを通すか、
  別ドメインから配信すること。
- `composer.json` の `extra.relayer.structure_version` は
  `relayer init`/`upgrade` が管理する。手で編集しない。
- `var/cache/` は生成物。`AGENTS.md` とこのファイルは `relayer init` が生成した
  ポインタなので、`relayer upgrade` で上書きされる可能性がある。

## RELAYER.md からあえて外している点

RELAYER.md は「Node/ビルドステップを追加しない」「新しい Composer 依存を
追加しない」を掲げているが、この 2 つは意図的に破っている。どちらも
**ランタイムのリクエスト経路には乗らない**（CSS はビルド成果物、Markdown 変換は
保存時のみ）。

- **Tailwind CLI** — 既存サイトの見た目をそのまま引き継ぐため。Docker の
  ビルドステージだけで動き、実行イメージに Node は入らない。
- **league/commonmark** — Markdown パーサの自作は非現実的。変換結果は
  `Post.html` に保存するので、表示時のコストはゼロ。

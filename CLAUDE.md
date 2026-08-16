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

### 子に「配列と要素」を混ぜない

usePHP が平坦化するのは **子が配列 1 つだけ**のとき。

```php
<ul>{\array_map(...)}</ul>            // OK
<nav>{\array_map(...)}<a>…</a></nav>  // 落ちる
```

後者はネストした配列がそのままレンダラに渡り、`Element|string|null` を期待する
`FormActionTransformer` が TypeError を投げる（500 になるがスタックトレースは
ページ本文に出るので原因は見えにくい）。並べたいときは PHP 側で 1 本の配列に
均してから渡すこと（`RootLayout::desktopNav()` がその形）。

### 見た目の決まりごと

公開側はタイポグラフィ主導のミニマル。判断に迷ったらこの 4 つに従う。

- **罫線を引かない。** 区切りは余白と文字の濃さで付ける。`border-*` を足したく
  なったら、まず余白で足りないか確かめる。
- **色は `assets/tailwind.css` の CSS 変数**（`surface` / `raised` / `ink` /
  `muted` / `faint` / `accent`）。`.dark` が変数ごと差し替えるので、**ページに
  `dark:` を書かない**（例外はテーマトグルのアイコン差し替えだけ）。
  `faint` は年号のような**大きい数字専用**で 3:1 しかない。小さい字は `muted`。
- **等幅（`font-mono`）は時間と数にだけ**。日付・年号・件数・ページ番号がそれ
  （サイト名だけロゴとして例外）。タグ名や見出しには使わない。
- **幅は `max-w-measure`（44rem）で全ページ共通。** ページごとに変えない。

記事一覧は各行に年を書かず、**年が変わる位置にだけ年号を置く**
（`Components::postList()`）。20 年ぶんの記事が 1 本の URL 体系で繋がっている
ことがこのサイトの中身なので、それを装飾ではなく組版で示している。行の日付が
「月.日」で済むのはこの構造があるからで、年号を外すと日付の意味が壊れる。

日本語の Web フォントは数 MB になり、CDN キャッシュ前提のコスト構造と噛み合わ
ないので**書体はシステムスタック**。個性は級差・字間・行間で付けている。

### 管理画面も同じ言語で書く

`/admin` は公開側と**同じトークン**（`surface` / `raised` / `ink` / `muted` /
`faint` / `hairline` / `accent`）だけを使い、`dark:` は 1 つも書かない。
ロゴ・ナビ・日付・件数の組み方も公開側に揃えてある。素の Tailwind パレット
（`gray-*` / `sky-*` / `slate-*`）を持ち込まないこと —— 変数と併走させると
ダークモードの切り替え点が 2 系統に割れる。

公開側の規約からわざと外しているのは 3 点だけで、どれも理由がある:

- **入力欄には罫線を引く**（`border-hairline` 1 本まで）。当たり判定が
  見えないフォームは使えない。面で囲うのはエディタの本文とプレビューだけ。
- **本文欄は等幅。** 扱うのが Markdown のソースだから（公開側の「等幅は
  時間と数だけ」は組版の規則で、ソースコードはその外）。
- **幅は `max-w-[72rem]`。** エディタが 2 ペインに開くため。公開側の
  `max-w-measure` は読み物の幅なので、そのままでは足りない。

通知の赤と緑（`danger` / `success`）は管理画面専用のトークン。公開側には
出てこない。

### エディタ（`AdminComponents::editor()`）

サーバが出すのは素の `<form>` だけで、JS との接続は**すべて data 属性**
（`data-editor` / `data-editor-body` / `data-md` など）。挙動は
`public/assets/admin.js` にあり、読み込むのは `AdminLayout` だけなので
公開側のページには 1 バイトも増えない。

- **プレビューは `/admin/preview` に投げる。** ブラウザ側に Markdown
  パーサを置かない —— 保存時と変換結果がずれると「プレビューでは
  正しかったのに」が起きる。変換は常にサーバの `MarkdownRenderer` 1 本。
- **プレビューの開閉は `data-preview` 属性の書き換えでやる。** JS から
  クラスを足してはいけない。Tailwind は `src/**` しかスキャンしないので、
  JS の中にだけ現れるクラスは CSS に出力されず、無言で効かなくなる。
  切り替えの CSS は `assets/tailwind.css` の `.editor-panes`。
- **本文への挿入は `execCommand('insertText')` を通す。** deprecated だが、
  textarea の undo 履歴に残る挿入手段はこれしかない。`value` を直接
  書き換えると Ctrl+Z で戻せなくなる。

### Claude コネクタ（MCP + OAuth）

`/mcp` が remote MCP サーバー、`/oauth/*` と `/.well-known/*` がその認証。
Claude Chat の「カスタムコネクタ」に `https://polidog.jp/mcp` を入れると繋がる。

**認証を自前の OAuth 2.1 で組んでいる理由**は、Claude 側の選択肢がそれしか
無いから。固定トークンをヘッダで渡す方式（`static_headers`）もあるが、
ベータで段階展開中・early access 申請が要るので、アカウントによっては
設定欄自体が出ない。OAuth なら URL を貼るだけで確実に繋がる。

- **トランスポートはステートレス**（`Mcp-Session-Id` を使わない）。仕様は
  POST への応答を SSE ではなく単一 JSON で返すことを認めていて、Relayer の
  `Response` は本文が `?string` 1 つ ——ストリームを返す手段がそもそも無い。
  `GET /mcp` は 405 を返して SSE を提供しない。
- **未認証は必ず 401 + `WWW-Authenticate`。** 200 で `isError` を返すと
  クライアントは OAuth を始めず、「ログインしてください」という文字列が
  そのまま会話に流れて終わる。ここは仕様というより Claude の実装の都合。
- **ツールの失敗は `isError`、呼び方の誤りは JSON-RPC エラー。** 前者は
  モデルが読んで次の手を選び直せるが、後者にするとモデルは同じ呼び出しを
  繰り返す。
- **書き込みは `PostFormMapper` → `PostWriter` を素通しする。** 検証を
  自前で書くと「管理画面では弾かれるのに MCP からは通る」ができる。
  同じ理由で、**path を `normalizePath()` してから mapper に渡さない**
  （先に整えるとスラッシュ無しの path が検証をすり抜ける）。引き当てだけは
  正規化した形で行う。
- **`update_post` は既存値で埋めてから保存する。** `PostWriter::save()` は
  `PostInput` の中身で行を丸ごと上書きするので、渡されなかった項目を
  埋めずに通すと本文もタグも空になる。
- **`create_post` は path の重複を自分で見る。** `save()` は id 無しで
  呼ぶと path 一致の行を黙って上書きするため（`PostWriter.php:57-59`）、
  「新規作成」で既存記事が消える。
- **`delete_post` は `confirm_path` を必須にする。** id を 1 つ取り違えた
  だけで別の記事が消えるので、削除の前に必ず 1 度読ませる。
- **OAuth のテーブルだけは読み書きとも tehilim。** 「読みは生 SQL」の
  住み分けは PostRepository が tehilim で書けない 3 つに当たったからで、
  OAuth のクエリは一意キーの lookup と insert/delete しかなく、その理由が
  1 つも当てはまらない。
- **平文の秘密は保存しない。** 認可コードもトークンも SHA-256 だけを
  DB に入れる（DB ファイルは記事と同じ volume に載る）。
- **同意画面は hidden で値を持ち回り、POST 後にもう一度検証する。**
  usePHP の form action はページのパスだけでクエリを保持しないため。
  改竄されても検証を通らないので、hidden を信用したことにはならない。
- **検証に失敗した認可リクエストではリダイレクトしない。** その時点の
  `redirect_uri` はまだ信用できず、飛ばすとこの認可サーバー自体が
  オープンリダイレクタになる。エラーは同意画面に出して止める。
- **`/admin/login` は `/oauth/authorize` から来た人を戻せるようにしてある。**
  戻り先を URL で受け取ると任意の URL へ飛べる口ができるので、
  クエリだけをセッションに預け、戻り先は `/oauth/authorize` 固定。
- **取り込んだ画像の一時ファイルは `UPLOADS_DIR` の下に作る。**
  `MediaStorage::store()` は `rename()` で動かすが、rename はデバイスを
  またげない。本番の `/tmp` はイメージの中、uploads は volume の上なので、
  システムの一時ディレクトリを使うと**本番だけ**保存に失敗する。
- **画像取得の SSRF 対策は「解決した IP を見る」+「リダイレクトを自分で追う」。**
  curl に `FOLLOWLOCATION` を任せると、リダイレクト先が内部アドレスに
  化けても検査できない。解決済み IP は `CURLOPT_RESOLVE` で固定して、
  検査と接続の間に DNS の答えが変わる隙間を消してある。

## 環境変数

`.env` がモード切り替えを担う。`APP_ENV=dev` は `.psx` のオンザフライ
コンパイルとリクエストプロファイリングを有効にする。それ以外は本番扱い。

| 変数 | 既定 | 用途 |
| --- | --- | --- |
| `DATABASE_PATH` | `var/cms.db` | SQLite の場所（fly では `/data/cms.db`） |
| `UPLOADS_DIR` | `var/uploads` | 画像。Caddy が `/images/*` を直接配信する |
| `ETAGS_DIR` | `var/cache/etags` | **volume に置くこと**。イメージ内だとデプロイで消え、304 が効かなくなる |
| `SITE_URL` | `https://polidog.jp` | canonical / RSS の絶対 URL |
| `ADMIN_EMAIL` | — | 管理画面に入れる唯一のメールアドレス |
| `ADMIN_PASSWORD_HASH` | — | そのパスワードの `password_hash()` 出力。**どちらかが空なら誰も入れない** |
| `CLOUDFLARE_ZONE_ID` / `_API_TOKEN` | — | purge。未設定なら no-op |
| `DISQUS_SHORTNAME` | — | 空ならコメント欄を出さない |

`config/services.yaml` の `%env(default:app.empty:VAR)%` という書き方は、
`%env(default::VAR)%` が未設定時に **null** を返すのを避けるため（受け側は
「空文字なら無効」で揃えてある）。

`.env` は**コミットされる**。資格情報はそこに書かず、`.gitignore` 済みの
`.env.local`（Dotenv が `.env` の後に読む）か fly の secrets に置く。
`ADMIN_PASSWORD_HASH` は `$` を含むので、`.env.local` では**シングル
クォートで囲む** —— 二重引用符と無引用符の値は Dotenv が変数展開する。

## 落とし穴

- **`container:compile` は env をビルド時に焼き込む。** secret は必ず
  `%env(VAR)%` 経由で参照すること（`$_ENV[...]` の直読みは空文字が焼き付く）。
- **`Authenticator` は `UserProvider` がバインドされていないと DI に登録されない。**
  `App\Auth\AdminUserProvider` を `Polidog\Relayer\Auth\UserProvider` に
  alias してあるのがそれ。外すとログインだけでなく `#[Auth]` も
  `requireAuth()` も動かなくなる。
- **`route.php` には CSRF 検証が入らない。** 自動で守られるのは
  `$ctx->action()` のフォームだけ。副作用のある API を足すなら、
  `CsrfToken::validate()` を自分で呼ぶこと（`admin/media/upload/route.php`
  がその形で、トークンはエディタのフォームに埋まっているものを JS が
  そのまま送っている）。
- **`PageContext::metadata()` は使わない。** 既定の `HtmlDocument` にしか届かず、
  このアプリは canonical のために `SiteDocument` に差し替えている。
  head の設定は `App\Service\PageMeta` を通す。
- **tehilim に `@@index` は無い。** インデックスは
  `tehilim/migrations/*_indexes/migration.sql` に手書きしてある。
- **マイグレーションの SQL で、文の頭にコメントを置かない。**
  `Migrator::runStatements()` は `--` で始まる文を丸ごと読み飛ばす。
  文と文の間にコメントを書くと、それが次の文にくっつき、**その文が音も
  無く消える**（`_indexes` はこれで 4 本中 3 本が DB に届いておらず、
  `20260815124500000_missing_indexes` で入れ直した）。説明は文の中か
  ファイル末尾に置くこと。
- **`curl_close()` を呼ばない。** PHP 8.0 以降は効果が無く、8.5 で
  deprecated になった。呼ぶと警告がレスポンス本文の先頭に混ざり、
  **JSON が壊れて `headers already sent` まで出る**（`ImageFetcher` が
  これで一度壊れた）。ハンドルは参照が切れた時点で解放される。
- **`config/services.yaml` のディレクトリ丸ごと登録に値オブジェクトを
  巻き込まない。** `App\Auth\` や `App\Mcp\` の `resource:` は再帰的なので、
  コンストラクタに `string $clientId` を取るような値オブジェクトが
  混ざるとコンテナのコンパイルが落ち、**アプリごと起動しなくなる**。
  `exclude:` に並べること。
- **アップロードで SVG は受けない**（`MediaStorage::ALLOWED`）。SVG は XML なので
  スクリプトを持てて、`/images/...` は管理画面と同じオリジンで配信される。
  Caddy 側でも画像パスに `Content-Security-Policy: default-src 'none'; sandbox`
  と `nosniff` を付けて二重に止めている。必要になったらサニタイザを通すか、
  別ドメインから配信すること。
- **`og:image` に WebP / AVIF を使わない。** X(Twitter) のカードクローラは
  どちらも取得できず、**カードが丸ごと出なくなる**（画像だけ欠けるのではなく、
  リンクがただの URL テキストになる）。自動生成は PNG（`OgpImageGenerator`）、
  トップの手描き画像は JPEG（`public/assets/ogp/top.jpg`）。アイキャッチは
  WebP でもアップロードできてしまうので、`PageMeta::usableAsOgImage()` が
  形式を見て、読めない形式なら自動生成の PNG に差し替えている。
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

# polidog.jp — Relayer + FrankenPHP。
#
# 本番はビルド時に全部コンパイルしてから配る:
#   Tailwind の CSS / .psx → PHP / ルートマップ / DI コンテナ
# どれも presence-gated（成果物が無ければライブ経路に縮退する）なので、
# 1 つ欠けても壊れはしないが、揃えておけばリクエスト時の仕事はほぼゼロになる。

# --- フロントエンドのビルド -----------------------------------------------
# Node はここだけ。ランタイムイメージには 1 バイトも入らない。
FROM node:22-slim AS css

WORKDIR /build
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund

# Tailwind はクラス名をソースから拾うので、スキャン対象を渡す。
COPY tailwind.config.js ./
COPY assets ./assets
COPY src ./src
RUN npx tailwindcss -i ./assets/tailwind.css -o ./style.css --minify

# highlight.js は node_modules からの複製なので、Node がいるこのステージで
# 済ませる。生成先は /build/public/assets/hljs/。
COPY bin/build-hljs.sh ./bin/
RUN bash bin/build-hljs.sh

# --- アプリ ---------------------------------------------------------------
FROM dunglas/frankenphp:php8.5

# curl と pdo_sqlite はベースイメージで有効。zip は composer の dist 展開用。
# opcache は本番のリクエストコストを決めるので明示的に入れる。
RUN install-php-extensions zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 依存は別レイヤーに。アプリのコードを直しても再インストールされない。
# --no-scripts なのは、post-install の usephp アセット公開がアプリの
# ソースを必要とするため（次の install で実行される）。
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev \
    --no-scripts --no-autoloader

COPY . .
COPY --from=css /build/style.css /app/public/assets/style.css
COPY --from=css /build/public/assets/hljs /app/public/assets/hljs

RUN composer install --no-interaction --prefer-dist --no-dev \
    --classmap-authoritative

# 3 つの事前コンパイル。APP_ENV を渡さない（= 本番扱い）ので、
# ランタイムはここで作った成果物を読むだけになる。
# container:compile は env をビルド時に焼き込むが、config/services.yaml は
# secret をすべて %env(...)% 経由で参照しているのでランタイム解決される。
RUN vendor/bin/usephp compile src/Pages \
    && vendor/bin/relayer routes:compile \
    && vendor/bin/relayer container:compile

# php.ini は conf.d の最後に読ませて上書きする。Composer のビルドが
# 自前のメモリ上限で走れるよう、インストール後に置く。
COPY php.ini "$PHP_INI_DIR/conf.d/zz-relayer.ini"

COPY Caddyfile /etc/caddy/Caddyfile
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENV SERVER_NAME=:8080
EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

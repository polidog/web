#!/usr/bin/env bash
#
# 開発用サーバ。Tailwind の watch と PHP のビルトインサーバを同時に動かす。
# `composer serve` から呼ばれる。
#
# Composer の scripts は配列を順に同期実行するだけで並行実行できないので、
# 「両方立ち上げて、どちらかが死んだら畳む」ところだけシェルに持たせている。
#
#   bin/dev.sh                    # → http://127.0.0.1:8000
#   bin/dev.sh 127.0.0.1:8001     # ポートを変えたいとき
#
set -euo pipefail

cd "$(dirname "$0")/.."

addr="${1:-127.0.0.1:8000}"
watch_pid=''
server_pid=''

cleanup() {
    trap - EXIT INT TERM
    for pid in "$server_pid" "$watch_pid"; do
        if [ -n "$pid" ]; then
            kill "$pid" 2>/dev/null || true
            wait "$pid" 2>/dev/null || true
        fi
    done
}
trap cleanup EXIT INT TERM

# CSS はビルド成果物（git 管理外）なので、npm install していない環境も
# ありうる。そのときは watch を諦めてサーバだけ動かす。
#
# `--watch=always` なのは、Tailwind CLI が既定では標準入力の EOF を見て
# 終了するため。バックグラウンドに回すと stdin が閉じて即死し、
# 「サーバは動いているのに CSS だけ更新されない」という一番気づきにくい
# 壊れ方をする。
if [ -x node_modules/.bin/tailwindcss ]; then
    node_modules/.bin/tailwindcss \
        -i ./assets/tailwind.css \
        -o ./public/assets/style.css \
        --watch=always &
    watch_pid=$!
else
    echo "bin/dev.sh: node_modules が無いので Tailwind の watch は起動しません（npm install してください）" >&2
fi

php -S "$addr" -t public &
server_pid=$!

# 両方ともバックグラウンドに置いて wait で待つ。前景で php を走らせると
# シグナルを受けても php が終わるまで trap が動かず、watch が孤児として
# 残る。`wait -n` はどちらか片方が落ちた時点で返るので、CSS の watch が
# 死んだまま気づかず開発を続ける事故も防げる。
wait -n

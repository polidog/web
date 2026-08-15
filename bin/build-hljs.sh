#!/usr/bin/env bash
#
# highlight.js を public/assets/hljs/ に配置する。
#
# npm パッケージ本体（highlight.js）ではなく @highlightjs/cdn-assets を使う。
# 本体の es/core.js は CommonJS の lib/core.js を import しているのでブラウザ
# では動かず、バンドラが要る。cdn-assets の es/*.min.js は import 文を 1 つも
# 持たない自己完結の ESM なので、置くだけで <script type="module"> から読める。
# Tailwind 以外にビルドツールを増やさずに済む。
#
#   npm run build:hljs
#
set -euo pipefail

cd "$(dirname "$0")/.."

src='node_modules/@highlightjs/cdn-assets'
dest='public/assets/hljs'

if [ ! -d "$src" ]; then
    echo "bin/build-hljs.sh: $src が無い（npm install してください）" >&2
    exit 1
fi

# common ビルドに入っている 36 言語。記事の言語指定の 9 割はここで足りるうえ、
# 言語指定なしのブロックを autodetect にかける母集団でもある。
# 判定は「登録済みの言語だけ」を突き合わせるので、これを分割で遅延ロードすると
# 最初のブロックが判定できない。base は 1 ファイルのまま読む。
mkdir -p "$dest/languages"
cp "$src/es/highlight.min.js" "$dest/highlight.min.js"

# common に無いが記事に実在する言語。明示指定があったときだけ追加で読む。
# ここに無いもの（pug 9・vue 2・apex 2・fish 2・prisma 1）は highlight.js
# 自体が持っていないので、素のまま表示される。
# toml は common の ini がエイリアスとして拾うので足さなくてよい。
extra=(
    dockerfile   # 15 本
    twig         #  9 本
    nginx        #  3 本
    coffeescript #  2 本（記事側の指定は coffee）
    apache       #  1 本
    handlebars   #  1 本（記事側の指定は ejs）
)

for lang in "${extra[@]}"; do
    if [ -f "$src/es/languages/$lang.min.js" ]; then
        cp "$src/es/languages/$lang.min.js" "$dest/languages/$lang.min.js"
    else
        echo "bin/build-hljs.sh: $lang は highlight.js に無い（読み飛ばす）" >&2
    fi
done

echo "bin/build-hljs.sh: $dest に $(( ${#extra[@]} + 1 )) ファイルを配置しました"

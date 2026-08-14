#!/bin/sh
set -e

# volume の初期化。fly の volume は空のディスクとしてマウントされるので、
# 初回起動時にディレクトリを作る必要がある。
mkdir -p "${UPLOADS_DIR:-/data/uploads}/images" "${ETAGS_DIR:-/data/etags}"

# tehilim CLI は schema.tehilim の相対パス（../var/cms.db）でしか DB を
# 見つけられず、--url での上書きも持たない。シンボリックリンクを張って、
# CLI とアプリの両方が volume 上の同じファイルを指すようにする。
DB_PATH="${DATABASE_PATH:-/data/cms.db}"
mkdir -p /app/var
if [ ! -L /app/var/cms.db ]; then
    ln -sf "$DB_PATH" /app/var/cms.db
fi

# マイグレーションは起動時に走らせる。fly の release_command は volume を
# マウントしない別マシンで実行されるため、そちらでは DB に触れない。
vendor/bin/tehilim migrate deploy

exec "$@"

#!/usr/bin/env bash
#
# Push the local SQLite database and uploads directory into the Fly volume.
#
# This replaces the remote /data/cms.db with a sqlite3 online backup of
# var/cms.db. If var/uploads exists, it also replaces /data/uploads.
#
# Usage:
#   bin/push-local-data-to-fly.sh
#   bin/push-local-data-to-fly.sh --app web-fzmoua --yes
#
set -euo pipefail

cd "$(dirname "$0")/.."

app=''
db_path='var/cms.db'
uploads_path='var/uploads'
assume_yes=0

usage() {
    cat <<'TXT'
Usage: bin/push-local-data-to-fly.sh [options]

Options:
  --app APP       Fly app name. Defaults to app in fly.toml.
  --db PATH       Local SQLite DB path. Defaults to var/cms.db.
  --uploads PATH  Local uploads directory. Defaults to var/uploads.
  --yes           Skip confirmation prompt.
  -h, --help      Show this help.
TXT
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --app)
            app="${2:-}"
            shift 2
            ;;
        --db)
            db_path="${2:-}"
            shift 2
            ;;
        --uploads)
            uploads_path="${2:-}"
            shift 2
            ;;
        --yes)
            assume_yes=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [ -z "$app" ]; then
    app="$(
        sed -n "s/^[[:space:]]*app[[:space:]]*=[[:space:]]*['\"]\([^'\"]*\)['\"].*/\1/p" fly.toml |
            sed -n '1p'
    )"
fi

if [ -z "$app" ]; then
    echo "Fly app name was not found. Pass --app APP." >&2
    exit 1
fi

if [ ! -f "$db_path" ]; then
    echo "SQLite DB not found: $db_path" >&2
    exit 1
fi

for command in fly sqlite3; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "required command not found: $command" >&2
        exit 1
    fi
done

cat <<TXT
This will replace data on Fly.

  app     : $app
  db      : $db_path -> /data/cms.db
  uploads : $uploads_path -> /data/uploads

Remote backups will be kept as /data/cms.db.bak.<timestamp> and
/data/uploads.bak.<timestamp> when those paths already exist.
TXT

if [ "$assume_yes" -ne 1 ]; then
    printf 'Continue? [y/N] '
    read -r answer
    case "$answer" in
        y|Y|yes|YES)
            ;;
        *)
            echo "aborted"
            exit 1
            ;;
    esac
fi

tmpdir="$(mktemp -d)"
cleanup() {
    rm -rf "$tmpdir"
}
trap cleanup EXIT

echo "Creating SQLite backup..."
sqlite3 "$db_path" ".backup '$tmpdir/cms.db'"

remote_script="$tmpdir/replace-fly-data.sh"
cat >"$remote_script" <<'SH'
#!/bin/sh
set -eu

ts="$(date +%Y%m%d%H%M%S)"

if [ ! -f /data/cms.db.import ]; then
    echo "/data/cms.db.import does not exist" >&2
    exit 1
fi

if [ -f /data/cms.db ]; then
    mv /data/cms.db "/data/cms.db.bak.$ts"
fi
rm -f /data/cms.db-wal /data/cms.db-shm
mv /data/cms.db.import /data/cms.db

if [ -d /data/uploads.import ]; then
    if [ -d /data/uploads.import/uploads ] && [ ! -e /data/uploads.import/images ]; then
        mv /data/uploads.import/uploads /data/uploads.import.normalized
        rm -rf /data/uploads.import
        mv /data/uploads.import.normalized /data/uploads.import
    fi

    if [ -d /data/uploads ]; then
        mv /data/uploads "/data/uploads.bak.$ts"
    fi
    mv /data/uploads.import /data/uploads
fi

mkdir -p /data/uploads/images /data/etags

if [ -f /app/bin/refresh-caches.php ]; then
    php /app/bin/refresh-caches.php
fi
SH

echo "Uploading DB to $app..."
fly ssh console -a "$app" -C "sh -lc 'rm -f /data/cms.db.import && rm -rf /data/uploads.import'"
fly sftp put -a "$app" "$tmpdir/cms.db" /data/cms.db.import

if [ -d "$uploads_path" ]; then
    echo "Uploading uploads to $app..."
    fly sftp put -R -a "$app" "$uploads_path" /data/uploads.import
else
    echo "Uploads directory not found; skipping uploads: $uploads_path"
fi

echo "Installing remote replacement script..."
fly sftp put -a "$app" "$remote_script" /tmp/replace-fly-data.sh

echo "Replacing remote data..."
fly ssh console -a "$app" -C "sh /tmp/replace-fly-data.sh"

echo "Restarting app..."
fly machine restart -a "$app"

echo "Done."

#!/usr/bin/env bash
#
# Copy the development app tree into /usr/clearos/apps/openvpn_watchdog.
# Useful on a ClearOS test machine before building RPM.
#
# This version intentionally does NOT use rsync, because minimal ClearOS
# installations may not have rsync installed.

set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
DST_DIR="/usr/clearos/apps/openvpn_watchdog"
BACKUP_ROOT="/root/app-openvpn-watchdog-dev-backups"

die() {
    echo "❌ $*" >&2
    exit 1
}

copy_item() {
    local item="$1"

    if [ -e "$SRC_DIR/$item" ]; then
        echo "  → $item"
        cp -a "$SRC_DIR/$item" "$DST_DIR/"
    fi
}

if [ "$(id -u)" -ne 0 ]; then
    die "Запусти від root."
fi

command -v cp >/dev/null 2>&1 || die "cp не знайдено."
command -v find >/dev/null 2>&1 || die "find не знайдено."
command -v install >/dev/null 2>&1 || die "install не знайдено."

[ -d "$SRC_DIR/controllers" ] || die "Не знайдено $SRC_DIR/controllers"
[ -d "$SRC_DIR/libraries" ] || die "Не знайдено $SRC_DIR/libraries"
[ -f "$SRC_DIR/deploy/install" ] || die "Не знайдено $SRC_DIR/deploy/install"

case "$DST_DIR" in
    /usr/clearos/apps/openvpn_watchdog) ;;
    *) die "Небезпечний DST_DIR: $DST_DIR" ;;
esac

echo "============================================================"
echo " Встановлення app-openvpn-watchdog у ClearOS app tree"
echo "============================================================"
echo "SRC: $SRC_DIR"
echo "DST: $DST_DIR"
echo "============================================================"

if [ -d "$DST_DIR" ]; then
    TS="$(date +%Y%m%d-%H%M%S)"
    BACKUP_DIR="$BACKUP_ROOT/backup-$TS"
    echo "📦 Роблю backup існуючого app tree:"
    echo "   $BACKUP_DIR"

    install -d -m 0700 -o root -g root "$BACKUP_ROOT"
    cp -a "$DST_DIR" "$BACKUP_DIR"

    echo "🧹 Очищаю старий каталог app tree..."
    rm -rf "$DST_DIR"
fi

install -d -m 0755 -o root -g root "$DST_DIR"

echo "📁 Копіюю файли без rsync..."
copy_item "controllers"
copy_item "deploy"
copy_item "htdocs"
copy_item "language"
copy_item "libraries"
copy_item "views"
copy_item "packaging"
copy_item "README.md"
copy_item "install-app-tree-dev.sh"

echo "🔐 Нормалізую права..."
chown -R root:root "$DST_DIR"
find "$DST_DIR" -type d -exec chmod 0755 {} \;
find "$DST_DIR" -type f -exec chmod 0644 {} \;

chmod 0755 "$DST_DIR/deploy/install"
if [ -f "$DST_DIR/deploy/openvpn-watchdog-helper.sh" ]; then
    chmod 0755 "$DST_DIR/deploy/openvpn-watchdog-helper.sh"
fi
if [ -f "$DST_DIR/packaging/build-rpm.sh" ]; then
    chmod 0755 "$DST_DIR/packaging/build-rpm.sh"
fi
chmod 0755 "$DST_DIR/install-app-tree-dev.sh" 2>/dev/null || true

echo "⚙️ Запускаю deploy/install..."
/bin/sh "$DST_DIR/deploy/install"

echo "============================================================"
echo " Готово ✅"
echo "============================================================"
echo "Відкрий у Webconfig:"
echo "  /app/openvpn_watchdog"
echo
echo "Якщо щось піде не так, backup тут:"
echo "  $BACKUP_ROOT"
echo "============================================================"

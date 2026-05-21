#!/usr/bin/env bash
#
# build-rpm.sh - build app-openvpn-watchdog RPM from the current source tree.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOPDIR="${RPM_TOPDIR:-$HOME/rpmbuild}"
NODEPS=0
SKIP_TESTS=0
CLEAN_TMP=1

usage() {
    cat <<USAGE
Usage:
  $0 [options]

Options:
  --topdir DIR       rpmbuild topdir, default: $TOPDIR
  --nodeps           Pass --nodeps to rpmbuild
  --skip-tests       Skip PHP/Bash syntax checks
  --keep-tmp         Keep temporary source directory
  -h, --help         Show this help

Examples:
  ./packaging/build-rpm.sh --nodeps
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --topdir)
            TOPDIR="$2"
            shift 2
            ;;
        --nodeps)
            NODEPS=1
            shift
            ;;
        --skip-tests)
            SKIP_TESTS=1
            shift
            ;;
        --keep-tmp)
            CLEAN_TMP=0
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "❌ Невідомий параметр: $1" >&2
            usage
            exit 1
            ;;
    esac
done

if [[ -f "$SCRIPT_DIR/packaging/app-openvpn-watchdog.spec" ]]; then
    PROJECT_ROOT="$SCRIPT_DIR"
    SPEC_FILE="$SCRIPT_DIR/packaging/app-openvpn-watchdog.spec"
elif [[ -f "$SCRIPT_DIR/app-openvpn-watchdog.spec" && "$(basename "$SCRIPT_DIR")" == "packaging" ]]; then
    PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
    SPEC_FILE="$SCRIPT_DIR/app-openvpn-watchdog.spec"
else
    echo "❌ Не знайдено app-openvpn-watchdog.spec." >&2
    exit 1
fi

if ! command -v rpmbuild >/dev/null 2>&1; then
    echo "❌ rpmbuild не знайдено." >&2
    echo "Для ClearOS/CentOS: yum install -y rpm-build" >&2
    exit 1
fi

NAME="$(awk '$1 == "Name:" { print $2; exit }' "$SPEC_FILE")"
VERSION="$(awk '$1 == "Version:" { print $2; exit }' "$SPEC_FILE")"
LOCAL_TARBALL_NAME="${NAME}-${VERSION}.tar.gz"

SOURCES_DIR="$TOPDIR/SOURCES"
SPECS_DIR="$TOPDIR/SPECS"
RPMS_DIR="$TOPDIR/RPMS"
SRPMS_DIR="$TOPDIR/SRPMS"
BUILD_DIR="$TOPDIR/BUILD"
BUILDROOT_DIR="$TOPDIR/BUILDROOT"
TMP_PARENT="$(mktemp -d "/tmp/${NAME}-rpmbuild.XXXXXX")"
SOURCE_DIR="$TMP_PARENT/${NAME}-${VERSION}"
SOURCE_PATH="$SOURCES_DIR/$LOCAL_TARBALL_NAME"
SPEC_WORK="$SPECS_DIR/$(basename "$SPEC_FILE")"

cleanup() {
    if [[ "$CLEAN_TMP" -eq 1 ]]; then
        rm -rf "$TMP_PARENT"
    else
        echo "ℹ Тимчасовий каталог залишено: $TMP_PARENT"
    fi
}
trap cleanup EXIT

run_syntax_tests() {
    echo "🔎 Перевіряю PHP/Bash синтаксис ..."
    if command -v php >/dev/null 2>&1; then
        while IFS= read -r -d '' file; do
            php -l "$file" >/dev/null
        done < <(find "$PROJECT_ROOT" -path "$PROJECT_ROOT/.git" -prune -o -type f -name '*.php' -print0)
        echo "✅ PHP syntax OK"
    else
        echo "⚠ php не знайдено, пропускаю PHP syntax check."
    fi

    local bash_files=()
    [[ -f "$PROJECT_ROOT/install-app-tree-dev.sh" ]] && bash_files+=("$PROJECT_ROOT/install-app-tree-dev.sh")
    [[ -f "$PROJECT_ROOT/deploy/install" ]] && bash_files+=("$PROJECT_ROOT/deploy/install")
    [[ -f "$PROJECT_ROOT/deploy/openvpn-watchdog-helper.sh" ]] && bash_files+=("$PROJECT_ROOT/deploy/openvpn-watchdog-helper.sh")
    [[ -f "$PROJECT_ROOT/packaging/build-rpm.sh" ]] && bash_files+=("$PROJECT_ROOT/packaging/build-rpm.sh")

    local file
    for file in "${bash_files[@]}"; do
        bash -n "$file"
    done
    echo "✅ Bash syntax OK"
}

verify_source_tree() {
    local required_files=(
        "deploy/install"
        "deploy/openvpn-watchdog-helper.sh"
        "libraries/Openvpn_Watchdog.php"
        "controllers/openvpn_watchdog.php"
        "controllers/settings.php"
        "controllers/server.php"
        "views/summary.php"
        "views/settings.php"
        "views/profile.php"
    )

    local missing=0
    local file
    for file in "${required_files[@]}"; do
        if [[ -f "$SOURCE_DIR/$file" ]]; then
            echo "✅ $file"
        else
            echo "❌ Не знайдено у Source0: $file" >&2
            missing=1
        fi
    done

    if [[ "$missing" -ne 0 ]]; then
        echo "❌ Source0 неповний. RPM не збираю." >&2
        exit 1
    fi
}

create_source_tarball() {
    mkdir -p "$SOURCE_DIR"
    (
        cd "$PROJECT_ROOT"
        tar \
            --exclude='./.git' \
            --exclude='./rpmbuild' \
            --exclude='./build' \
            --exclude='./dist' \
            --exclude='./tmp' \
            --exclude='./*.rpm' \
            --exclude='./*.src.rpm' \
            --exclude='./*.tar.gz' \
            --exclude='./*.zip' \
            --exclude='./*.log' \
            -cf - .
    ) | (
        cd "$SOURCE_DIR"
        tar -xf -
    )

    verify_source_tree
    tar -C "$TMP_PARENT" -czf "$SOURCE_PATH" "${NAME}-${VERSION}"

    awk -v local_source="$LOCAL_TARBALL_NAME" '
        /^Source0:[[:space:]]*/ { print "Source0:        " local_source; next }
        { print }
    ' "$SPEC_FILE" > "$SPEC_WORK"
}

mkdir -p "$SOURCES_DIR" "$SPECS_DIR" "$RPMS_DIR" "$SRPMS_DIR" "$BUILD_DIR" "$BUILDROOT_DIR"

if [[ "$SKIP_TESTS" -eq 0 ]]; then
    run_syntax_tests
fi

create_source_tarball

RPMBUILD_ARGS=("-ba" "$SPEC_WORK" "--define" "_topdir $TOPDIR")
if [[ "$NODEPS" -eq 1 ]]; then
    RPMBUILD_ARGS+=("--nodeps")
fi

rpmbuild "${RPMBUILD_ARGS[@]}"

echo "✅ Готово. RPM:"
find "$RPMS_DIR" -type f -name "${NAME}-*.rpm" -print

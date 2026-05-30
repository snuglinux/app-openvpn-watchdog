#!/usr/bin/env bash
#
# clearos-openvpn-watchdog-helper
#
# Small privileged helper for app-openvpn-watchdog Webconfig operations.
# Keep commands fixed and conservative. Do not accept arbitrary shell commands.

set -euo pipefail

WATCHDOG="/usr/bin/openvpn-watchdog"
SERVICE="openvpn-watchdog.service"
TIMER="openvpn-watchdog.timer"
EVENT_LOG="/var/log/openvpn-watchdog/events.log"
LOG_DIR="/var/log/openvpn-watchdog"
TIMEOUT="/usr/bin/timeout"
[ -x "$TIMEOUT" ] || TIMEOUT="/bin/timeout"

log() {
    printf '%s\n' "$*"
}

need_systemctl() {
    command -v systemctl >/dev/null 2>&1 || {
        log "systemctl не знайдено"
        exit 1
    }
}

need_watchdog() {
    if [ ! -x "$WATCHDOG" ]; then
        log "openvpn-watchdog не встановлено: $WATCHDOG"
        exit 1
    fi
}

is_safe_profile_name() {
    local name="${1:-}"
    [[ "$name" =~ ^[A-Za-z0-9_.-]+$ ]]
}

list_profiles_from_dir() {
    local type="$1"
    local dir="$2"
    local suffix="$3"
    local file base name

    [ -d "$dir" ] || return 0

    find "$dir" -maxdepth 1 -type f -name "*$suffix" 2>/dev/null | LC_ALL=C sort | while IFS= read -r file; do
        [ -n "$file" ] || continue
        base=$(basename "$file")
        name=${base%$suffix}

        if ! is_safe_profile_name "$name"; then
            continue
        fi

        printf '%s\t%s\n' "$type" "$name"
    done
}

list_profiles() {
    # Return only safe profile names, not file contents.
    # Format: TYPE<TAB>NAME
    #
    # Supported OpenVPN layouts:
    #   /etc/openvpn/client/<name>-client.conf
    #   /etc/openvpn/server/<name>-server.conf
    #   /etc/openvpn/<name>.conf
    list_profiles_from_dir "CLIENT" "/etc/openvpn/client" "-client.conf"
    list_profiles_from_dir "SERVER" "/etc/openvpn/server" "-server.conf"
    list_profiles_from_dir "LEGACY" "/etc/openvpn" ".conf"
}

file_mode() {
    stat -c '%a' "$1" 2>/dev/null || return 1
}

has_other_permissions() {
    local mode="$1"
    local last="${mode:${#mode}-1}"
    [ "$((10#$last & 7))" -ne 0 ]
}

report_unsafe_permission() {
    local path="$1"
    local recommended="$2"
    local reason="$3"
    local mode

    [ -e "$path" ] || return 0
    mode=$(file_mode "$path") || return 0

    if has_other_permissions "$mode"; then
        printf 'UNSAFE\t%s\t%s\t%s\t%s\n' "$path" "$mode" "$recommended" "$reason"
    fi
}

find_sensitive_openvpn_files() {
    local base="$1"
    local maxdepth="$2"

    [ -d "$base" ] || return 0

    find "$base" -maxdepth "$maxdepth" -type f \
        \( -name '*.conf' \
        -o -name '*.ovpn' \
        -o -name '*.key' \
        -o -name '*.pem' \
        -o -name '*.crt' \
        -o -name '*.p12' \
        -o -name '*.pfx' \
        -o -name '*.pass' \
        -o -name 'pass' \) 2>/dev/null | LC_ALL=C sort
}

check_openvpn_permissions() {
    # Warn only about world/other access on sensitive OpenVPN dirs/files.
    # The helper does not print secrets and does not change anything here.
    local path

    report_unsafe_permission "/etc/openvpn/client" "0750" "other-permissions-on-client-directory"
    report_unsafe_permission "/etc/openvpn/server" "0750" "other-permissions-on-server-directory"

    if [ -d /etc/openvpn/client ]; then
        find /etc/openvpn/client -maxdepth 1 -type d -name 'key*' 2>/dev/null | LC_ALL=C sort | while IFS= read -r path; do
            report_unsafe_permission "$path" "0750" "other-permissions-on-key-directory"
        done

        find_sensitive_openvpn_files /etc/openvpn/client 2 | while IFS= read -r path; do
            report_unsafe_permission "$path" "0640" "other-permissions-on-sensitive-file"
        done
    fi

    if [ -d /etc/openvpn/server ]; then
        find /etc/openvpn/server -maxdepth 1 -type d -name 'key*' 2>/dev/null | LC_ALL=C sort | while IFS= read -r path; do
            report_unsafe_permission "$path" "0750" "other-permissions-on-key-directory"
        done

        find_sensitive_openvpn_files /etc/openvpn/server 2 | while IFS= read -r path; do
            report_unsafe_permission "$path" "0640" "other-permissions-on-sensitive-file"
        done
    fi

    find_sensitive_openvpn_files /etc/openvpn 1 | while IFS= read -r path; do
        report_unsafe_permission "$path" "0640" "other-permissions-on-sensitive-file"
    done
}

fix_openvpn_permissions() {
    # Do not change owner/group.  Only remove access for "other" users and
    # restore conservative modes on sensitive OpenVPN directories/files.
    # This intentionally avoids client.up/client.down/restart scripts.
    local path

    [ -d /etc/openvpn/client ] && chmod 0750 /etc/openvpn/client 2>/dev/null || true
    [ -d /etc/openvpn/server ] && chmod 0750 /etc/openvpn/server 2>/dev/null || true

    if [ -d /etc/openvpn/client ]; then
        find /etc/openvpn/client -maxdepth 1 -type d -name 'key*' 2>/dev/null | while IFS= read -r path; do
            chmod 0750 "$path" 2>/dev/null || true
        done

        find_sensitive_openvpn_files /etc/openvpn/client 2 | while IFS= read -r path; do
            chmod 0640 "$path" 2>/dev/null || true
        done
    fi

    if [ -d /etc/openvpn/server ]; then
        find /etc/openvpn/server -maxdepth 1 -type d -name 'key*' 2>/dev/null | while IFS= read -r path; do
            chmod 0750 "$path" 2>/dev/null || true
        done

        find_sensitive_openvpn_files /etc/openvpn/server 2 | while IFS= read -r path; do
            chmod 0640 "$path" 2>/dev/null || true
        done
    fi

    find_sensitive_openvpn_files /etc/openvpn 1 | while IFS= read -r path; do
        chmod 0640 "$path" 2>/dev/null || true
    done

    log "OpenVPN sensitive permissions fixed. Owner/group were not changed."
}

run_watchdog() {
    need_watchdog
    local mode="$1"
    local rc=0

    if [ "$mode" = "dry-run" ]; then
        log "============================================================"
        log " OpenVPN Watchdog: тест без змін"
        log "============================================================"
        if [ -x "$TIMEOUT" ]; then
            "$TIMEOUT" 240 "$WATCHDOG" --dry-run
            rc=$?
        else
            "$WATCHDOG" --dry-run
            rc=$?
        fi
    else
        log "============================================================"
        log " OpenVPN Watchdog: запуск перевірки зараз"
        log "============================================================"
        log "Увага: якщо watchdog знайде проблему, він може перезапустити тільки проблемний OpenVPN-сервіс."
        if [ -x "$TIMEOUT" ]; then
            "$TIMEOUT" 240 "$WATCHDOG"
            rc=$?
        else
            "$WATCHDOG"
            rc=$?
        fi
    fi

    log "============================================================"
    log "Код завершення: $rc"
    exit "$rc"
}

start_timer() {
    need_systemctl
    systemctl daemon-reload || true
    systemctl reset-failed "$SERVICE" "$TIMER" || true
    systemctl enable --now "$TIMER"
    log "Таймер $TIMER увімкнено і запущено."
}

stop_timer() {
    need_systemctl
    systemctl disable --now "$TIMER" || true
    log "Таймер $TIMER зупинено і вимкнено."
}

restart_timer() {
    need_systemctl
    systemctl daemon-reload || true
    systemctl reset-failed "$SERVICE" "$TIMER" || true
    systemctl restart "$TIMER"
    log "Таймер $TIMER перезапущено."
}

reset_failed() {
    need_systemctl
    systemctl reset-failed "$SERVICE" "$TIMER" || true
    log "Failed-стан очищено для $SERVICE і $TIMER."
}

clear_events() {
    install -d -m 0755 -o root -g root "$LOG_DIR"
    : > "$EVENT_LOG"
    chown root:root "$EVENT_LOG" 2>/dev/null || true
    chmod 0644 "$EVENT_LOG" 2>/dev/null || true
    log "Журнал подій очищено: $EVENT_LOG"
}

status_all() {
    need_systemctl
    log "============================================================"
    log " OpenVPN Watchdog status"
    log "============================================================"
    systemctl is-enabled "$TIMER" 2>/dev/null | sed 's/^/timer enabled: /' || true
    systemctl is-active "$TIMER" 2>/dev/null | sed 's/^/timer active : /' || true
    systemctl is-active "$SERVICE" 2>/dev/null | sed 's/^/service active: /' || true
    log ""
    systemctl list-timers "$TIMER" --no-pager 2>/dev/null || true
    log ""
    journalctl -u "$SERVICE" -n 80 --no-pager 2>/dev/null || true
}

case "${1:-}" in
    dry-run)
        run_watchdog dry-run
        ;;
    run-now)
        run_watchdog run-now
        ;;
    start)
        start_timer
        ;;
    stop)
        stop_timer
        ;;
    restart)
        restart_timer
        ;;
    reset-failed)
        reset_failed
        ;;
    status)
        status_all
        ;;
    clear-events)
        clear_events
        ;;
    list-profiles)
        list_profiles
        ;;
    check-permissions)
        check_openvpn_permissions
        ;;
    fix-permissions)
        fix_openvpn_permissions
        ;;
    *)
        log "Usage: $0 {dry-run|run-now|start|stop|restart|reset-failed|status|clear-events|list-profiles|check-permissions|fix-permissions}"
        exit 2
        ;;
esac

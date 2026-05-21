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
    *)
        log "Usage: $0 {dry-run|run-now|start|stop|restart|reset-failed|status|clear-events}"
        exit 2
        ;;
esac

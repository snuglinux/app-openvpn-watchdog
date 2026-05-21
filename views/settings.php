<?php

/**
 * OpenVPN Watchdog settings edit view.
 *
 * @category   apps
 * @package    openvpn-watchdog
 * @subpackage views
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$this->lang->load('base');
$this->lang->load('openvpn_watchdog');

if (! isset($settings))
    $settings = array();
if (! isset($language_options))
    $language_options = array('auto' => 'auto', 'en' => 'en', 'uk' => 'uk');
if (! isset($method_options))
    $method_options = array('auto' => 'auto', 'http' => 'http', 'ping' => 'ping');
if (! isset($ip_version_options))
    $ip_version_options = array('4' => 'IPv4', '6' => 'IPv6', 'auto' => 'auto');
if (! isset($request_method_options))
    $request_method_options = array('HEAD' => 'HEAD', 'GET' => 'GET');

if (! function_exists('openvpn_watchdog_settings_escape')) {
    function openvpn_watchdog_settings_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function openvpn_watchdog_setting($settings, $key, $default)
{
    return isset($settings[$key]) ? $settings[$key] : $default;
}

$lang_value = openvpn_watchdog_setting($settings, 'OPENVPN_WATCHDOG_LANGUAGE', 'auto');
$log_retention_days = openvpn_watchdog_setting($settings, 'LOG_RETENTION_DAYS', '30');
$ping_count = openvpn_watchdog_setting($settings, 'PING_COUNT', '3');
$ping_timeout = openvpn_watchdog_setting($settings, 'PING_TIMEOUT', '3');
$default_restart_cycles = openvpn_watchdog_setting($settings, 'DEFAULT_RESTART_CYCLES', '3');
$restart_cooldown_seconds = openvpn_watchdog_setting($settings, 'RESTART_COOLDOWN_SECONDS', '60');
$internet_check_enabled = openvpn_watchdog_setting($settings, 'INTERNET_CHECK_ENABLED', 'YES') === 'YES';
$internet_check_method = openvpn_watchdog_setting($settings, 'INTERNET_CHECK_METHOD', 'auto');
$http_server_int = openvpn_watchdog_setting($settings, 'HTTP_SERVER_INT', 'https://google.com,https://cloudflare.com');
$ping_server_int = openvpn_watchdog_setting($settings, 'PING_SERVER_INT', '8.8.8.8,1.1.1.1');
$http_connect_timeout = openvpn_watchdog_setting($settings, 'HTTP_CONNECT_TIMEOUT', '8');
$http_max_time = openvpn_watchdog_setting($settings, 'HTTP_MAX_TIME', '12');
$http_ip_version = openvpn_watchdog_setting($settings, 'HTTP_IP_VERSION', '4');
$http_request_method = openvpn_watchdog_setting($settings, 'HTTP_REQUEST_METHOD', 'HEAD');
$skip_client_ping_when_internet_down = openvpn_watchdog_setting($settings, 'SKIP_CLIENT_PING_WHEN_INTERNET_DOWN', 'YES') === 'YES';
$notifications_enabled = openvpn_watchdog_setting($settings, 'NOTIFICATIONS_ENABLED', 'NO') === 'YES';
$notify_script = openvpn_watchdog_setting($settings, 'NOTIFY_SCRIPT', '');
$log_analysis_enabled = openvpn_watchdog_setting($settings, 'LOG_ANALYSIS_ENABLED', 'YES') === 'YES';
$log_analysis_on_problem_only = openvpn_watchdog_setting($settings, 'LOG_ANALYSIS_ON_PROBLEM_ONLY', 'YES') === 'YES';
$log_analysis_lines = openvpn_watchdog_setting($settings, 'LOG_ANALYSIS_LINES', '120');
$log_analysis_max_matches = openvpn_watchdog_setting($settings, 'LOG_ANALYSIS_MAX_MATCHES', '5');
$log_analysis_patterns = openvpn_watchdog_setting($settings, 'LOG_ANALYSIS_PATTERNS', 'AUTH_FAILED|TLS Error|Inactivity timeout|Connection reset|Cannot resolve host address|VERIFY ERROR|Options error|Exiting due to fatal error');

echo infobox_highlight(lang('base_information'), lang('openvpn_watchdog_help'));

echo form_open('openvpn_watchdog/settings/edit');
echo form_header(lang('base_settings'));

echo field_dropdown('OPENVPN_WATCHDOG_LANGUAGE', $language_options, $lang_value, lang('openvpn_watchdog_language'), FALSE);


echo field_input('LOG_RETENTION_DAYS', $log_retention_days, lang('openvpn_watchdog_log_retention_days'), FALSE);

echo field_input('PING_COUNT', $ping_count, lang('openvpn_watchdog_ping_count'), FALSE);
echo field_input('PING_TIMEOUT', $ping_timeout, lang('openvpn_watchdog_ping_timeout'), FALSE);
echo field_input('DEFAULT_RESTART_CYCLES', $default_restart_cycles, lang('openvpn_watchdog_default_restart_cycles'), FALSE);
echo field_input('RESTART_COOLDOWN_SECONDS', $restart_cooldown_seconds, lang('openvpn_watchdog_restart_cooldown_seconds'), FALSE);

echo field_toggle_enable_disable('INTERNET_CHECK_ENABLED', $internet_check_enabled, lang('openvpn_watchdog_internet_check_enabled'));
echo field_dropdown('INTERNET_CHECK_METHOD', $method_options, $internet_check_method, lang('openvpn_watchdog_internet_check_method'), FALSE);
echo field_input('HTTP_SERVER_INT', $http_server_int, lang('openvpn_watchdog_http_targets'), FALSE);
echo field_input('PING_SERVER_INT', $ping_server_int, lang('openvpn_watchdog_ping_targets'), FALSE);
echo field_input('HTTP_CONNECT_TIMEOUT', $http_connect_timeout, lang('openvpn_watchdog_http_connect_timeout'), FALSE);
echo field_input('HTTP_MAX_TIME', $http_max_time, lang('openvpn_watchdog_http_max_time'), FALSE);
echo field_dropdown('HTTP_IP_VERSION', $ip_version_options, $http_ip_version, lang('openvpn_watchdog_http_ip_version'), FALSE);
echo field_dropdown('HTTP_REQUEST_METHOD', $request_method_options, $http_request_method, lang('openvpn_watchdog_http_request_method'), FALSE);
echo field_toggle_enable_disable('SKIP_CLIENT_PING_WHEN_INTERNET_DOWN', $skip_client_ping_when_internet_down, lang('openvpn_watchdog_skip_client_ping_when_internet_down'));

echo field_toggle_enable_disable('NOTIFICATIONS_ENABLED', $notifications_enabled, lang('openvpn_watchdog_notifications_enabled'));
echo field_input('NOTIFY_SCRIPT', $notify_script, lang('openvpn_watchdog_notify_script'), FALSE);

echo field_toggle_enable_disable('LOG_ANALYSIS_ENABLED', $log_analysis_enabled, lang('openvpn_watchdog_log_analysis_enabled'));
echo field_toggle_enable_disable('LOG_ANALYSIS_ON_PROBLEM_ONLY', $log_analysis_on_problem_only, lang('openvpn_watchdog_log_analysis_on_problem_only'));
echo field_input('LOG_ANALYSIS_LINES', $log_analysis_lines, lang('openvpn_watchdog_log_analysis_lines'), FALSE);
echo field_input('LOG_ANALYSIS_MAX_MATCHES', $log_analysis_max_matches, lang('openvpn_watchdog_log_analysis_max_matches'), FALSE);
echo field_input('LOG_ANALYSIS_PATTERNS', $log_analysis_patterns, lang('openvpn_watchdog_log_analysis_patterns'), FALSE);

echo field_button_set(array(
    form_submit_update('submit'),
    anchor_cancel('/app/openvpn_watchdog')
));

echo form_footer();
echo form_close();

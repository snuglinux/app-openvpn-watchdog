<?php

/**
 * OpenVPN Watchdog summary view.
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
if (! isset($profiles))
    $profiles = array();
if (! isset($watchdog_version))
    $watchdog_version = '-';
if (! isset($service_summary))
    $service_summary = array();
if (! isset($config_warnings))
    $config_warnings = array();
if (! isset($action_output))
    $action_output = '';
if (! isset($action_status))
    $action_status = '';
if (! isset($action_title))
    $action_title = '';
if (! isset($method_options))
    $method_options = array();

if (! function_exists('openvpn_watchdog_view_escape')) {
    function openvpn_watchdog_view_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('openvpn_watchdog_view_dash')) {
    function openvpn_watchdog_view_dash($value)
    {
        $value = trim((string) $value);
        return ($value === '') ? '-' : openvpn_watchdog_view_escape($value);
    }
}

$method = isset($settings['INTERNET_CHECK_METHOD']) ? $settings['INTERNET_CHECK_METHOD'] : 'auto';
$method_display = isset($method_options[$method]) ? $method_options[$method] : $method;

///////////////////////////////////////////////////////////////////////////////
// Standard ClearOS daemon sidebar integration.  This controls the timer.
///////////////////////////////////////////////////////////////////////////////

echo "<input id='os_app_name' value='openvpn_watchdog' type='hidden'>\n";
echo "<input id='os_daemon_name' value='openvpn-watchdog' type='hidden'>\n";
echo "<input id='os_daemon_status_lock' value='off' type='hidden'>\n";

///////////////////////////////////////////////////////////////////////////////
// Information and warnings
///////////////////////////////////////////////////////////////////////////////

if (is_array($config_warnings) && count($config_warnings) > 0) {
    $warning_text = '';
    foreach ($config_warnings as $warning)
        $warning_text .= openvpn_watchdog_view_escape($warning) . '<br>';
    echo infobox_warning(lang('base_warning'), $warning_text);
}

if ($action_status === 'success') {
    $output_text = trim((string) $action_output);
    if ($output_text === '')
        $output_text = '-';
    echo infobox_highlight(
        openvpn_watchdog_view_escape($action_title === '' ? lang('openvpn_watchdog_action_result') : $action_title),
        '<pre style="white-space: pre-wrap; word-break: break-word; margin: 0;">' . openvpn_watchdog_view_escape($output_text) . '</pre>'
    );
}

///////////////////////////////////////////////////////////////////////////////
// Status summary
///////////////////////////////////////////////////////////////////////////////

echo form_open('openvpn_watchdog/settings/edit');
echo form_header(lang('openvpn_watchdog_status'));

echo field_view(lang('openvpn_watchdog_version'), openvpn_watchdog_view_dash($watchdog_version));
echo field_view(lang('openvpn_watchdog_internet_check_method'), openvpn_watchdog_view_dash($method_display));
echo field_view(lang('openvpn_watchdog_http_targets'), openvpn_watchdog_view_dash(isset($settings['HTTP_SERVER_INT']) ? $settings['HTTP_SERVER_INT'] : ''));
echo field_view(lang('openvpn_watchdog_ping_targets'), openvpn_watchdog_view_dash(isset($settings['PING_SERVER_INT']) ? $settings['PING_SERVER_INT'] : ''));

echo field_button_set(array(
    anchor_edit('/app/openvpn_watchdog/settings/edit'),
    anchor_custom('/app/openvpn_watchdog/events', '📋 ' . lang('openvpn_watchdog_recent_events'), 'low'),
    anchor_custom('/app/openvpn_watchdog/dry_run', '🧪 ' . lang('openvpn_watchdog_dry_run'), 'low'),
    anchor_custom('/app/openvpn_watchdog/run_now', '▶ ' . lang('openvpn_watchdog_run_now'), 'high')
));

echo form_footer();
echo form_close();

///////////////////////////////////////////////////////////////////////////////
// Profiles
///////////////////////////////////////////////////////////////////////////////

$buttons = array(
    anchor_add('/app/openvpn_watchdog/settings/add'),
);

$headers = array(
    lang('openvpn_watchdog_profile'),
    lang('openvpn_watchdog_type'),
    lang('openvpn_watchdog_ping'),
    lang('openvpn_watchdog_restart_cycles_short'),
);

$items = array();

if (is_array($profiles)) {
    foreach ($profiles as $index => $profile) {
        $items[] = array(
            'anchors' => button_set(array(
                anchor_edit('/app/openvpn_watchdog/settings/profile/' . intval($index)),
                anchor_delete('/app/openvpn_watchdog/settings/delete/' . intval($index)),
            )),
            'details' => array(
                openvpn_watchdog_view_dash(isset($profile['name']) ? $profile['name'] : ''),
                openvpn_watchdog_view_dash(isset($profile['type']) ? strtoupper($profile['type']) : ''),
                openvpn_watchdog_view_dash(isset($profile['ping']) ? $profile['ping'] : ''),
                openvpn_watchdog_view_dash(isset($profile['restart_cycles']) ? $profile['restart_cycles'] : ''),
            ),
        );
    }
}

echo summary_table(
    lang('openvpn_watchdog_profiles'),
    $buttons,
    $headers,
    $items
);

if (count($items) === 0)
    echo infobox_warning(lang('base_warning'), lang('openvpn_watchdog_no_profiles'));

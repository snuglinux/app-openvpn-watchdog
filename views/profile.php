<?php

/**
 * OpenVPN Watchdog profile edit view.
 *
 * @category   apps
 * @package    openvpn-watchdog
 * @subpackage views
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$this->lang->load('base');
$this->lang->load('openvpn_watchdog');

if (! isset($profile) || ! is_array($profile))
    $profile = array();
if (! isset($type_options))
    $type_options = array('CLIENT' => 'CLIENT', 'SERVER' => 'SERVER');
if (! isset($profile_name_options))
    $profile_name_options = array();
if (! isset($form_action))
    $form_action = 'openvpn_watchdog/settings/add';
if (! isset($ping_target_suggestions))
    $ping_target_suggestions = array();
if (! isset($is_new))
    $is_new = TRUE;

if (! function_exists('openvpn_watchdog_profile_escape')) {
    function openvpn_watchdog_profile_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('openvpn_watchdog_profile_value')) {
    function openvpn_watchdog_profile_value($profile, $key, $default = '')
    {
        return isset($profile[$key]) ? $profile[$key] : $default;
    }
}

$name = openvpn_watchdog_profile_value($profile, 'name', '');
$type = strtoupper(openvpn_watchdog_profile_value($profile, 'type', 'CLIENT'));
$ping = openvpn_watchdog_profile_value($profile, 'ping', '');
$restart_cycles = openvpn_watchdog_profile_value($profile, 'restart_cycles', '3');
$service = openvpn_watchdog_profile_value($profile, 'service', '');

if (! isset($type_options[$type]))
    $type = 'CLIENT';

if ($name !== '' && ! isset($profile_name_options[$name]))
    $profile_name_options[$name] = $name;

ksort($profile_name_options, SORT_NATURAL | SORT_FLAG_CASE);

echo infobox_highlight(lang('openvpn_watchdog_information'), lang('openvpn_watchdog_profile_form_help'));

echo form_open($form_action);
echo form_header($is_new ? lang('openvpn_watchdog_add_profile') : lang('openvpn_watchdog_edit_profile'));

if (count($profile_name_options) > 0) {
    echo field_dropdown('name', $profile_name_options, $name, lang('openvpn_watchdog_profile_name'), FALSE);
} else {
    echo infobox_warning(lang('openvpn_watchdog_warning'), lang('openvpn_watchdog_no_openvpn_config_files'));
    echo field_input('name', $name, lang('openvpn_watchdog_profile_name'), FALSE);
}
echo field_dropdown('type', $type_options, $type, lang('openvpn_watchdog_profile_type'), FALSE);
echo field_input('ping', $ping, lang('openvpn_watchdog_profile_ping_targets'), FALSE);

if (is_array($ping_target_suggestions) && count($ping_target_suggestions) > 0) {
    $suggestion_options = array('' => lang('openvpn_watchdog_ping_suggestion_placeholder')) + $ping_target_suggestions;
    echo field_dropdown('ping_suggestion', $suggestion_options, '', lang('openvpn_watchdog_ping_suggestion'), FALSE);
    echo infobox_highlight(lang('openvpn_watchdog_information'), lang('openvpn_watchdog_ping_suggestion_help'));
}

echo field_input('restart_cycles', $restart_cycles, lang('openvpn_watchdog_profile_restart_cycles'), FALSE);
echo field_input('service', $service, lang('openvpn_watchdog_profile_custom_service'), FALSE);

echo infobox_highlight(
    lang('openvpn_watchdog_information'),
    lang('openvpn_watchdog_profile_service_help')
);

echo field_button_set(array(
    '<input type="submit" name="submit" value="' . openvpn_watchdog_profile_escape(lang('openvpn_watchdog_update')) . '" class="btn btn-primary" />',
    anchor_custom('/app/openvpn_watchdog', lang('openvpn_watchdog_cancel'), 'low')
));

echo "<script>
";
echo "(function(){
";
echo "  var suggestion = document.querySelector('[name=\"ping_suggestion\"]');
";
echo "  var ping = document.querySelector('[name=\"ping\"]');
";
echo "  if (suggestion && ping) {
";
echo "    suggestion.onchange = function(){
";
echo "      if (suggestion.value !== '') ping.value = suggestion.value;
";
echo "    };
";
echo "  }
";
echo "})();
";
echo "</script>
";

echo form_footer();
echo form_close();

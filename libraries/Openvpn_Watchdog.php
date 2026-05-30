<?php

/**
 * OpenVPN Watchdog ClearOS integration library.
 *
 * @category   apps
 * @package    openvpn-watchdog
 * @subpackage libraries
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 */

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\openvpn_watchdog;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('base');
clearos_load_language('openvpn_watchdog');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\base\Daemon as Daemon;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Daemon');
clearos_load_library('base/Engine_Exception');
clearos_load_library('base/File');
clearos_load_library('base/Shell');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

class Openvpn_Watchdog extends Daemon
{
    ///////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////

    const FILE_CONFIG = '/etc/openvpn-watchdog.conf';
    const COMMAND_WATCHDOG = '/usr/bin/openvpn-watchdog';
    const COMMAND_HELPER = '/usr/sbin/clearos-openvpn-watchdog-helper';
    const SERVICE_UNIT = 'openvpn-watchdog.service';
    const TIMER_UNIT = 'openvpn-watchdog.timer';
    const EVENT_LOG = '/var/log/openvpn-watchdog/events.log';
    const CONFIG_BACKUP_KEEP = 3;

    ///////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////

    protected $is_loaded = FALSE;
    protected $settings = array();

    ///////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////

    /**
     * Constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        parent::__construct('openvpn-watchdog');
    }

    /**
     * Returns config file path.
     *
     * @return string path
     */

    public function get_config_file()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::FILE_CONFIG;
    }

    /**
     * Returns app settings.
     *
     * @return array settings
     */

    public function get_settings()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_loaded)
            $this->_load_settings();

        return $this->settings;
    }

    /**
     * Saves settings.
     *
     * @param array $settings settings
     *
     * @return void
     * @throws Engine_Exception
     * @throws Validation_Exception
     */

    public function set_settings($settings)
    {
        clearos_profile(__METHOD__, __LINE__);

        $normalized = $this->_normalize_settings($settings);
        $this->_validate_settings($normalized);
        $this->_write_config($normalized);

        $this->settings = $normalized;
        $this->is_loaded = TRUE;
    }

    /**
     * Returns language options.
     *
     * @return array options
     */

    public function get_language_options()
    {
        return array(
            'auto' => lang('openvpn_watchdog_language_auto'),
            'en' => lang('openvpn_watchdog_language_en'),
            'uk' => lang('openvpn_watchdog_language_uk'),
        );
    }

    /**
     * Returns Internet check method options.
     *
     * @return array options
     */

    public function get_internet_check_method_options()
    {
        return array(
            'auto' => lang('openvpn_watchdog_method_auto'),
            'http' => lang('openvpn_watchdog_method_http'),
            'ping' => lang('openvpn_watchdog_method_ping'),
        );
    }

    /**
     * Returns HTTP IP version options.
     *
     * @return array options
     */

    public function get_http_ip_version_options()
    {
        return array(
            '4' => 'IPv4',
            '6' => 'IPv6',
            'auto' => 'auto',
        );
    }

    /**
     * Returns HTTP request method options.
     *
     * @return array options
     */

    public function get_http_request_method_options()
    {
        return array(
            'HEAD' => 'HEAD',
            'GET' => 'GET',
        );
    }


    /**
     * Returns OpenVPN profile type options.
     *
     * @return array options
     */

    public function get_profile_type_options()
    {
        return array(
            'CLIENT' => 'CLIENT',
            'SERVER' => 'SERVER',
        );
    }

    /**
     * Returns existing OpenVPN profile names from configuration files.
     *
     * Standard OpenVPN systemd units use these files:
     *   /etc/openvpn/client/<name>-client.conf
     *   /etc/openvpn/server/<name>-server.conf
     *
     * The current profile name is preserved in the list even if the matching
     * file was removed, so existing saved watchdog profiles remain editable.
     *
     * @param string $current_name current profile name
     *
     * @return array dropdown options
     */

    public function get_available_profile_name_options($current_name = '')
    {
        clearos_profile(__METHOD__, __LINE__);

        $current_name = trim((string) $current_name);
        $names = $this->_get_openvpn_profiles_from_helper();

        if ($current_name !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $current_name))
            $names[$current_name] = $current_name;

        ksort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /**
     * Returns VPN-side ping target suggestions.
     *
     * These values are best-effort hints only.  A static OpenVPN config does
     * not always contain the real VPN gateway, so the app combines values from
     * OpenVPN config files and currently active tun/tap interfaces.
     *
     * @param string $profile_name current profile name
     * @param string $current_ping currently configured ping value
     *
     * @return array dropdown options
     */

    public function get_vpn_ping_target_suggestions($profile_name = '', $current_ping = '')
    {
        clearos_profile(__METHOD__, __LINE__);

        $targets = array();
        $profile_name = trim((string) $profile_name);
        $current_ping = trim((string) $current_ping);

        $this->_add_ping_targets_from_list($targets, $current_ping);

        $files = $this->_get_openvpn_config_files($profile_name, TRUE);
        foreach ($files as $file)
            $this->_add_ping_targets_from_openvpn_config($targets, $file);

        $this->_add_ping_targets_from_ip_addr($targets);
        $this->_add_ping_targets_from_ip_route($targets);

        ksort($targets, SORT_NATURAL | SORT_FLAG_CASE);

        return $targets;
    }

    /**
     * Returns installed watchdog version.
     *
     * @return string version
     */

    public function get_watchdog_version()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! is_executable(self::COMMAND_WATCHDOG))
            return lang('openvpn_watchdog_not_installed');

        $output = array();
        $exit_code = 0;
        exec('/usr/bin/timeout 15 ' . escapeshellarg(self::COMMAND_WATCHDOG) . ' --version 2>/dev/null', $output, $exit_code);
        $result = trim(implode("\n", $output));

        if ($result === '')
            return '-';

        return strtok($result, "\n");
    }

    /**
     * Returns parsed profiles from settings.
     *
     * @return array profiles
     */

    public function get_profiles()
    {
        $settings = $this->get_settings();
        return $this->_parse_profiles_text($settings['OPENVPN_PROFILES_TEXT']);
    }


    /**
     * Returns one profile by index.
     *
     * @param int $index profile index
     *
     * @return array profile
     * @throws Engine_Exception
     */

    public function get_profile($index)
    {
        clearos_profile(__METHOD__, __LINE__);

        $profiles = $this->get_profiles();
        $index = intval($index);

        if (! isset($profiles[$index]))
            throw new Engine_Exception(lang('openvpn_watchdog_profile_not_found'), CLEAROS_ERROR);

        return $profiles[$index];
    }

    /**
     * Adds an OpenVPN profile.
     *
     * @param array $profile profile
     *
     * @return void
     * @throws Validation_Exception
     */

    public function add_profile($profile)
    {
        clearos_profile(__METHOD__, __LINE__);

        $settings = $this->get_settings();
        $profiles = $this->get_profiles();
        $normalized = $this->_normalize_profile($profile);
        $this->_validate_profile($normalized, NULL, $profiles);

        $lines = $this->_profile_lines($settings['OPENVPN_PROFILES_TEXT']);
        $lines[] = $this->_profile_to_line($normalized);
        $settings['OPENVPN_PROFILES_TEXT'] = implode("\n", $lines);

        $this->set_settings($settings);
    }

    /**
     * Updates an OpenVPN profile.
     *
     * @param int   $index   profile index
     * @param array $profile profile
     *
     * @return void
     * @throws Engine_Exception
     * @throws Validation_Exception
     */

    public function update_profile($index, $profile)
    {
        clearos_profile(__METHOD__, __LINE__);

        $settings = $this->get_settings();
        $profiles = $this->get_profiles();
        $lines = $this->_profile_lines($settings['OPENVPN_PROFILES_TEXT']);
        $index = intval($index);

        if (! isset($lines[$index]))
            throw new Engine_Exception(lang('openvpn_watchdog_profile_not_found'), CLEAROS_ERROR);

        $normalized = $this->_normalize_profile($profile);
        $this->_validate_profile($normalized, $index, $profiles);

        $lines[$index] = $this->_profile_to_line($normalized);
        $settings['OPENVPN_PROFILES_TEXT'] = implode("\n", $lines);

        $this->set_settings($settings);
    }

    /**
     * Deletes an OpenVPN profile.
     *
     * @param int $index profile index
     *
     * @return void
     * @throws Engine_Exception
     */

    public function delete_profile($index)
    {
        clearos_profile(__METHOD__, __LINE__);

        $settings = $this->get_settings();
        $lines = $this->_profile_lines($settings['OPENVPN_PROFILES_TEXT']);
        $index = intval($index);

        if (! isset($lines[$index]))
            throw new Engine_Exception(lang('openvpn_watchdog_profile_not_found'), CLEAROS_ERROR);

        unset($lines[$index]);
        $settings['OPENVPN_PROFILES_TEXT'] = implode("\n", array_values($lines));

        $this->set_settings($settings);
    }

    /**
     * Returns unsafe OpenVPN permission warnings.
     *
     * @return array warnings
     */

    public function get_openvpn_permission_warnings()
    {
        $warnings = array();

        try {
            $result = $this->_run_helper('check-permissions', TRUE);
        } catch (\Exception $e) {
            return $warnings;
        }

        if (! isset($result['output']) || ! is_array($result['output']))
            return $warnings;

        foreach ($result['output'] as $line) {
            $line = trim((string) $line);
            if ($line === '')
                continue;

            $parts = explode("\t", $line);
            if (! is_array($parts) || count($parts) < 5)
                continue;

            if ($parts[0] !== 'UNSAFE')
                continue;

            $format = lang('openvpn_watchdog_permission_item_format');
            if ($format === 'openvpn_watchdog_permission_item_format' || trim((string) $format) === '')
                $format = '%s - permissions %s, recommended %s (%s)';

            $warnings[] = sprintf($format, $parts[1], $parts[2], $parts[3], $parts[4]);
        }

        return $warnings;
    }

    /**
     * Fixes unsafe OpenVPN permissions through privileged helper.
     *
     * @return void
     */

    public function fix_openvpn_permissions()
    {
        $this->_run_helper('fix-permissions', FALSE);
    }

    /**
     * Returns config warnings.
     *
     * @return array warnings
     */

    public function get_config_warnings()
    {
        $warnings = array();

        if (! is_executable(self::COMMAND_WATCHDOG))
            $warnings[] = lang('openvpn_watchdog_warning_binary_missing');

        if (! file_exists(self::FILE_CONFIG))
            $warnings[] = lang('openvpn_watchdog_warning_config_missing');

        $settings = $this->get_settings();
        if (trim($settings['OPENVPN_PROFILES_TEXT']) === '')
            $warnings[] = lang('openvpn_watchdog_warning_no_profiles');

        return $warnings;
    }

    /**
     * Returns timer/service status summary.
     *
     * @return array summary
     */

    public function get_service_summary()
    {
        clearos_profile(__METHOD__, __LINE__);

        $timer_active = $this->_is_systemd_state(self::TIMER_UNIT, 'is-active');
        $timer_enabled = $this->_is_systemd_state(self::TIMER_UNIT, 'is-enabled');
        $service_active = $this->_is_systemd_state(self::SERVICE_UNIT, 'is-active');

        return array(
            'timer_unit' => self::TIMER_UNIT,
            'service_unit' => self::SERVICE_UNIT,
            'timer_active' => $timer_active,
            'timer_enabled' => $timer_enabled,
            'service_active' => $service_active,
            'timer_active_label' => $timer_active ? lang('base_running') : lang('base_stopped'),
            'timer_enabled_label' => $timer_enabled ? lang('base_enabled') : lang('base_disabled'),
            'service_active_label' => $service_active ? lang('base_running') : lang('base_stopped'),
        );
    }

    /**
     * Returns daemon.js.php compatible status based on timer state.
     *
     * @return string ClearOS daemon status
     */

    public function get_daemon_status()
    {
        clearos_profile(__METHOD__, __LINE__);

        $result = $this->_systemctl('is-active', self::TIMER_UNIT, TRUE);
        $status = trim(implode("\n", $result['output']));

        if ($status === 'active')
            return Daemon::STATUS_RUNNING;
        if ($status === 'activating')
            return Daemon::STATUS_STARTING;
        if ($status === 'deactivating')
            return Daemon::STATUS_STOPPING;
        if ($status === 'failed')
            return Daemon::STATUS_DEAD;

        return Daemon::STATUS_STOPPED;
    }

    /**
     * Starts and enables watchdog timer.
     *
     * @return void
     */

    public function start_and_enable_service()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_run_helper('start', FALSE);
    }

    /**
     * Stops and disables watchdog timer.
     *
     * @return void
     */

    public function stop_and_disable_service()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_run_helper('stop', FALSE);
    }

    /**
     * Runs watchdog normally through helper.
     *
     * @return string output
     */

    public function run_now()
    {
        $result = $this->_run_helper('run-now', TRUE);
        return trim(implode("\n", $result['output']));
    }

    /**
     * Runs watchdog in dry-run mode through helper.
     *
     * @return string output
     */

    public function dry_run()
    {
        $result = $this->_run_helper('dry-run', TRUE);
        return trim(implode("\n", $result['output']));
    }

    /**
     * Clears structured event log through the privileged helper.
     *
     * @return void
     * @throws Engine_Exception
     */

    public function clear_events()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_run_helper('clear-events', FALSE);
    }

    /**
     * Returns structured event log path.
     *
     * @return string path
     */

    public function get_event_log_file()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::EVENT_LOG;
    }

    /**
     * Returns recent structured event log entries.
     *
     * @param int $lines line count
     *
     * @return string text
     */

    public function get_recent_events($lines = 30)
    {
        clearos_profile(__METHOD__, __LINE__);

        $lines = intval($lines);
        if ($lines < 1)
            $lines = 30;
        if ($lines > 200)
            $lines = 200;

        if (! file_exists(self::EVENT_LOG) || ! is_readable(self::EVENT_LOG))
            return '';

        return $this->_run_command('/usr/bin/tail -n ' . intval($lines) . ' ' . escapeshellarg(self::EVENT_LOG), TRUE);
    }

    /**
     * Returns recent structured event log entries as rows for Webconfig.
     *
     * @param int $lines line count
     *
     * @return array event rows
     */

    public function get_recent_event_rows($lines = 100)
    {
        clearos_profile(__METHOD__, __LINE__);

        $text = trim((string) $this->get_recent_events($lines));
        if ($text === '')
            return array();

        $rows = preg_split('/\r\n|\r|\n/', $text);
        $events = array();

        if (! is_array($rows))
            return $events;

        foreach ($rows as $line) {
            $line = trim($line);
            if ($line === '')
                continue;

            $events[] = $this->_parse_event_line($line);
        }

        // The log is chronological; the UI is easier to read with the newest
        // events first.
        return array_reverse($events);
    }

    /**
     * Loads settings from /etc/openvpn-watchdog.conf.
     *
     * @return void
     */

    protected function _load_settings()
    {
        clearos_profile(__METHOD__, __LINE__);

        $settings = $this->_get_default_settings();

        if (file_exists(self::FILE_CONFIG)) {
            $lines = @file(self::FILE_CONFIG, FILE_IGNORE_NEW_LINES);
            if (is_array($lines)) {
                $settings['OPENVPN_PROFILES_TEXT'] = $this->_extract_profiles_text($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || preg_match('/^#/', $line))
                        continue;
                    if (preg_match('/^OPENVPN_PROFILES\s*=\s*\(/', $line))
                        break;
                    if (! preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $matches))
                        continue;

                    $key = $matches[1];
                    $value = $this->_parse_shell_value($matches[2]);

                    if (isset($settings[$key]) && $key !== 'OPENVPN_PROFILES_TEXT')
                        $settings[$key] = $value;
                }
            }
        }

        $this->settings = $this->_normalize_settings($settings);
        $this->is_loaded = TRUE;
    }

    /**
     * Returns default settings.
     *
     * @return array defaults
     */

    protected function _get_default_settings()
    {
        return array(
            'OPENVPN_WATCHDOG_LANGUAGE' => 'auto',
            'OPENVPN_WATCHDOG_LOCALE_DIR' => '/usr/share/openvpn-watchdog/locale',
            'OPENVPN_PROFILES_TEXT' => '',
            'LOG_DIR' => '/var/log/openvpn-watchdog',
            'STATE_DIR' => '/var/lib/openvpn-watchdog',
            'LOG_RETENTION_DAYS' => '30',
            'PING_COUNT' => '3',
            'PING_TIMEOUT' => '3',
            'DEFAULT_RESTART_CYCLES' => '3',
            'RESTART_COOLDOWN_SECONDS' => '60',
            'INTERNET_CHECK_ENABLED' => 'YES',
            'INTERNET_CHECK_METHOD' => 'auto',
            'HTTP_SERVER_INT' => 'https://google.com,https://cloudflare.com',
            'PING_SERVER_INT' => '8.8.8.8,1.1.1.1',
            'HTTP_CONNECT_TIMEOUT' => '8',
            'HTTP_MAX_TIME' => '12',
            'HTTP_IP_VERSION' => '4',
            'HTTP_REQUEST_METHOD' => 'HEAD',
            'SKIP_CLIENT_PING_WHEN_INTERNET_DOWN' => 'YES',
            'SKIP_CLIENT_CONNECTIVITY_WHEN_INTERNET_DOWN' => '',
            'NOTIFICATIONS_ENABLED' => 'NO',
            'NOTIFY_SCRIPT' => '',
            'LOG_ANALYSIS_ENABLED' => 'YES',
            'LOG_ANALYSIS_ON_PROBLEM_ONLY' => 'YES',
            'LOG_ANALYSIS_LINES' => '120',
            'LOG_ANALYSIS_MAX_MATCHES' => '5',
            'LOG_ANALYSIS_PATTERNS' => 'AUTH_FAILED|TLS Error|Inactivity timeout|Connection reset|Cannot resolve host address|VERIFY ERROR|Options error|Exiting due to fatal error',
        );
    }

    /**
     * Normalizes settings.
     *
     * @param array $settings settings
     *
     * @return array normalized settings
     */

    protected function _normalize_settings($settings)
    {
        $defaults = $this->_get_default_settings();
        if (! is_array($settings))
            $settings = array();

        foreach ($defaults as $key => $value) {
            if (! isset($settings[$key]))
                $settings[$key] = $value;
            $settings[$key] = trim((string) $settings[$key]);
        }

        $settings['OPENVPN_WATCHDOG_LANGUAGE'] = strtolower($settings['OPENVPN_WATCHDOG_LANGUAGE']);
        if (! in_array($settings['OPENVPN_WATCHDOG_LANGUAGE'], array('auto', 'en', 'uk'), TRUE))
            $settings['OPENVPN_WATCHDOG_LANGUAGE'] = 'auto';

        $settings['INTERNET_CHECK_METHOD'] = strtolower($settings['INTERNET_CHECK_METHOD']);
        if (! in_array($settings['INTERNET_CHECK_METHOD'], array('auto', 'http', 'ping'), TRUE))
            $settings['INTERNET_CHECK_METHOD'] = 'auto';

        $settings['HTTP_IP_VERSION'] = strtolower($settings['HTTP_IP_VERSION']);
        if (! in_array($settings['HTTP_IP_VERSION'], array('4', '6', 'auto'), TRUE))
            $settings['HTTP_IP_VERSION'] = '4';

        $settings['HTTP_REQUEST_METHOD'] = strtoupper($settings['HTTP_REQUEST_METHOD']);
        if (! in_array($settings['HTTP_REQUEST_METHOD'], array('HEAD', 'GET'), TRUE))
            $settings['HTTP_REQUEST_METHOD'] = 'HEAD';

        $bool_keys = array(
            'INTERNET_CHECK_ENABLED',
            'SKIP_CLIENT_PING_WHEN_INTERNET_DOWN',
            'NOTIFICATIONS_ENABLED',
            'LOG_ANALYSIS_ENABLED',
            'LOG_ANALYSIS_ON_PROBLEM_ONLY',
        );

        foreach ($bool_keys as $key)
            $settings[$key] = $this->_is_enabled_value($settings[$key]) ? 'YES' : 'NO';

        return $settings;
    }

    /**
     * Validates settings.
     *
     * @param array $settings settings
     *
     * @return void
     * @throws Validation_Exception
     */

    protected function _validate_settings($settings)
    {
        if (! in_array($settings['OPENVPN_WATCHDOG_LANGUAGE'], array('auto', 'en', 'uk'), TRUE))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_language'));

        if (! in_array($settings['INTERNET_CHECK_METHOD'], array('auto', 'http', 'ping'), TRUE))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_method'));

        if (! in_array($settings['HTTP_IP_VERSION'], array('4', '6', 'auto'), TRUE))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_http_ip_version'));

        if (! in_array($settings['HTTP_REQUEST_METHOD'], array('HEAD', 'GET'), TRUE))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_http_request_method'));

        $number_keys = array(
            'LOG_RETENTION_DAYS' => array(1, 3650),
            'PING_COUNT' => array(1, 20),
            'PING_TIMEOUT' => array(1, 60),
            'DEFAULT_RESTART_CYCLES' => array(1, 100),
            'RESTART_COOLDOWN_SECONDS' => array(0, 86400),
            'HTTP_CONNECT_TIMEOUT' => array(1, 120),
            'HTTP_MAX_TIME' => array(1, 300),
            'LOG_ANALYSIS_LINES' => array(1, 5000),
            'LOG_ANALYSIS_MAX_MATCHES' => array(1, 100),
        );

        foreach ($number_keys as $key => $range) {
            if (! preg_match('/^[0-9]+$/', $settings[$key]))
                throw new Validation_Exception(lang('openvpn_watchdog_invalid_number') . ': ' . $key);
            $value = intval($settings[$key]);
            if ($value < $range[0] || $value > $range[1])
                throw new Validation_Exception(lang('openvpn_watchdog_invalid_number') . ': ' . $key);
        }

        $path_keys = array('LOG_DIR', 'STATE_DIR', 'OPENVPN_WATCHDOG_LOCALE_DIR');
        foreach ($path_keys as $key) {
            if ($settings[$key] === '' || ! preg_match('#^/[-A-Za-z0-9_./]+$#', $settings[$key]))
                throw new Validation_Exception(lang('openvpn_watchdog_invalid_path') . ': ' . $key);
        }

        if ($settings['NOTIFY_SCRIPT'] !== '' && ! preg_match('#^/[-A-Za-z0-9_./]+$#', $settings['NOTIFY_SCRIPT']))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_path') . ': NOTIFY_SCRIPT');

        if (! $this->_is_safe_target_list($settings['HTTP_SERVER_INT'], TRUE))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_targets') . ': HTTP_SERVER_INT');

        if (! $this->_is_safe_target_list($settings['PING_SERVER_INT'], FALSE))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_targets') . ': PING_SERVER_INT');

        if (! preg_match('/^[A-Za-z0-9_ .|:\/\\+*?()\[\]-]+$/', $settings['LOG_ANALYSIS_PATTERNS']))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_targets') . ': LOG_ANALYSIS_PATTERNS');

        $profiles = $this->_profile_lines($settings['OPENVPN_PROFILES_TEXT']);
        foreach ($profiles as $line) {
            if (! preg_match('/^[A-Za-z0-9_.,:@\/+=\- ]+$/', $line))
                throw new Validation_Exception(sprintf(lang('openvpn_watchdog_invalid_profile'), $line));

            $profile = $this->_parse_profile_line($line);
            if (! isset($profile['name']) || trim($profile['name']) === '')
                throw new Validation_Exception(sprintf(lang('openvpn_watchdog_profile_missing_name'), $line));
            if (! isset($profile['type']) || ! in_array(strtoupper($profile['type']), array('CLIENT', 'SERVER'), TRUE))
                throw new Validation_Exception(sprintf(lang('openvpn_watchdog_profile_missing_type'), $line));
        }
    }

    /**
     * Writes managed config.
     *
     * @param array $settings settings
     *
     * @return void
     * @throws Engine_Exception
     */

    protected function _write_config($settings)
    {
        $lines = array();
        $lines[] = '# /etc/openvpn-watchdog.conf';
        $lines[] = '# Managed by ClearOS app-openvpn-watchdog';
        $lines[] = '# Do not edit this file manually while using the Webconfig page.';
        $lines[] = '';
        $lines[] = 'OPENVPN_WATCHDOG_LANGUAGE=' . $this->_shell_quote($settings['OPENVPN_WATCHDOG_LANGUAGE']);
        $lines[] = 'OPENVPN_WATCHDOG_LOCALE_DIR=' . $this->_shell_quote($settings['OPENVPN_WATCHDOG_LOCALE_DIR']);
        $lines[] = '';
        $lines[] = 'OPENVPN_PROFILES=(';
        $profiles = $this->_profile_lines($settings['OPENVPN_PROFILES_TEXT']);
        foreach ($profiles as $profile)
            $lines[] = '    ' . $this->_shell_quote($profile);
        $lines[] = ')';
        $lines[] = '';
        $lines[] = 'LOG_DIR=' . $this->_shell_quote($settings['LOG_DIR']);
        $lines[] = 'STATE_DIR=' . $this->_shell_quote($settings['STATE_DIR']);
        $lines[] = 'LOG_RETENTION_DAYS=' . intval($settings['LOG_RETENTION_DAYS']);
        $lines[] = '';
        $lines[] = 'PING_COUNT=' . intval($settings['PING_COUNT']);
        $lines[] = 'PING_TIMEOUT=' . intval($settings['PING_TIMEOUT']);
        $lines[] = 'DEFAULT_RESTART_CYCLES=' . intval($settings['DEFAULT_RESTART_CYCLES']);
        $lines[] = 'RESTART_COOLDOWN_SECONDS=' . intval($settings['RESTART_COOLDOWN_SECONDS']);
        $lines[] = '';
        $lines[] = 'INTERNET_CHECK_ENABLED=' . $this->_shell_quote($settings['INTERNET_CHECK_ENABLED']);
        $lines[] = 'INTERNET_CHECK_METHOD=' . $this->_shell_quote($settings['INTERNET_CHECK_METHOD']);
        $lines[] = 'HTTP_SERVER_INT=' . $this->_shell_quote($settings['HTTP_SERVER_INT']);
        $lines[] = 'PING_SERVER_INT=' . $this->_shell_quote($settings['PING_SERVER_INT']);
        $lines[] = 'HTTP_CONNECT_TIMEOUT=' . intval($settings['HTTP_CONNECT_TIMEOUT']);
        $lines[] = 'HTTP_MAX_TIME=' . intval($settings['HTTP_MAX_TIME']);
        $lines[] = 'HTTP_IP_VERSION=' . $this->_shell_quote($settings['HTTP_IP_VERSION']);
        $lines[] = 'HTTP_REQUEST_METHOD=' . $this->_shell_quote($settings['HTTP_REQUEST_METHOD']);
        $lines[] = 'SKIP_CLIENT_PING_WHEN_INTERNET_DOWN=' . $this->_shell_quote($settings['SKIP_CLIENT_PING_WHEN_INTERNET_DOWN']);
        $lines[] = 'SKIP_CLIENT_CONNECTIVITY_WHEN_INTERNET_DOWN=' . $this->_shell_quote($settings['SKIP_CLIENT_CONNECTIVITY_WHEN_INTERNET_DOWN']);
        $lines[] = '';
        $lines[] = 'NOTIFICATIONS_ENABLED=' . $this->_shell_quote($settings['NOTIFICATIONS_ENABLED']);
        $lines[] = 'NOTIFY_SCRIPT=' . $this->_shell_quote($settings['NOTIFY_SCRIPT']);
        $lines[] = '';
        $lines[] = 'LOG_ANALYSIS_ENABLED=' . $this->_shell_quote($settings['LOG_ANALYSIS_ENABLED']);
        $lines[] = 'LOG_ANALYSIS_ON_PROBLEM_ONLY=' . $this->_shell_quote($settings['LOG_ANALYSIS_ON_PROBLEM_ONLY']);
        $lines[] = 'LOG_ANALYSIS_LINES=' . intval($settings['LOG_ANALYSIS_LINES']);
        $lines[] = 'LOG_ANALYSIS_MAX_MATCHES=' . intval($settings['LOG_ANALYSIS_MAX_MATCHES']);
        $lines[] = 'LOG_ANALYSIS_PATTERNS=' . $this->_shell_quote($settings['LOG_ANALYSIS_PATTERNS']);
        $lines[] = '';

        $contents = implode("\n", $lines);

        try {
            if (file_exists(self::FILE_CONFIG)) {
                try {
                    $source = new File(self::FILE_CONFIG, TRUE);
                    $source->copy_to(self::FILE_CONFIG . '.bak-' . date('Ymd-His'));
                    $this->_cleanup_config_backups(self::FILE_CONFIG);
                } catch (\Exception $e) {
                    // Helpful but not fatal.
                }
            }

            $target = new File(self::FILE_CONFIG, TRUE);
            if (! $target->exists())
                $target->create('root', 'root', '0644');

            $tempfile = tempnam(defined('CLEAROS_TEMP_DIR') ? CLEAROS_TEMP_DIR : sys_get_temp_dir(), 'app-openvpn-watchdog-');
            if ($tempfile === FALSE)
                throw new Engine_Exception(lang('base_file_write_error') . ': ' . self::FILE_CONFIG, CLEAROS_ERROR);

            if (file_put_contents($tempfile, $contents) === FALSE) {
                @unlink($tempfile);
                throw new Engine_Exception(lang('base_file_write_error') . ': ' . self::FILE_CONFIG, CLEAROS_ERROR);
            }

            $target->replace($tempfile);
            $target->chown('root', 'root');
            $target->chmod('0644');
        } catch (\Exception $e) {
            throw new Engine_Exception(lang('base_file_write_error') . ': ' . self::FILE_CONFIG, CLEAROS_ERROR);
        }

        $this->_cleanup_config_backups(self::FILE_CONFIG);
    }

    /**
     * Parses one structured event log line.
     *
     * Example line:
     * 2026-05-04T08:57:38+0300 severity=ERROR profile=office type=CLIENT service=openvpn-client@office-client.service event=service_inactive language=uk message=Service не активний.
     *
     * @param string $line event line
     *
     * @return array parsed event
     */

    protected function _parse_event_line($line)
    {
        $line = trim((string) $line);
        $event = array(
            'time' => '',
            'severity' => '',
            'profile' => '',
            'type' => '',
            'service' => '',
            'event' => '',
            'language' => '',
            'message' => '',
            'raw' => $line,
        );

        if ($line === '')
            return $event;

        $rest = $line;
        if (preg_match('/^(\S+)\s+(.*)$/', $line, $matches)) {
            $event['time'] = $matches[1];
            $rest = $matches[2];
        }

        if (! preg_match_all('/\b([A-Za-z0-9_]+)=/', $rest, $matches, PREG_OFFSET_CAPTURE)) {
            $event['message'] = $rest;
            return $event;
        }

        $count = count($matches[1]);
        for ($i = 0; $i < $count; $i++) {
            $key = strtolower($matches[1][$i][0]);
            $key_offset = $matches[1][$i][1];
            $value_start = $key_offset + strlen($matches[1][$i][0]) + 1;
            $value_end = ($i + 1 < $count) ? $matches[0][$i + 1][1] : strlen($rest);
            $value = trim(substr($rest, $value_start, $value_end - $value_start));

            if (array_key_exists($key, $event))
                $event[$key] = $value;
        }

        if ($event['message'] === '')
            $event['message'] = $rest;

        return $event;
    }

    /**
     * Extracts OPENVPN_PROFILES array as textarea content.
     *
     * @param array $lines config lines
     *
     * @return string profile lines
     */

    protected function _extract_profiles_text($lines)
    {
        $inside = FALSE;
        $profiles = array();

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (! $inside) {
                if (preg_match('/^OPENVPN_PROFILES\s*=\s*\(/', $trimmed))
                    $inside = TRUE;
                continue;
            }

            if ($trimmed === ')')
                break;

            if ($trimmed === '' || preg_match('/^#/', $trimmed))
                continue;

            $profiles[] = $this->_parse_shell_value($trimmed);
        }

        return implode("\n", $profiles);
    }

    /**
     * Parses a simple shell assignment value.
     *
     * @param string $value value
     *
     * @return string parsed value
     */

    protected function _parse_shell_value($value)
    {
        $value = trim((string) $value);
        if ($value === '')
            return '';

        $quote = substr($value, 0, 1);
        if ($quote === '"' || $quote === "'") {
            $end = strrpos($value, $quote);
            if ($end !== FALSE && $end > 0)
                $value = substr($value, 1, $end - 1);
            else
                $value = trim($value, $quote);

            if ($quote === '"')
                $value = str_replace(array('\\"', '\\\\'), array('"', '\\'), $value);

            return $value;
        }

        $value = preg_replace('/\s+#.*$/', '', $value);
        return trim($value);
    }

    /**
     * Returns clean profile lines.
     *
     * @param string $text text
     *
     * @return array lines
     */

    protected function _profile_lines($text)
    {
        $rows = preg_split('/\r\n|\r|\n/', (string) $text);
        $lines = array();
        if (! is_array($rows))
            return $lines;

        foreach ($rows as $row) {
            $row = trim($row);
            if ($row === '' || preg_match('/^#/', $row))
                continue;
            if ((substr($row, 0, 1) === '"' && substr($row, -1) === '"') || (substr($row, 0, 1) === "'" && substr($row, -1) === "'"))
                $row = $this->_parse_shell_value($row);
            $lines[] = $row;
        }

        return $lines;
    }

    /**
     * Normalizes profile data from Webconfig.
     *
     * @param array $profile profile
     *
     * @return array normalized profile
     */

    protected function _normalize_profile($profile)
    {
        if (! is_array($profile))
            $profile = array();

        $normalized = array(
            'name' => isset($profile['name']) ? trim((string) $profile['name']) : '',
            'type' => isset($profile['type']) ? strtoupper(trim((string) $profile['type'])) : 'CLIENT',
            'ping' => isset($profile['ping']) ? trim((string) $profile['ping']) : '',
            'restart_cycles' => isset($profile['restart_cycles']) ? trim((string) $profile['restart_cycles']) : '',
            'service' => isset($profile['service']) ? trim((string) $profile['service']) : '',
        );

        if (! in_array($normalized['type'], array('CLIENT', 'SERVER'), TRUE))
            $normalized['type'] = 'CLIENT';

        if ($normalized['restart_cycles'] === '') {
            $settings = $this->get_settings();
            $normalized['restart_cycles'] = isset($settings['DEFAULT_RESTART_CYCLES']) ? $settings['DEFAULT_RESTART_CYCLES'] : '3';
        }

        if ($normalized['type'] === 'SERVER')
            $normalized['ping'] = '';

        return $normalized;
    }

    /**
     * Validates one profile from Webconfig.
     *
     * @param array $profile        profile
     * @param int   $current_index  current index for duplicate-name checks
     * @param array $current        current profiles
     *
     * @return void
     * @throws Validation_Exception
     */

    protected function _validate_profile($profile, $current_index = NULL, $current = array())
    {
        if ($profile['name'] === '' || ! preg_match('/^[A-Za-z0-9_.-]+$/', $profile['name']))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_profile_name'));

        if (! in_array($profile['type'], array('CLIENT', 'SERVER'), TRUE))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_profile_type'));

        if ($profile['ping'] !== '' && ! $this->_is_safe_target_list($profile['ping'], FALSE))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_targets') . ': ping');

        if ($profile['restart_cycles'] !== '' && ! preg_match('/^[0-9]+$/', $profile['restart_cycles']))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_number') . ': restart_cycles');

        if ($profile['restart_cycles'] !== '') {
            $restart_cycles = intval($profile['restart_cycles']);
            if ($restart_cycles < 1 || $restart_cycles > 100)
                throw new Validation_Exception(lang('openvpn_watchdog_invalid_number') . ': restart_cycles');
        }

        if ($profile['service'] !== '' && ! preg_match('/^[A-Za-z0-9_.@\\-]+\.service$/', $profile['service']))
            throw new Validation_Exception(lang('openvpn_watchdog_invalid_service_name'));

        if (is_array($current)) {
            foreach ($current as $index => $item) {
                if ($current_index !== NULL && intval($index) === intval($current_index))
                    continue;

                if (isset($item['name']) && $item['name'] === $profile['name'])
                    throw new Validation_Exception(lang('openvpn_watchdog_duplicate_profile_name'));
            }
        }
    }

    /**
     * Converts profile data to openvpn-watchdog config line.
     *
     * @param array $profile profile
     *
     * @return string profile line
     */

    protected function _profile_to_line($profile)
    {
        $parts = array();
        $parts[] = 'name=' . $profile['name'];
        $parts[] = 'type=' . $profile['type'];

        if ($profile['type'] === 'CLIENT' && $profile['ping'] !== '')
            $parts[] = 'ping=' . $profile['ping'];

        if ($profile['restart_cycles'] !== '')
            $parts[] = 'restart_cycles=' . intval($profile['restart_cycles']);

        if ($profile['service'] !== '')
            $parts[] = 'service=' . $profile['service'];

        return implode(' ', $parts);
    }

    /**
     * Parses profile textarea into display rows.
     *
     * @param string $text profiles text
     *
     * @return array parsed profiles
     */

    protected function _parse_profiles_text($text)
    {
        $profiles = array();
        foreach ($this->_profile_lines($text) as $line) {
            $item = $this->_parse_profile_line($line);
            $item['_raw'] = $line;
            $profiles[] = $item;
        }
        return $profiles;
    }

    /**
     * Parses one profile line into key/value array.
     *
     * @param string $line line
     *
     * @return array profile
     */

    protected function _parse_profile_line($line)
    {
        $profile = array();
        $parts = preg_split('/\s+/', trim($line));
        if (! is_array($parts))
            return $profile;

        foreach ($parts as $part) {
            if (! preg_match('/^([A-Za-z0-9_\-]+)=(.*)$/', $part, $matches))
                continue;
            $profile[strtolower($matches[1])] = $matches[2];
        }

        return $profile;
    }

    /**
     * Returns quoted shell value.
     *
     * @param string $value value
     *
     * @return string quoted value
     */

    protected function _shell_quote($value)
    {
        return '"' . str_replace(array('\\', '"', '$', '`'), array('\\\\', '\\"', '\$', '\`'), (string) $value) . '"';
    }

    /**
     * Returns OpenVPN profiles discovered through privileged helper.
     *
     * Webconfig normally runs as the webconfig user and must not be added to
     * the openvpn group just to list profiles.  The helper runs as root through
     * sudoers and returns only safe profile names, not configuration contents.
     *
     * @return array profile names
     */

    protected function _get_openvpn_profiles_from_helper()
    {
        $profiles = array();

        try {
            $result = $this->_run_helper('list-profiles', TRUE);
        } catch (\Exception $e) {
            return $profiles;
        }

        if (! isset($result['output']) || ! is_array($result['output']))
            return $profiles;

        foreach ($result['output'] as $line) {
            $line = trim((string) $line);
            if ($line === '')
                continue;

            $parts = preg_split('/\s+/', $line);
            if (! is_array($parts) || count($parts) < 2)
                continue;

            $type = strtoupper(trim((string) $parts[0]));
            $name = trim((string) $parts[1]);

            if (! in_array($type, array('CLIENT', 'SERVER', 'LEGACY'), TRUE))
                continue;

            if ($name === '' || ! preg_match('/^[A-Za-z0-9_.-]+$/', $name))
                continue;

            $profiles[$name] = $name;
        }

        return $profiles;
    }

    /**
     * Finds OpenVPN config files.
     *
     * @param string  $profile_name        profile name
     * @param boolean $include_all_clients include all client configs
     *
     * @return array file paths
     */

    protected function _get_openvpn_config_files($profile_name = '', $include_all_clients = FALSE)
    {
        $files = array();
        $profile_name = trim((string) $profile_name);

        if ($profile_name !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $profile_name)) {
            $candidates = array(
                '/etc/openvpn/client/' . $profile_name . '-client.conf',
                '/etc/openvpn/server/' . $profile_name . '-server.conf',
                '/etc/openvpn/' . $profile_name . '.conf',
            );

            foreach ($candidates as $file) {
                if (is_file($file) && is_readable($file))
                    $files[$file] = $file;
            }
        }

        if ($include_all_clients) {
            $patterns = array(
                '/etc/openvpn/client/*-client.conf',
                '/etc/openvpn/*.conf',
            );

            foreach ($patterns as $pattern) {
                $glob = glob($pattern);
                if (! is_array($glob))
                    continue;

                foreach ($glob as $file) {
                    if (is_file($file) && is_readable($file))
                        $files[$file] = $file;
                }
            }
        }

        return array_values($files);
    }

    /**
     * Adds ping targets from comma-separated text.
     *
     * @param array  $targets target map
     * @param string $list    comma-separated targets
     *
     * @return void
     */

    protected function _add_ping_targets_from_list(&$targets, $list)
    {
        $items = explode(',', (string) $list);
        foreach ($items as $item)
            $this->_add_ping_target($targets, trim($item), lang('openvpn_watchdog_current_value'));
    }

    /**
     * Adds ping target if it is safe and useful.
     *
     * @param array  $targets target map
     * @param string $target  target value
     * @param string $source  source label
     *
     * @return void
     */

    protected function _add_ping_target(&$targets, $target, $source = '')
    {
        $target = trim((string) $target);
        $source = trim((string) $source);

        if ($target === '')
            return;

        $target = preg_replace('/\/.*$/', '', $target);
        $target = trim($target, " \t\r\n\"'");

        if (! preg_match('/^[A-Za-z0-9_.:-]+$/', $target))
            return;

        if (in_array(strtolower($target), array('dhcp', 'vpn_gateway', 'net_gateway', 'remote_host'), TRUE))
            return;

        if (preg_match('/^(0\.0\.0\.0|255\.255\.255\.255)$/', $target))
            return;

        if (preg_match('/^(255\.)/', $target))
            return;

        if ($source !== '')
            $targets[$target] = $target . ' — ' . $source;
        else
            $targets[$target] = $target;
    }

    /**
     * Adds ping targets parsed from an OpenVPN config file.
     *
     * @param array  $targets target map
     * @param string $file    config file path
     *
     * @return void
     */

    protected function _add_ping_targets_from_openvpn_config(&$targets, $file)
    {
        if (! is_file($file) || ! is_readable($file))
            return;

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines))
            return;

        $label = basename($file);

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || preg_match('/^[#;]/', $line))
                continue;

            if (preg_match('/^ifconfig\s+([^\s]+)\s+([^\s]+)/i', $line, $matches)) {
                $this->_add_ping_target($targets, $matches[2], $label . ' ifconfig peer');
                continue;
            }

            if (preg_match('/^ifconfig-push\s+([^\s]+)\s+([^\s]+)/i', $line, $matches)) {
                $this->_add_ping_target($targets, $matches[2], $label . ' ifconfig-push peer');
                continue;
            }

            if (preg_match('/^route-gateway\s+([^\s]+)/i', $line, $matches)) {
                $this->_add_ping_target($targets, $matches[1], $label . ' route-gateway');
                continue;
            }

            if (preg_match('/^dhcp-option\s+DNS\s+([^\s]+)/i', $line, $matches)) {
                $this->_add_ping_target($targets, $matches[1], $label . ' DNS');
                continue;
            }

            if (preg_match('/^server\s+([0-9.]+)\s+([0-9.]+)/i', $line, $matches)) {
                $first_host = $this->_first_host_ipv4($matches[1], $matches[2]);
                if ($first_host !== '')
                    $this->_add_ping_target($targets, $first_host, $label . ' server network');
                continue;
            }
        }
    }

    /**
     * Adds peer addresses from active tun/tap interfaces.
     *
     * @param array $targets target map
     *
     * @return void
     */

    protected function _add_ping_targets_from_ip_addr(&$targets)
    {
        $ip = file_exists('/usr/sbin/ip') ? '/usr/sbin/ip' : '/sbin/ip';
        if (! file_exists($ip))
            return;

        $output = $this->_run_command(escapeshellarg($ip) . ' -o -4 addr show 2>/dev/null', TRUE);
        if ($output === '')
            return;

        foreach (explode("\n", $output) as $line) {
            if (! preg_match('/\s(tun|tap)[A-Za-z0-9_.:-]*\s/', $line))
                continue;

            if (preg_match('/\speer\s+([0-9.]+)(?:\/\d+)?\s/', $line, $matches))
                $this->_add_ping_target($targets, $matches[1], 'active tun/tap peer');
        }
    }

    /**
     * Adds gateways from routes through tun/tap interfaces.
     *
     * @param array $targets target map
     *
     * @return void
     */

    protected function _add_ping_targets_from_ip_route(&$targets)
    {
        $ip = file_exists('/usr/sbin/ip') ? '/usr/sbin/ip' : '/sbin/ip';
        if (! file_exists($ip))
            return;

        $output = $this->_run_command(escapeshellarg($ip) . ' -4 route show 2>/dev/null', TRUE);
        if ($output === '')
            return;

        foreach (explode("\n", $output) as $line) {
            if (! preg_match('/\sdev\s+(tun|tap)[A-Za-z0-9_.:-]*/', $line))
                continue;

            if (preg_match('/\svia\s+([0-9.]+)\s+/', $line, $matches))
                $this->_add_ping_target($targets, $matches[1], 'route via tun/tap');
        }
    }

    /**
     * Returns first usable IPv4 host from network/netmask.
     *
     * @param string $network network address
     * @param string $netmask netmask
     *
     * @return string IPv4 address or empty string
     */

    protected function _first_host_ipv4($network, $netmask)
    {
        $network_long = ip2long($network);
        $mask_long = ip2long($netmask);

        if ($network_long === FALSE || $mask_long === FALSE)
            return '';

        $first = ($network_long & $mask_long) + 1;
        $result = long2ip($first);

        return ($result === FALSE) ? '' : $result;
    }

    /**
     * Checks a comma-separated target list.
     *
     * @param string  $list list
     * @param boolean $http TRUE for HTTP URLs
     *
     * @return bool valid
     */

    protected function _is_safe_target_list($list, $http)
    {
        $list = trim((string) $list);
        if ($list === '')
            return TRUE;

        $items = explode(',', $list);
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '')
                return FALSE;

            if ($http) {
                if (! preg_match('#^https?://[A-Za-z0-9_.:-]+(/[-A-Za-z0-9_./?=&%+~:@]*)?$#', $item))
                    return FALSE;
            } else {
                if (! preg_match('/^[A-Za-z0-9_.:-]+$/', $item))
                    return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Keeps only newest config backups.
     *
     * @param string $base_file base path
     *
     * @return void
     */

    protected function _cleanup_config_backups($base_file)
    {
        $files = glob($base_file . '.bak-*');
        if (! is_array($files) || count($files) <= self::CONFIG_BACKUP_KEEP)
            return;

        usort($files, function($a, $b) {
            $mtime_a = @filemtime($a);
            $mtime_b = @filemtime($b);
            if ($mtime_a == $mtime_b)
                return strcmp($b, $a);
            return ($mtime_a < $mtime_b) ? 1 : -1;
        });

        $old_files = array_slice($files, self::CONFIG_BACKUP_KEEP);
        foreach ($old_files as $file)
            @unlink($file);
    }

    /**
     * Runs privileged helper through sudo.
     *
     * @param string  $action        action
     * @param boolean $ignore_errors ignore non-zero exit code
     *
     * @return array result
     * @throws Engine_Exception
     */

    protected function _run_helper($action, $ignore_errors = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! preg_match('/^(dry-run|run-now|start|stop|restart|status|reset-failed|clear-events|list-profiles|check-permissions|fix-permissions)$/', $action))
            throw new Engine_Exception('Invalid helper action', CLEAROS_ERROR);

        if (! is_file(self::COMMAND_HELPER) || ! is_executable(self::COMMAND_HELPER))
            throw new Engine_Exception(lang('openvpn_watchdog_helper_missing'), CLEAROS_ERROR);

        $sudo = is_executable('/usr/bin/sudo') ? '/usr/bin/sudo' : '/bin/sudo';
        if (! is_executable($sudo))
            $sudo = 'sudo';

        $cmd = escapeshellcmd($sudo) . ' -n ' . escapeshellarg(self::COMMAND_HELPER) . ' ' . escapeshellarg($action) . ' 2>&1';

        $output = array();
        $exit_code = 0;
        exec($cmd, $output, $exit_code);

        $result = array(
            'exit_code' => $exit_code,
            'output' => $output,
            'cmd' => $cmd,
        );

        if (! $ignore_errors && $exit_code !== 0)
            throw new Engine_Exception($this->_format_command_error($result), CLEAROS_ERROR);

        return $result;
    }

    /**
     * Runs systemctl action.
     *
     * @param string  $action        action
     * @param string  $unit          unit name
     * @param boolean $ignore_errors ignore non-zero exit code
     *
     * @return array result
     * @throws Engine_Exception
     */

    protected function _systemctl($action, $unit = '', $ignore_errors = FALSE)
    {
        if (! preg_match('/^(is-active|is-enabled|enable|disable|start|stop|restart|status)$/', $action))
            throw new Engine_Exception('Invalid systemctl action', CLEAROS_ERROR);

        if ($unit !== '' && ! preg_match('/^[A-Za-z0-9_.@\\-]+$/', $unit))
            throw new Engine_Exception('Invalid systemd unit', CLEAROS_ERROR);

        $args = $action;
        if ($unit !== '')
            $args .= ' ' . $unit;

        return $this->_run_shell($this->_get_systemctl_command(), $args, $ignore_errors);
    }

    /**
     * Checks systemd state.
     *
     * @param string $unit unit
     * @param string $mode is-active or is-enabled
     *
     * @return bool state
     */

    protected function _is_systemd_state($unit, $mode)
    {
        if (! preg_match('/^(is-active|is-enabled)$/', $mode))
            return FALSE;

        $result = $this->_run_shell($this->_get_systemctl_command(), $mode . ' --quiet ' . $unit, TRUE);
        return ($result['exit_code'] === 0);
    }

    /**
     * Returns systemctl command path.
     *
     * @return string path
     */

    protected function _get_systemctl_command()
    {
        if (file_exists('/usr/bin/systemctl'))
            return '/usr/bin/systemctl';

        return '/bin/systemctl';
    }

    /**
     * Runs a command through ClearOS Shell.
     *
     * @param string  $command       command
     * @param string  $args          args
     * @param boolean $ignore_errors ignore errors
     *
     * @return array result
     * @throws Engine_Exception
     */

    protected function _run_shell($command, $args = '', $ignore_errors = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! file_exists($command)) {
            $result = array('exit_code' => 127, 'output' => array($command . ' not found'));
            if (! $ignore_errors)
                throw new Engine_Exception($this->_format_command_error($result), CLEAROS_ERROR);
            return $result;
        }

        $shell = new Shell();
        $options = array('validate_exit_code' => FALSE);
        $exit_code = $shell->execute($command, $args, TRUE, $options);
        $output = $shell->get_output();

        if (! is_array($output))
            $output = array($output);

        $result = array('exit_code' => $exit_code, 'output' => $output);
        if (! $ignore_errors && $exit_code !== 0)
            throw new Engine_Exception($this->_format_command_error($result), CLEAROS_ERROR);

        return $result;
    }

    /**
     * Runs shell command.
     *
     * @param string  $command       command line
     * @param boolean $ignore_errors ignore errors
     *
     * @return string output
     * @throws Engine_Exception
     */

    protected function _run_command($command, $ignore_errors = FALSE)
    {
        $output = array();
        $exit_code = 0;
        exec($command . ' 2>&1', $output, $exit_code);
        $result = trim(implode("\n", $output));

        if (! $ignore_errors && $exit_code !== 0)
            throw new Engine_Exception(($result === '' ? $command : $result), CLEAROS_ERROR);

        return $result;
    }

    /**
     * Formats command result.
     *
     * @param array $result result
     *
     * @return string text
     */

    protected function _format_command_error($result)
    {
        $output = isset($result['output']) && is_array($result['output']) ? $result['output'] : array();
        $text = trim(implode("\n", $output));

        if ($text === '')
            $text = 'exit=' . (isset($result['exit_code']) ? $result['exit_code'] : 'unknown');

        return $text;
    }

    /**
     * Converts yes/no-like values.
     *
     * @param string $value value
     *
     * @return bool enabled
     */

    protected function _is_enabled_value($value)
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, array('1', 'yes', 'true', 'on', 'enabled'), TRUE);
    }
}

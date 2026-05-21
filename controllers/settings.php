<?php

/**
 * OpenVPN Watchdog settings controller.
 *
 * @category   apps
 * @package    openvpn-watchdog
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class Settings extends ClearOS_Controller
{
    /**
     * Default view.
     *
     * @return redirect
     */

    function index()
    {
        redirect('/openvpn_watchdog');
    }

    /**
     * Edit global settings.
     *
     * @return view
     */

    function edit()
    {
        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        if ($this->input->post('submit')) {
            try {
                $settings = $this->_get_posted_settings();
                $this->openvpn_watchdog->set_settings($settings);
                $this->page->set_status_updated();
                redirect('/openvpn_watchdog');
                return;
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        } else {
            try {
                $settings = $this->openvpn_watchdog->get_settings();
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        $data['settings'] = $settings;
        $data['language_options'] = $this->openvpn_watchdog->get_language_options();
        $data['method_options'] = $this->openvpn_watchdog->get_internet_check_method_options();
        $data['ip_version_options'] = $this->openvpn_watchdog->get_http_ip_version_options();
        $data['request_method_options'] = $this->openvpn_watchdog->get_http_request_method_options();

        $this->page->view_form('openvpn_watchdog/settings', $data, lang('openvpn_watchdog_settings'));
    }

    /**
     * Add a new OpenVPN profile.
     *
     * @return view
     */

    function add()
    {
        $this->_view_profile(NULL);
    }

    /**
     * Edit an OpenVPN profile.
     *
     * @param int $index profile index
     *
     * @return view
     */

    function profile($index = NULL)
    {
        $this->_view_profile($index);
    }

    /**
     * Confirm profile deletion.
     *
     * @param int $index profile index
     *
     * @return view
     */

    function delete($index)
    {
        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        try {
            $profile = $this->openvpn_watchdog->get_profile($index);
            $name = isset($profile['name']) ? $profile['name'] : '';
            $type = isset($profile['type']) ? strtoupper($profile['type']) : '';

            $confirm_uri = '/app/openvpn_watchdog/settings/destroy/' . intval($index);
            $cancel_uri = '/app/openvpn_watchdog';
            $items = array(
                lang('openvpn_watchdog_profile') . ': ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '<br></li><li>' .
                lang('openvpn_watchdog_type') . ': ' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8')
            );

            $this->page->view_confirm_delete($confirm_uri, $cancel_uri, $items);
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Delete an OpenVPN profile.
     *
     * @param int $index profile index
     *
     * @return redirect
     */

    function destroy($index)
    {
        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        try {
            $this->openvpn_watchdog->delete_profile($index);
            $this->page->set_status_deleted();
            redirect('/openvpn_watchdog');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Common add/edit profile form.
     *
     * @param int $index profile index or NULL for new profile
     *
     * @return view
     */

    protected function _view_profile($index = NULL)
    {
        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        $is_new = ($index === NULL || $index === '');

        if ($this->input->post('submit')) {
            try {
                $profile = $this->_get_posted_profile();

                if ($is_new)
                    $this->openvpn_watchdog->add_profile($profile);
                else
                    $this->openvpn_watchdog->update_profile($index, $profile);

                $this->page->set_status_updated();
                redirect('/openvpn_watchdog');
                return;
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        try {
            if ($is_new) {
                $settings = $this->openvpn_watchdog->get_settings();
                $profile = array(
                    'name' => '',
                    'type' => 'CLIENT',
                    'ping' => '',
                    'restart_cycles' => isset($settings['DEFAULT_RESTART_CYCLES']) ? $settings['DEFAULT_RESTART_CYCLES'] : '3',
                    'service' => '',
                );
                $form_action = 'openvpn_watchdog/settings/add';
                $title = lang('openvpn_watchdog_add_profile');
            } else {
                $profile = $this->openvpn_watchdog->get_profile($index);
                $form_action = 'openvpn_watchdog/settings/profile/' . intval($index);
                $title = lang('openvpn_watchdog_edit_profile');
            }

            $data['profile'] = $profile;
            $data['type_options'] = $this->openvpn_watchdog->get_profile_type_options();
            $data['profile_name_options'] = $this->openvpn_watchdog->get_available_profile_name_options(isset($profile['name']) ? $profile['name'] : '');
            $data['ping_target_suggestions'] = $this->openvpn_watchdog->get_vpn_ping_target_suggestions(
                isset($profile['name']) ? $profile['name'] : '',
                isset($profile['ping']) ? $profile['ping'] : ''
            );
            $data['is_new'] = $is_new;
            $data['form_action'] = $form_action;
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $this->page->view_form('openvpn_watchdog/profile', $data, $title);
    }

    /**
     * Returns settings from POST data.
     *
     * @return array settings
     */

    protected function _get_posted_settings()
    {
        $current_settings = array();
        try {
            $current_settings = $this->openvpn_watchdog->get_settings();
        } catch (Exception $e) {
            $current_settings = array();
        }

        $locale_dir = isset($current_settings['OPENVPN_WATCHDOG_LOCALE_DIR']) ? $current_settings['OPENVPN_WATCHDOG_LOCALE_DIR'] : '/usr/share/openvpn-watchdog/locale';
        $profiles_text = isset($current_settings['OPENVPN_PROFILES_TEXT']) ? $current_settings['OPENVPN_PROFILES_TEXT'] : '';
        $log_dir = isset($current_settings['LOG_DIR']) ? $current_settings['LOG_DIR'] : '/var/log/openvpn-watchdog';
        $state_dir = isset($current_settings['STATE_DIR']) ? $current_settings['STATE_DIR'] : '/var/lib/openvpn-watchdog';

        return array(
            'OPENVPN_WATCHDOG_LANGUAGE' => trim((string) $this->input->post('OPENVPN_WATCHDOG_LANGUAGE')),
            'OPENVPN_WATCHDOG_LOCALE_DIR' => $locale_dir,
            'OPENVPN_PROFILES_TEXT' => $profiles_text,
            'LOG_DIR' => $log_dir,
            'STATE_DIR' => $state_dir,
            'LOG_RETENTION_DAYS' => trim((string) $this->input->post('LOG_RETENTION_DAYS')),
            'PING_COUNT' => trim((string) $this->input->post('PING_COUNT')),
            'PING_TIMEOUT' => trim((string) $this->input->post('PING_TIMEOUT')),
            'DEFAULT_RESTART_CYCLES' => trim((string) $this->input->post('DEFAULT_RESTART_CYCLES')),
            'RESTART_COOLDOWN_SECONDS' => trim((string) $this->input->post('RESTART_COOLDOWN_SECONDS')),
            'INTERNET_CHECK_ENABLED' => $this->input->post('INTERNET_CHECK_ENABLED') ? 'YES' : 'NO',
            'INTERNET_CHECK_METHOD' => trim((string) $this->input->post('INTERNET_CHECK_METHOD')),
            'HTTP_SERVER_INT' => trim((string) $this->input->post('HTTP_SERVER_INT')),
            'PING_SERVER_INT' => trim((string) $this->input->post('PING_SERVER_INT')),
            'HTTP_CONNECT_TIMEOUT' => trim((string) $this->input->post('HTTP_CONNECT_TIMEOUT')),
            'HTTP_MAX_TIME' => trim((string) $this->input->post('HTTP_MAX_TIME')),
            'HTTP_IP_VERSION' => trim((string) $this->input->post('HTTP_IP_VERSION')),
            'HTTP_REQUEST_METHOD' => trim((string) $this->input->post('HTTP_REQUEST_METHOD')),
            'SKIP_CLIENT_PING_WHEN_INTERNET_DOWN' => $this->input->post('SKIP_CLIENT_PING_WHEN_INTERNET_DOWN') ? 'YES' : 'NO',
            'SKIP_CLIENT_CONNECTIVITY_WHEN_INTERNET_DOWN' => '',
            'NOTIFICATIONS_ENABLED' => $this->input->post('NOTIFICATIONS_ENABLED') ? 'YES' : 'NO',
            'NOTIFY_SCRIPT' => trim((string) $this->input->post('NOTIFY_SCRIPT')),
            'LOG_ANALYSIS_ENABLED' => $this->input->post('LOG_ANALYSIS_ENABLED') ? 'YES' : 'NO',
            'LOG_ANALYSIS_ON_PROBLEM_ONLY' => $this->input->post('LOG_ANALYSIS_ON_PROBLEM_ONLY') ? 'YES' : 'NO',
            'LOG_ANALYSIS_LINES' => trim((string) $this->input->post('LOG_ANALYSIS_LINES')),
            'LOG_ANALYSIS_MAX_MATCHES' => trim((string) $this->input->post('LOG_ANALYSIS_MAX_MATCHES')),
            'LOG_ANALYSIS_PATTERNS' => trim((string) $this->input->post('LOG_ANALYSIS_PATTERNS')),
        );
    }

    /**
     * Returns profile data from POST.
     *
     * @return array profile
     */

    protected function _get_posted_profile()
    {
        return array(
            'name' => trim((string) $this->input->post('name')),
            'type' => trim((string) $this->input->post('type')),
            'ping' => trim((string) $this->input->post('ping')),
            'restart_cycles' => trim((string) $this->input->post('restart_cycles')),
            'service' => trim((string) $this->input->post('service')),
        );
    }
}

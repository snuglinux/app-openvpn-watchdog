<?php

/**
 * OpenVPN Watchdog main controller.
 *
 * @category   apps
 * @package    openvpn-watchdog
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class Openvpn_Watchdog extends ClearOS_Controller
{
    /**
     * Main summary page.
     *
     * @return view
     */

    function index()
    {
        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        try {
            $data = $this->_get_summary_data();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $options['javascript'] = array(clearos_app_htdocs('base') . '/daemon.js.php');
        $this->page->view_form('openvpn_watchdog/summary', $data, lang('openvpn_watchdog_app_name'), $options);
    }

    /**
     * Shows recent structured events on a full-width page.
     *
     * @return view
     */

    function events()
    {
        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        try {
            $data['events'] = $this->openvpn_watchdog->get_recent_event_rows(200);
            $data['event_log'] = $this->openvpn_watchdog->get_event_log_file();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Do not load daemon.js.php and do not print daemon hidden fields here.
        // This keeps the right-side Status/Action daemon box away from the
        // event table and leaves more horizontal space for messages.
        $this->page->view_form('openvpn_watchdog/events', $data, '📋 ' . lang('openvpn_watchdog_recent_events'));
    }

    /**
     * Clears structured event log after browser confirmation.
     *
     * @return redirect
     */

    function clear_events()
    {
        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        try {
            $this->openvpn_watchdog->clear_events();
            $this->page->set_status_updated();
            redirect('/openvpn_watchdog/events');
            return;
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Runs a read-only dry run.
     *
     * @return view
     */

    function dry_run()
    {
        $this->_run_watchdog_action(TRUE);
    }

    /**
     * Runs watchdog now. This can restart broken OpenVPN profiles.
     *
     * @return view
     */

    function run_now()
    {
        $this->_run_watchdog_action(FALSE);
    }

    /**
     * Runs watchdog action and returns summary page.
     *
     * @param boolean $dry TRUE for dry-run
     *
     * @return view
     */

    protected function _run_watchdog_action($dry)
    {
        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        try {
            $data = $this->_get_summary_data();
            $data['action_output'] = $dry ? $this->openvpn_watchdog->dry_run() : $this->openvpn_watchdog->run_now();
            $data['action_status'] = 'success';
            $data['action_title'] = $dry ? ('🧪 ' . lang('openvpn_watchdog_dry_run')) : ('▶ ' . lang('openvpn_watchdog_run_now'));
            $data = array_merge($this->_get_summary_data(), $data);
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $options['javascript'] = array(clearos_app_htdocs('base') . '/daemon.js.php');
        $this->page->view_form('openvpn_watchdog/summary', $data, lang('openvpn_watchdog_app_name'), $options);
    }

    /**
     * Gets summary page data.
     *
     * @return array summary data
     */

    protected function _get_summary_data()
    {
        $data = array();

        $data['settings'] = $this->openvpn_watchdog->get_settings();
        $data['profiles'] = $this->openvpn_watchdog->get_profiles();
        $data['watchdog_version'] = $this->openvpn_watchdog->get_watchdog_version();
        $data['config_file'] = $this->openvpn_watchdog->get_config_file();
        $data['service_summary'] = $this->openvpn_watchdog->get_service_summary();
        $data['config_warnings'] = $this->openvpn_watchdog->get_config_warnings();
        $data['method_options'] = $this->openvpn_watchdog->get_internet_check_method_options();

        return $data;
    }
}

<?php

/**
 * OpenVPN Watchdog daemon/timer controller.
 *
 * Exposes standard ClearOS daemon.js.php endpoints:
 *   /app/openvpn_watchdog/server/status/openvpn-watchdog
 *   /app/openvpn_watchdog/server/start/openvpn-watchdog
 *   /app/openvpn_watchdog/server/stop/openvpn-watchdog
 *
 * @category   apps
 * @package    openvpn-watchdog
 * @subpackage controllers
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

class Server extends ClearOS_Controller
{
    /**
     * Hidden fields for ClearOS daemon sidebar integration.
     *
     * @return view
     */

    function index()
    {
        $this->lang->load('base');

        $data['daemon_name'] = 'openvpn-watchdog';
        $data['app_name'] = 'openvpn_watchdog';

        $options['javascript'] = array(clearos_app_htdocs('base') . '/daemon.js.php');
        $this->page->view_form('base/daemon', $data, lang('base_server_status'), $options);
    }

    /**
     * Timer status for daemon.js.php.
     *
     * @param string $daemon_name ignored
     *
     * @return JSON
     */

    function status($daemon_name = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        try {
            echo json_encode(array('status' => $this->openvpn_watchdog->get_daemon_status()));
        } catch (Exception $e) {
            echo json_encode(array('status' => 'dead'));
        }
    }

    /**
     * Start and enable timer.
     *
     * @param string $daemon_name ignored
     *
     * @return JSON
     */

    function start($daemon_name = NULL)
    {
        $this->_run_action(TRUE);
    }

    /**
     * Stop and disable timer.
     *
     * @param string $daemon_name ignored
     *
     * @return JSON
     */

    function stop($daemon_name = NULL)
    {
        $this->_run_action(FALSE);
    }

    /**
     * Runs timer action.
     *
     * @param boolean $start TRUE to start/enable, FALSE to stop/disable
     *
     * @return JSON
     */

    protected function _run_action($start)
    {
        clearos_profile(__METHOD__, __LINE__);

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        $this->lang->load('base');
        $this->lang->load('openvpn_watchdog');
        $this->load->library('openvpn_watchdog/Openvpn_Watchdog');

        try {
            if ($start)
                $this->openvpn_watchdog->start_and_enable_service();
            else
                $this->openvpn_watchdog->stop_and_disable_service();

            echo json_encode('ok');
        } catch (Exception $e) {
            echo json_encode('error');
        }
    }
}

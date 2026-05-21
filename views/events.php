<?php

/**
 * OpenVPN Watchdog recent events view.
 *
 * @category   apps
 * @package    openvpn-watchdog
 * @subpackage views
 * @author     SnugLinux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 */

$this->lang->load('base');
$this->lang->load('openvpn_watchdog');

if (! isset($events))
    $events = array();

if (! function_exists('openvpn_watchdog_events_escape')) {
    function openvpn_watchdog_events_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('openvpn_watchdog_events_format_severity')) {
    function openvpn_watchdog_events_format_severity($severity)
    {
        $severity = strtoupper(trim((string) $severity));

        switch ($severity) {
            case 'INFO':
                return 'ℹ️ INFO';
            case 'ERROR':
                return '⛔ ERROR';
            case 'WARNING':
            case 'WARN':
                return '⚠️ WARNING';
            case 'CRITICAL':
                return '🚨 CRITICAL';
            case 'DEBUG':
                return '🐞 DEBUG';
            default:
                return ($severity === '') ? '-' : $severity;
        }
    }
}


if (! function_exists('openvpn_watchdog_events_format_time')) {
    function openvpn_watchdog_events_format_time($time)
    {
        $time = trim((string) $time);
        if ($time === '')
            return '-';

        // openvpn-watchdog writes ISO-like values, for example:
        //   2026-05-04T15:23:00+0300
        // Show a more readable local date/time but keep the original value for
        // DataTables sorting via data-order.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:[+-]\d{4}|Z)?$/', $time, $matches))
            return $matches[1] . ' ' . $matches[2];

        return str_replace('T', ' ', preg_replace('/([+-]\d{4}|Z)$/', '', $time));
    }
}

if (is_array($events)) {
    usort($events, function($a, $b) {
        $time_a = isset($a['time']) ? (string) $a['time'] : '';
        $time_b = isset($b['time']) ? (string) $b['time'] : '';
        return strcmp($time_b, $time_a);
    });
} else {
    $events = array();
}

$items = array();

foreach ($events as $event) {
    $severity = isset($event['severity']) ? strtoupper($event['severity']) : '';
    $message = isset($event['message']) ? trim((string) $event['message']) : '';

    if ($message === '' && isset($event['raw']))
        $message = $event['raw'];

    $event_name = isset($event['event']) ? trim((string) $event['event']) : '';
    $service = isset($event['service']) ? trim((string) $event['service']) : '';

    $message_html = '<span class="openvpn-watchdog-event-message">' . openvpn_watchdog_events_escape($message) . '</span>';

    if ($event_name !== '' || $service !== '') {
        $meta = array();
        if ($event_name !== '')
            $meta[] = 'event=' . $event_name;
        if ($service !== '')
            $meta[] = 'service=' . $service;

        $message_html .= '<br><small class="text-muted openvpn-watchdog-event-meta">' . openvpn_watchdog_events_escape(implode(' · ', $meta)) . '</small>';
    }

    $items[] = array(
        'details' => array(
            '<span style="white-space: nowrap;" data-order="' . openvpn_watchdog_events_escape(isset($event['time']) ? $event['time'] : '') . '">' . openvpn_watchdog_events_escape(openvpn_watchdog_events_format_time(isset($event['time']) ? $event['time'] : '')) . '</span>',
            '<span style="white-space: nowrap; font-weight: bold;">' . openvpn_watchdog_events_escape(openvpn_watchdog_events_format_severity($severity)) . '</span>',
            openvpn_watchdog_events_escape(isset($event['profile']) ? $event['profile'] : ''),
            openvpn_watchdog_events_escape(isset($event['type']) ? $event['type'] : ''),
            $message_html,
        ),
    );
}

echo '<div id="openvpn-watchdog-events-page">';

echo '<style>' .
    '#openvpn-watchdog-events-page {' .
    'width:100%; max-width:100%;' .
    '}' .
    '#openvpn-watchdog-events-page .table,' .
    '#openvpn-watchdog-events-page table,' .
    '#openvpn-watchdog-events-page .dataTables_wrapper {' .
    'width:100% !important; max-width:100% !important;' .
    '}' .
    '#openvpn-watchdog-events-page table {' .
    'table-layout:auto !important;' .
    '}' .
    '#openvpn-watchdog-events-page table td,' .
    '#openvpn-watchdog-events-page table th {' .
    'vertical-align:top !important;' .
    '}' .
    '#openvpn-watchdog-events-page table td:nth-child(1),' .
    '#openvpn-watchdog-events-page table th:nth-child(1) {' .
    'width:190px; white-space:nowrap !important;' .
    '}' .
    '#openvpn-watchdog-events-page table td:nth-child(2),' .
    '#openvpn-watchdog-events-page table th:nth-child(2) {' .
    'width:95px; white-space:nowrap !important;' .
    '}' .
    '#openvpn-watchdog-events-page table td:nth-child(3),' .
    '#openvpn-watchdog-events-page table th:nth-child(3) {' .
    'width:120px; white-space:nowrap !important;' .
    '}' .
    '#openvpn-watchdog-events-page table td:nth-child(4),' .
    '#openvpn-watchdog-events-page table th:nth-child(4) {' .
    'width:90px; white-space:nowrap !important;' .
    '}' .
    '#openvpn-watchdog-events-page table td:nth-child(5),' .
    '#openvpn-watchdog-events-page table th:nth-child(5) {' .
    'min-width:420px; width:auto; white-space:normal !important; word-break:break-word;' .
    '}' .
    '#openvpn-watchdog-events-page .openvpn-watchdog-event-message {' .
    'display:block; white-space:normal; word-break:break-word; line-height:1.35;' .
    '}' .
    '#openvpn-watchdog-events-page .openvpn-watchdog-event-meta {' .
    'display:block; margin-top:3px; white-space:normal; word-break:break-word;' .
    '}' .
    '</style>';

echo infobox_highlight(
    lang('base_information'),
    openvpn_watchdog_events_escape(lang('openvpn_watchdog_events_page_help'))
);

$buttons = array(
    anchor_custom('/app/openvpn_watchdog', '🔙 ' . lang('openvpn_watchdog_return_to_summary')),
    anchor_custom('/app/openvpn_watchdog/events', '🔄 ' . lang('openvpn_watchdog_refresh')),
    '<button type="button" class="btn btn-primary" onclick="if (confirm(\'' . openvpn_watchdog_events_escape(lang('openvpn_watchdog_clear_events_confirm')) . '\')) window.location.href=\'/app/openvpn_watchdog/clear_events\'; return false;">' . openvpn_watchdog_events_escape('🧹 ' . lang('openvpn_watchdog_clear_events')) . '</button>',
);

if (count($items) === 0) {
    echo infobox_warning(lang('base_warning'), lang('openvpn_watchdog_no_recent_events'));
}

echo summary_table(
    '📋 ' . lang('openvpn_watchdog_recent_events'),
    $buttons,
    array(
        'Час',
        'Рівень',
        'Профіль',
        'Тип',
        'Повідомлення',
    ),
    $items,
    array('no_action' => TRUE)
);

echo '</div>';

// ClearOS normally renders an app information card on the right side of app
// pages.  The event log needs the full page width, so hide that side card only
// on this view and expand the main content column.
//
// Do this in JavaScript instead of changing global ClearOS templates: it is
// local to /app/openvpn_watchdog/events and safe for the other app pages.
echo "<script>\n";
echo "(function(){\n";
echo "  function hasBootstrapColumnClass(node){\n";
echo "    return node && node.className && /\\bcol-(xs|sm|md|lg)-[0-9]+\\b/.test(String(node.className));\n";
echo "  }\n";
echo "  function findMainColumn(page){\n";
echo "    var node = page ? page.parentNode : null;\n";
echo "    while (node && node !== document.body) {\n";
echo "      if (hasBootstrapColumnClass(node)) return node;\n";
echo "      node = node.parentNode;\n";
echo "    }\n";
echo "    return null;\n";
echo "  }\n";
echo "  function makeFullWidth(){\n";
echo "    var page = document.getElementById('openvpn-watchdog-events-page');\n";
echo "    var main = findMainColumn(page);\n";
echo "    if (!main || !main.parentNode) return;\n";
echo "\n";
echo "    var row = main.parentNode;\n";
echo "    var children = row.children || [];\n";
echo "    for (var i = 0; i < children.length; i++) {\n";
echo "      var child = children[i];\n";
echo "      if (child === main) continue;\n";
echo "      var text = child.textContent || '';\n";
echo "      if (text.indexOf('OpenVPN Watchdog') !== -1 || text.indexOf('Powered By') !== -1 || text.indexOf('Additional Info') !== -1 || text.indexOf('Статус') !== -1 || text.indexOf('Дія') !== -1)\n";
echo "        child.style.display = 'none';\n";
echo "    }\n";
echo "\n";
echo "    main.className = String(main.className).replace(/\\bcol-(xs|sm|md|lg)-[0-9]+\\b/g, 'col-$1-12');\n";
echo "    main.style.width = '100%';\n";
echo "    main.style.maxWidth = '100%';\n";
echo "    main.style.flex = '0 0 100%';\n";
echo "  }\n";
echo "\n";
echo "  function localizeOpenvpnWatchdogDataTable(){\n";
echo "    if (!window.jQuery) return;\n";
echo "    var page = jQuery('#openvpn-watchdog-events-page');\n";
echo "    page.find('.dataTables_filter label').contents().filter(function(){ return this.nodeType === 3; }).each(function(){ this.nodeValue = 'Пошук '; });\n";
echo "    page.find('.dataTables_filter input').attr('placeholder', 'Пошук');\n";
echo "    page.find('.dataTables_length label').contents().filter(function(){ return this.nodeType === 3; }).each(function(){\n";
echo "      this.nodeValue = this.nodeValue.replace(/Show|Показати/gi, 'Показати').replace(/entries|Rows|Рядки/gi, ' рядків');\n";
echo "    });\n";
echo "    page.find('.dataTables_info').each(function(){\n";
echo "      var text = jQuery(this).text();\n";
echo "      var m = text.match(/Showing\s+(\d+)\s+to\s+(\d+)\s+of\s+(\d+)\s+entries/i);\n";
echo "      if (m) jQuery(this).text('Показано ' + m[1] + '–' + m[2] + ' із ' + m[3] + ' записів');\n";
echo "    });\n";
echo "  }\n";
echo "\n";
echo "  function orderOpenvpnWatchdogEvents(){\n";
echo "    if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) return;\n";
echo "    var table = jQuery('#openvpn-watchdog-events-page table').last();\n";
echo "    if (!table.length) return;\n";
echo "    if (jQuery.fn.dataTable.isDataTable(table)) {\n";
echo "      table.DataTable().columns.adjust().order([[0, 'desc']]).draw(false);\n";
echo "      table.on('draw.dt', function(){ setTimeout(localizeOpenvpnWatchdogDataTable, 10); });\n";
echo "    }\n";
echo "    localizeOpenvpnWatchdogDataTable();\n";
echo "  }\n";
echo "\n";
echo "  function applyOpenvpnWatchdogEventsLayout(){\n";
echo "    makeFullWidth();\n";
echo "    orderOpenvpnWatchdogEvents();\n";
echo "  }\n";
echo "\n";
echo "  if (window.jQuery) {\n";
echo "    jQuery(function(){\n";
echo "      applyOpenvpnWatchdogEventsLayout();\n";
echo "      setTimeout(applyOpenvpnWatchdogEventsLayout, 400);\n";
echo "      setTimeout(applyOpenvpnWatchdogEventsLayout, 1200);\n";
echo "    });\n";
echo "  } else {\n";
echo "    if (document.addEventListener)\n";
echo "      document.addEventListener('DOMContentLoaded', applyOpenvpnWatchdogEventsLayout);\n";
echo "  }\n";
echo "})();\n";
echo "</script>\n";

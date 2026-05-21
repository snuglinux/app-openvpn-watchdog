<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'openvpn_watchdog';
$app['version'] = '0.1.12';
$app['release'] = '1';
$app['vendor'] = 'SnugLinux';
$app['packager'] = 'SnugLinux';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('openvpn_watchdog_app_description');
$app['tooltip'] = lang('openvpn_watchdog_app_tooltip');

$app['powered_by'] = array(
    'vendor' => NULL,
    'packages' => array(
        'openvpn-watchdog' => array(
            'name' => 'openvpn-watchdog',
            'version' => '---',
            'url' => 'https://github.com/snuglinux/openvpn-watchdog',
        ),
    ),
);

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('openvpn_watchdog_app_name');
$app['category'] = lang('base_category_network');
$app['subcategory'] = lang('base_subcategory_vpn');

/////////////////////////////////////////////////////////////////////////////
// Controllers
/////////////////////////////////////////////////////////////////////////////

$app['controllers']['openvpn_watchdog']['title'] = lang('openvpn_watchdog_app_name');
$app['controllers']['settings']['title'] = lang('base_settings');
$app['controllers']['server']['title'] = lang('base_server_status');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['requires'] = array(
    'app-base',
);

$app['core_requires'] = array(
    'app-base-core',
    'openvpn-watchdog',
    'sudo',
);

$app['core_directory_manifest'] = array(
    '/var/clearos/openvpn_watchdog' => array(
        'mode' => '0755',
        'owner' => 'root',
        'group' => 'root',
    ),
);

// Do not remove openvpn-watchdog on app removal. It may be used outside Webconfig.
$app['delete_dependency'] = array(
    'app-openvpn-watchdog-core',
);

<?php

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_page']['useComatrack']       = array('Activate background tracking', '');
$GLOBALS['TL_LANG']['tl_page']['comatrack_url']      = array('Matomo installation URL', 'Enter the base address of the Matomo installation (e.g. https://matomo.example.com)');
$GLOBALS['TL_LANG']['tl_page']['comatrack_id']       = array('Matomo Website ID', 'Enter the ID of the website created in Matomo here');
$GLOBALS['TL_LANG']['tl_page']['comatrack_token']    = array('Matomo Security Token', 'The token is used for IP filtering and GeoIP information (if available).');
$GLOBALS['TL_LANG']['tl_page']['comatrack_debug']    = array('DEBUG Mode', 'Caution. In DEBUG mode, a lot of data is reported in the system log. In active operation, always deactivate it!');
$GLOBALS['TL_LANG']['tl_page']['comatrack_404']      = array('Track 404 errors', 'Do you want Matomo to track accesses to error pages, i.e. when the URL is unknown?');
$GLOBALS['TL_LANG']['tl_page']['comatrack_dnt']      = array('Respect "DoNotTrack" header', 'Users can set a "DoNotTrack" information in the browser. Do you want to respect it?');
$GLOBALS['TL_LANG']['tl_page']['comatrack_dim_dnt']  = array('Additionnal info for DoNotTrack', 'If DoNotTrack is not to be taken into account but this information is to be stored in a CustomDimension, please enter the dimension ID here.');
$GLOBALS['TL_LANG']['tl_page']['comatrack_ip']       = array('Give the IP to Matomo', 'Should the IP address be forwarded to Matomo? Recommendation: no forwarding, follow IP as 127.0.0.1');

/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_page']['comatrack_legend']      = 'Background tracking with Matomo';

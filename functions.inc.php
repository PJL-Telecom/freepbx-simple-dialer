<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/**
 * Simple Dialer Module Functions
 */

function simpledialer_get_config($engine) {
    global $ext;
    
    switch($engine) {
        case "asterisk":
            // Add dialplan context for Simple Dialer
            $ext->addInclude('from-internal', 'simpledialer-outbound');
            break;
    }
}

function simpledialer_hook_core($viewing_itemid, $request) {
    // Hook into core for additional functionality
    return '';
}
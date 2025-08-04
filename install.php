<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db;
global $amp_conf;

// Create sounds directory
$sounds_dir = '/var/lib/asterisk/sounds/custom/simpledialer';
if (!is_dir($sounds_dir)) {
    mkdir($sounds_dir, 0755, true);
    chown($sounds_dir, 'asterisk');
    chgrp($sounds_dir, 'asterisk');
}

// Copy sample audio files from original dialer if they exist
$original_sounds = array('/opt/simple-dialer/my_message.wav', '/opt/simple-dialer/test_message.wav');
foreach ($original_sounds as $original_file) {
    if (file_exists($original_file)) {
        $filename = basename($original_file);
        $dest_file = $sounds_dir . '/' . $filename;
        if (!file_exists($dest_file)) {
            copy($original_file, $dest_file);
            chown($dest_file, 'asterisk');
            chgrp($dest_file, 'asterisk');
        }
    }
}

// Make daemon script executable
$daemon_script = __DIR__ . '/bin/simpledialer_daemon.php';
if (file_exists($daemon_script)) {
    chmod($daemon_script, 0755);
}

out(_('Simple Dialer module installed successfully'));
out(_('Sounds directory created at: ') . $sounds_dir);
out(_('Sample audio files copied if available'));
out(_('Daemon script made executable'));
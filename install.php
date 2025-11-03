<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db;
global $amp_conf;

// Create database tables
$sql = "CREATE TABLE IF NOT EXISTS simpledialer_campaigns (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    audio_file VARCHAR(255),
    trunk VARCHAR(100),
    caller_id VARCHAR(20),
    max_concurrent INT(11) DEFAULT 5,
    delay_between_calls INT(11) DEFAULT 2,
    scheduled_time DATETIME,
    status VARCHAR(20) DEFAULT 'inactive',
    created_at DATETIME,
    updated_at DATETIME,
    PRIMARY KEY (id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$db->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS simpledialer_contacts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    campaign_id INT(11) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    name VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending',
    call_attempts INT(11) DEFAULT 0,
    last_attempt DATETIME,
    created_at DATETIME,
    PRIMARY KEY (id),
    KEY idx_campaign (campaign_id),
    KEY idx_status (status),
    FOREIGN KEY (campaign_id) REFERENCES simpledialer_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$db->query($sql);

$sql = "CREATE TABLE IF NOT EXISTS simpledialer_call_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    campaign_id INT(11) NOT NULL,
    contact_id INT(11) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    call_id VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL,
    duration INT(11) NOT NULL DEFAULT 0,
    answer_time DATETIME,
    hangup_time DATETIME,
    hangup_cause VARCHAR(50) NOT NULL DEFAULT '',
    voicemail_detected TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME,
    PRIMARY KEY (id),
    KEY idx_campaign (campaign_id),
    KEY idx_contact (contact_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$db->query($sql);

out(_('Database tables created successfully'));

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

// Install SimpleDialer dialplan context as separate file
$extensions_file = '/etc/asterisk/extensions_simpledialer.conf';
$module_context_file = __DIR__ . '/extensions_simpledialer.conf';

if (file_exists($module_context_file)) {
    copy($module_context_file, $extensions_file);
    out(_('AMD dialplan context installed to extensions_simpledialer.conf'));
} else {
    out(_('Warning: extensions_simpledialer.conf template not found in module'));
}

// Add include to extensions_custom.conf if not already present
$extensions_custom_conf = '/etc/asterisk/extensions_custom.conf';
$include_line = '#include extensions_simpledialer.conf';

// Ensure the file exists before trying to read/write it
if (!file_exists($extensions_custom_conf)) {
    touch($extensions_custom_conf);
}

$content = file_get_contents($extensions_custom_conf);

if (strpos($content, $include_line) === false) {
    // Append the include line at the end of the file, with newlines to be safe
    file_put_contents($extensions_custom_conf, "\n" . $include_line . "\n", FILE_APPEND);
    out(_('Added include to extensions_custom.conf'));
} else {
    out(_('Include already exists in extensions_custom.conf'));
}

// Reload dialplan to apply changes
exec('asterisk -rx "dialplan reload"');
out(_('Dialplan reloaded with AMD context'));

out(_('Simple Dialer module installed successfully'));
out(_('Sounds directory created at: ') . $sounds_dir);
out(_('Sample audio files copied if available'));
out(_('Daemon script made executable'));
out(_('AMD voicemail detection enabled'));
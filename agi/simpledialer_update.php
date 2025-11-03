#!/usr/bin/env php
<?php
// Simple Dialer AGI - Update call status in database
// Called from dialplan to update call_logs table

// Read AGI environment
$agi = array();
while (!feof(STDIN)) {
    $line = trim(fgets(STDIN));
    if ($line === '') break;
    list($key, $value) = explode(':', $line, 2);
    $agi[str_replace('agi_', '', strtolower($key))] = trim($value);
}

// Get variables from AGI args or channel variables
$call_id = $argv[1] ?? '';
$status = $argv[2] ?? '';
$duration = intval($argv[3] ?? 0);
$answer_time = $argv[4] ?? null;
$hangup_time = $argv[5] ?? null;
$hangup_cause = $argv[6] ?? '';
$voicemail = intval($argv[7] ?? 0);

// Load FreePBX
if (!defined('FREEPBX_CONF')) {
    define('FREEPBX_CONF', '/etc/freepbx.conf');
}
if (file_exists(FREEPBX_CONF)) {
    include_once(FREEPBX_CONF);
}

// Connect to database
try {
    $db = FreePBX::Database();

    // Map Asterisk status to friendly names
    $status_map = array(
        'ANSWER' => 'answered',
        'NOANSWER' => 'no-answer',
        'BUSY' => 'busy',
        'CONGESTION' => 'congestion',
        'CHANUNAVAIL' => 'unavailable',
        'CANCEL' => 'cancelled'
    );

    $mapped_status = isset($status_map[$status]) ? $status_map[$status] : strtolower($status);

    // Update call log
    $sql = "UPDATE simpledialer_call_logs
            SET status = ?,
                duration = ?,
                answer_time = ?,
                hangup_time = ?,
                hangup_cause = ?,
                voicemail_detected = ?
            WHERE call_id = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute(array(
        $mapped_status,
        $duration,
        $answer_time ?: null,
        $hangup_time ?: null,
        $hangup_cause,
        $voicemail,
        $call_id
    ));

    // Log success
    error_log("SimpleDialer: Updated {$call_id} - status={$mapped_status}, duration={$duration}s");

} catch (Exception $e) {
    error_log("SimpleDialer AGI Error: " . $e->getMessage());
}

// Return success to Asterisk
fwrite(STDOUT, "VERBOSE \"SimpleDialer: Call {$call_id} updated\" 3\n");
fflush(STDOUT);
?>

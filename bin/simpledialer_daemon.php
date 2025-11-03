#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Include FreePBX bootstrap
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ?: '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

class SimpleDialerDaemon {
    private $campaign_id;
    private $campaign;
    private $contacts;
    private $db;
    private $ami;
    private $active_calls = array();
    private $stop_file;
    
    public function __construct($campaign_id) {
        $this->campaign_id = $campaign_id;
        $this->stop_file = "/tmp/simpledialer_stop_$campaign_id";
        
        // Debug environment information
        echo "DEBUG: User: " . get_current_user() . " UID: " . getmyuid() . " GID: " . getmygid() . "\n";
        echo "DEBUG: Working directory: " . getcwd() . "\n";
        echo "DEBUG: FREEPBX_CONF env: " . (getenv('FREEPBX_CONF') ?: 'not set') . "\n";
        
        // Remove stop file if exists
        if (file_exists($this->stop_file)) {
            unlink($this->stop_file);
        }
        
        // Get database connection from FreePBX
        $this->db = FreePBX::Database();
        
        $this->loadCampaign();
        $this->loadContacts();
        $this->connectAMI();
    }
    
    private function loadCampaign() {
        $sql = "SELECT * FROM simpledialer_campaigns WHERE id = ?";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($this->campaign_id));
        $this->campaign = $sth->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->campaign) {
            die("Campaign not found: {$this->campaign_id}\n");
        }
        
        echo "Loaded campaign: {$this->campaign['name']}\n";
    }
    
    private function loadContacts() {
        $sql = "SELECT * FROM simpledialer_contacts WHERE campaign_id = ? AND status = 'pending' ORDER BY id";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($this->campaign_id));
        $this->contacts = $sth->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Loaded " . count($this->contacts) . " contacts to call\n";
    }
    
    private function connectAMI() {
        global $amp_conf;
        require_once('/var/www/html/admin/libraries/php-asmanager.php');
        
        $this->ami = new AGI_AsteriskManager();
        
        // Get AMI credentials from FreePBX
        $ami_user = isset($amp_conf['AMPMGRUSER']) ? $amp_conf['AMPMGRUSER'] : 'admin';
        $ami_pass = isset($amp_conf['AMPMGRPASS']) ? $amp_conf['AMPMGRPASS'] : 'amp111';
        
        if (!$this->ami->connect('localhost', $ami_user, $ami_pass)) {
            die("Failed to connect to AMI with user: $ami_user\n");
        }
        
        echo "Connected to AMI with user: $ami_user\n";
    }
    
    public function runCampaign() {
        echo "Starting campaign: {$this->campaign['name']}\n";
        echo "Max concurrent: {$this->campaign['max_concurrent']}\n";
        echo "Delay between calls: {$this->campaign['delay_between_calls']} seconds\n";
        
        $successful = 0;
        $failed = 0;
        $total = count($this->contacts);
        
        foreach ($this->contacts as $index => $contact) {
            // Check for stop signal
            if (file_exists($this->stop_file)) {
                echo "Stop signal received, terminating campaign\n";
                break;
            }
            
            // Wait for available slot
            $this->waitForAvailableSlot();
            
            echo "Calling " . ($index + 1) . "/$total: {$contact['phone_number']}\n";
            
            $call_result = $this->makeCall($contact);
            if ($call_result['success']) {
                $successful++;
                $this->updateContactStatus($contact['id'], 'called');
                $this->updateCallStatus($call_result['call_id'], 'answered');
            } else {
                $failed++;
                $this->updateContactStatus($contact['id'], 'failed');
                if (isset($call_result['call_id'])) {
                    $this->updateCallStatus($call_result['call_id'], 'failed');
                }
            }
            
            // Delay between call attempts
            if ($index < $total - 1) {
                sleep($this->campaign['delay_between_calls']);
            }
        }
        
        // Wait for remaining calls to complete
        echo "Waiting for remaining calls to complete...\n";
        $timeout = 120; // 2 minutes
        while (count($this->active_calls) > 0 && $timeout > 0) {
            sleep(2);
            $timeout -= 2;
            $this->cleanupCompletedCalls();
            
            if (count($this->active_calls) > 0) {
                echo "Still waiting for " . count($this->active_calls) . " active calls...\n";
            }
        }
        
        // Force cleanup any remaining calls
        if (count($this->active_calls) > 0) {
            echo "Timeout reached, force completing remaining " . count($this->active_calls) . " calls\n";
            foreach ($this->active_calls as $call_id => $call_info) {
                $this->updateContactStatus($call_info['contact_id'], 'called');
            }
            $this->active_calls = array();
        }
        
        echo "Campaign completed\n";
        echo "Successful: $successful\n";
        echo "Failed: $failed\n";
        
        // Update campaign status
        echo "Updating campaign status to 'completed'...\n";
        $this->updateCampaignStatus('completed');
        echo "Campaign status updated successfully\n";
        
        // Generate and save campaign report
        $this->generateAndEmailCampaignReport($successful, $failed, $total);
        
        // Cleanup old reports (older than 7 days)
        $this->cleanupOldReports();
        
        $this->ami->disconnect();
    }
    
    private function waitForAvailableSlot() {
        while (count($this->active_calls) >= $this->campaign['max_concurrent']) {
            echo "Waiting for available slot... (" . count($this->active_calls) . "/{$this->campaign['max_concurrent']})\n";
            sleep(1);
            $this->cleanupCompletedCalls();
        }
    }
    
    private function makeCall($contact) {
        $call_id = "simpledialer_" . $this->campaign_id . "_" . $contact['id'] . "_" . time();
        
        // Parse trunk information
        $trunk_parts = explode('/', $this->campaign['trunk']);
        $trunk_tech = $trunk_parts[0];
        $trunk_name = $trunk_parts[1];
        
        // Use direct trunk origination to preserve caller ID
        // Remove + prefix for FreePBX compatibility
        $dialable_number = ltrim($contact['phone_number'], '+');
        
        // Use Local channel to route through FreePBX outbound routes
        // This ensures proper trunk authentication and routing rules are applied
        // Direct trunk dialing (PJSIP/number@trunk) bypasses authentication and causes 403 errors
        $channel = "Local/{$dialable_number}@from-internal";
        
        echo "DEBUG: Channel string: $channel\n";
        echo "DEBUG: Context: simpledialer-outbound\n";
        echo "DEBUG: CallerID: {$this->campaign['caller_id']}\n";
        echo "DEBUG: Audio file: {$this->campaign['audio_file']}\n";
        
        // Prepare variables - including caller ID override for Local channel
        $cid_num = preg_replace('/[^0-9+]/', '', $this->campaign['caller_id']); // Extract number
        $cid_name = preg_replace('/<.*?>/', '', $this->campaign['caller_id']); // Extract name
        $cid_name = trim(str_replace(['"', $cid_num], '', $cid_name)); // Clean up name

        if (empty($cid_name)) {
            $cid_name = 'Campaign';
        }

        // Make the call using AMI - use simpledialer-outbound context with AMD
        // Use __ prefix to inherit variables through Local channel
        $originate_params = array(
            'Channel' => $channel,
            'Context' => 'simpledialer-outbound',
            'Exten' => 's',
            'Priority' => '1',
            'Timeout' => '30000',
            'CallerID' => '"' . $cid_name . '" <' . $cid_num . '>',
            'Variable' => implode(',', array(
                'CALL_ID=' . $call_id,
                'AUDIO_FILE=' . $this->campaign['audio_file'],
                'CAMPAIGN_ID=' . $this->campaign_id,
                'CONTACT_ID=' . $contact['id'],
                '__CAMPAIGN_CID_NUM=' . $cid_num,
                '__CAMPAIGN_CID_NAME=' . $cid_name
            )),
            'Async' => 'true'
        );
        
        $response = $this->ami->send_request('Originate', $originate_params);
        
        echo "DEBUG: AMI Originate response: " . print_r($response, true) . "\n";
        
        if ($response['Response'] == 'Success') {
            $this->active_calls[$call_id] = array(
                'contact_id' => $contact['id'],
                'phone_number' => $contact['phone_number'],
                'start_time' => time(),
                'channel' => $channel
            );
            
            // Log the call attempt
            $this->logCall($contact['id'], $call_id, 'initiated');
            
            return array('success' => true, 'call_id' => $call_id);
        } else {
            echo "Failed to originate call to {$contact['phone_number']}: {$response['Message']}\n";
            $this->logCall($contact['id'], $call_id, 'failed');
            return array('success' => false, 'call_id' => $call_id);
        }
    }
    
    private function cleanupCompletedCalls() {
        // Process any pending AMI events first
        $this->processAMIEvents();

        $current_time = time();
        foreach ($this->active_calls as $call_id => $call_info) {
            // Check if call is still active via AMI
            $channel_status = $this->checkChannelStatus($call_info['channel']);

            // Remove calls that are no longer active or older than 2 minutes
            if (!$channel_status || ($current_time - $call_info['start_time'] > 120)) {
                // Only update if we haven't received a UserEvent status yet
                if (!isset($call_info['status_updated'])) {
                    echo "Call completed without UserEvent, marking as completed: {$call_info['phone_number']}\n";
                    $this->updateContactStatus($call_info['contact_id'], 'called');
                    $this->updateCallStatus($call_id, 'completed');
                }

                unset($this->active_calls[$call_id]);
                echo "Cleaned up completed call: {$call_info['phone_number']}\n";
            }
        }
    }

    private function processAMIEvents() {
        // Read and process any pending AMI events
        while ($event = $this->ami->wait_response(false)) {
            if (isset($event['Event']) && $event['Event'] == 'UserEvent') {
                if (isset($event['UserEvent']) && $event['UserEvent'] == 'SimpleDialerStatus') {
                    $this->handleCallStatusEvent($event);
                }
            }
        }
    }

    private function handleCallStatusEvent($event) {
        $call_id = isset($event['CallID']) ? $event['CallID'] : null;
        $status = isset($event['Status']) ? $event['Status'] : 'UNKNOWN';
        $duration = isset($event['Duration']) ? intval($event['Duration']) : 0;
        $answer_time = isset($event['AnswerTime']) ? $event['AnswerTime'] : null;
        $hangup_time = isset($event['HangupTime']) ? $event['HangupTime'] : null;
        $hangup_cause = isset($event['HangupCause']) ? $event['HangupCause'] : '';
        $voicemail = isset($event['Voicemail']) ? intval($event['Voicemail']) : 0;

        if (!$call_id || !isset($this->active_calls[$call_id])) {
            return;
        }

        $call_info = $this->active_calls[$call_id];

        echo "Received status for call {$call_id}: {$status}\n";

        // Map Asterisk DIALSTATUS to user-friendly status
        $mapped_status = $this->mapDialStatus($status);

        // Update call log with detailed information
        $sql = "UPDATE simpledialer_call_logs SET
                status = ?,
                duration = ?,
                answer_time = ?,
                hangup_time = ?,
                hangup_cause = ?,
                voicemail_detected = ?
                WHERE call_id = ?";

        $sth = $this->db->prepare($sql);
        $sth->execute(array(
            $mapped_status,
            $duration,
            $answer_time,
            $hangup_time,
            $hangup_cause,
            $voicemail,
            $call_id
        ));

        // Update contact status based on call result
        $contact_status = ($status == 'ANSWER') ? 'called' : 'failed';
        $this->updateContactStatus($call_info['contact_id'], $contact_status);

        // If this UserEvent has a hangup_time, the call is complete - remove from active calls immediately
        if (!empty($hangup_time)) {
            echo "Call {$call_id} completed with hangup_time - removing from active calls\n";
            unset($this->active_calls[$call_id]);
        } else {
            // Mark this call as status_updated so cleanupCompletedCalls doesn't override it
            $this->active_calls[$call_id]['status_updated'] = true;
        }

        echo "Updated call {$call_id} with status: {$mapped_status}, duration: {$duration}s, voicemail: {$voicemail}\n";
    }

    private function mapDialStatus($asterisk_status) {
        // Map Asterisk DIALSTATUS values to user-friendly status names
        $status_map = array(
            'ANSWER' => 'answered',
            'NOANSWER' => 'no-answer',
            'BUSY' => 'busy',
            'CONGESTION' => 'congestion',
            'CHANUNAVAIL' => 'unavailable',
            'CANCEL' => 'cancelled',
            'DONTCALL' => 'blocked',
            'TORTURE' => 'rejected',
            'INVALIDARGS' => 'invalid'
        );

        return isset($status_map[$asterisk_status]) ? $status_map[$asterisk_status] : strtolower($asterisk_status);
    }
    
    private function checkChannelStatus($channel) {
        if (empty($channel)) {
            return false;
        }
        
        try {
            // Query AMI for channel status
            $response = $this->ami->send_request('Status', array('Channel' => $channel));
            
            if (isset($response['Response']) && $response['Response'] == 'Success') {
                return true;
            }
        } catch (Exception $e) {
            // If AMI query fails, assume channel is down
            echo "AMI Status query failed for $channel: " . $e->getMessage() . "\n";
        }
        
        return false;
    }
    
    private function updateContactStatus($contact_id, $status) {
        $sql = "UPDATE simpledialer_contacts SET status = ?, call_attempts = call_attempts + 1, last_called = NOW() WHERE id = ?";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($status, $contact_id));
    }
    
    private function updateCallStatus($call_id, $status) {
        $sql = "UPDATE simpledialer_call_logs SET status = ? WHERE call_id = ?";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($status, $call_id));
        echo "Updated call status for $call_id to $status\n";
    }
    
    private function updateCampaignStatus($status) {
        try {
            $sql = "UPDATE simpledialer_campaigns SET status = ?, updated_at = NOW() WHERE id = ?";
            $sth = $this->db->prepare($sql);
            $result = $sth->execute(array($status, $this->campaign_id));
            
            if (!$result) {
                echo "Failed to update campaign status to '$status'\n";
                return false;
            }
            
            echo "Campaign status updated to '$status' successfully\n";
            return true;
        } catch (Exception $e) {
            echo "Error updating campaign status: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function logCall($contact_id, $call_id, $status) {
        $sql = "INSERT INTO simpledialer_call_logs (campaign_id, contact_id, phone_number, call_id, status, duration, hangup_cause, voicemail_detected, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        // Get phone number from contact
        $contact_sql = "SELECT phone_number FROM simpledialer_contacts WHERE id = ?";
        $contact_sth = $this->db->prepare($contact_sql);
        $contact_sth->execute(array($contact_id));
        $phone_number = $contact_sth->fetchColumn();
        
        $sth = $this->db->prepare($sql);
        $sth->execute(array(
            $this->campaign_id,
            $contact_id,
            $phone_number,
            $call_id,
            $status,
            0,  // duration (will be updated later)
            '', // hangup_cause (will be updated later)
            0   // voicemail_detected (will be updated later)
        ));
    }
    
    private function generateAndEmailCampaignReport($successful, $failed, $total) {
        // Generate report
        $report_data = $this->generateCampaignReport($successful, $failed, $total);
        
        // Save report to file
        $report_file = $this->saveCampaignReport($report_data);
        
        // Email the report
        $this->emailCampaignReport($report_data, $report_file);
        
        echo "Campaign report generated and emailed: $report_file\n";
    }
    
    private function generateCampaignReport($successful, $failed, $total) {
        // Get campaign details
        $sql = "SELECT * FROM simpledialer_campaigns WHERE id = ?";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($this->campaign_id));
        $campaign = $sth->fetch(PDO::FETCH_ASSOC);

        // Get contact statistics
        $sql = "SELECT status, COUNT(*) as count FROM simpledialer_contacts WHERE campaign_id = ? GROUP BY status";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($this->campaign_id));
        $contact_stats = $sth->fetchAll(PDO::FETCH_ASSOC);

        // Get detailed call status breakdown
        $sql = "SELECT status, COUNT(*) as count, SUM(duration) as total_duration, SUM(voicemail_detected) as voicemail_count
                FROM simpledialer_call_logs
                WHERE campaign_id = ?
                GROUP BY status";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($this->campaign_id));
        $call_status_breakdown = $sth->fetchAll(PDO::FETCH_ASSOC);

        // Calculate statistics by status
        $status_counts = array();
        $total_duration = 0;
        $total_voicemails = 0;

        foreach ($call_status_breakdown as $row) {
            $status_counts[$row['status']] = intval($row['count']);
            $total_duration += intval($row['total_duration']);
            $total_voicemails += intval($row['voicemail_count']);
        }

        // Get call logs
        $sql = "SELECT cl.*, c.phone_number, c.name FROM simpledialer_call_logs cl
                JOIN simpledialer_contacts c ON cl.contact_id = c.id
                WHERE cl.campaign_id = ? ORDER BY cl.created_at";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($this->campaign_id));
        $call_logs = $sth->fetchAll(PDO::FETCH_ASSOC);

        $answered_count = isset($status_counts['answered']) ? $status_counts['answered'] : 0;
        $success_rate = $total > 0 ? round(($answered_count / $total) * 100, 1) : 0;

        return array(
            'campaign' => $campaign,
            'stats' => array(
                'total_contacts' => $total,
                'successful_calls' => $successful,
                'failed_calls' => $failed,
                'answered' => isset($status_counts['answered']) ? $status_counts['answered'] : 0,
                'no_answer' => isset($status_counts['no-answer']) ? $status_counts['no-answer'] : 0,
                'busy' => isset($status_counts['busy']) ? $status_counts['busy'] : 0,
                'congestion' => isset($status_counts['congestion']) ? $status_counts['congestion'] : 0,
                'unavailable' => isset($status_counts['unavailable']) ? $status_counts['unavailable'] : 0,
                'cancelled' => isset($status_counts['cancelled']) ? $status_counts['cancelled'] : 0,
                'other' => isset($status_counts['completed']) ? $status_counts['completed'] : 0,
                'total_duration' => $total_duration,
                'avg_duration' => $answered_count > 0 ? round($total_duration / $answered_count, 1) : 0,
                'voicemail_count' => $total_voicemails,
                'success_rate' => $success_rate
            ),
            'call_status_breakdown' => $call_status_breakdown,
            'contact_stats' => $contact_stats,
            'call_logs' => $call_logs,
            'generated_at' => date('Y-m-d H:i:s')
        );
    }
    
    private function saveCampaignReport($report_data) {
        $report_dir = '/var/log/asterisk/simpledialer_reports';
        
        if (!is_dir($report_dir)) {
            mkdir($report_dir, 0755, true);
        }
        
        $filename = 'campaign_' . $this->campaign_id . '_' . date('Y-m-d_H-i-s') . '.txt';
        $filepath = $report_dir . '/' . $filename;
        
        $content = "SIMPLE DIALER CAMPAIGN REPORT\n";
        $content .= "================================\n\n";
        $content .= "Campaign: " . $report_data['campaign']['name'] . "\n";
        $content .= "Description: " . $report_data['campaign']['description'] . "\n";
        $content .= "Status: " . $report_data['campaign']['status'] . "\n";
        $content .= "Generated: " . $report_data['generated_at'] . "\n\n";
        
        $content .= "STATISTICS\n";
        $content .= "----------\n";
        $content .= "Total Contacts: " . $report_data['stats']['total_contacts'] . "\n\n";

        $content .= "Call Status Breakdown:\n";
        $content .= "  Answered: " . $report_data['stats']['answered'] . "\n";
        $content .= "  No Answer: " . $report_data['stats']['no_answer'] . "\n";
        $content .= "  Busy: " . $report_data['stats']['busy'] . "\n";
        $content .= "  Congestion: " . $report_data['stats']['congestion'] . "\n";
        $content .= "  Unavailable: " . $report_data['stats']['unavailable'] . "\n";
        $content .= "  Cancelled: " . $report_data['stats']['cancelled'] . "\n";
        $content .= "  Other: " . $report_data['stats']['other'] . "\n\n";

        $content .= "Call Metrics:\n";
        $content .= "  Total Duration: " . gmdate("H:i:s", $report_data['stats']['total_duration']) . "\n";
        $content .= "  Average Duration: " . $report_data['stats']['avg_duration'] . " seconds\n";
        $content .= "  Voicemails Detected: " . $report_data['stats']['voicemail_count'] . "\n";
        $content .= "  Success Rate: " . $report_data['stats']['success_rate'] . "%\n\n";
        
        if (!empty($report_data['contact_stats'])) {
            $content .= "CONTACT STATUS BREAKDOWN\n";
            $content .= "------------------------\n";
            foreach ($report_data['contact_stats'] as $stat) {
                $content .= ucfirst($stat['status']) . ": " . $stat['count'] . "\n";
            }
            $content .= "\n";
        }
        
        if (!empty($report_data['call_logs'])) {
            $content .= "CALL LOGS\n";
            $content .= "---------\n";
            foreach ($report_data['call_logs'] as $log) {
                $content .= $log['created_at'] . " - " . $log['phone_number'] . " (" . $log['name'] . ") - " . 
                    ucfirst($log['status']) . "\n";
            }
        }
        
        file_put_contents($filepath, $content);
        return $filepath;
    }
    
    private function cleanupOldReports() {
        $report_dir = '/var/log/asterisk/simpledialer_reports';
        $deleted_count = 0;
        $cutoff_time = time() - (7 * 24 * 60 * 60); // 7 days ago
        
        if (!is_dir($report_dir)) {
            return;
        }
        
        $files = glob($report_dir . '/campaign_*.txt');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                    echo "Deleted old report: " . basename($file) . "\n";
                }
            }
        }
        
        if ($deleted_count > 0) {
            echo "Cleaned up $deleted_count old reports (older than 7 days)\n";
        }
    }
    
    private function emailCampaignReport($report_data, $report_file) {
        // Get FreePBX mail settings
        global $amp_conf;
        
        // Get system email settings
        $from_email = isset($amp_conf['AMPEXTFROM']) ? $amp_conf['AMPEXTFROM'] : 'admin@localhost';
        $admin_email = isset($amp_conf['AMPEXTMAIL']) ? $amp_conf['AMPEXTMAIL'] : $from_email;
        
        if (empty($admin_email) || $admin_email == 'admin@localhost') {
            echo "No admin email configured, skipping email report\n";
            return false;
        }
        
        $subject = "Simple Dialer Campaign Report - " . $report_data['campaign']['name'];
        
        $message = "Campaign: " . $report_data['campaign']['name'] . "\n";
        $message .= "Description: " . $report_data['campaign']['description'] . "\n";
        $message .= "Completed: " . $report_data['generated_at'] . "\n\n";
        
        $message .= "SUMMARY:\n";
        $message .= "--------\n";
        $message .= "Total Contacts: " . $report_data['stats']['total_contacts'] . "\n\n";

        $message .= "Call Status Breakdown:\n";
        $message .= "  Answered: " . $report_data['stats']['answered'] . "\n";
        $message .= "  No Answer: " . $report_data['stats']['no_answer'] . "\n";
        $message .= "  Busy: " . $report_data['stats']['busy'] . "\n";
        $message .= "  Congestion: " . $report_data['stats']['congestion'] . "\n";
        $message .= "  Unavailable: " . $report_data['stats']['unavailable'] . "\n";
        $message .= "  Cancelled: " . $report_data['stats']['cancelled'] . "\n\n";

        $message .= "Call Metrics:\n";
        $message .= "  Total Duration: " . gmdate("H:i:s", $report_data['stats']['total_duration']) . "\n";
        $message .= "  Average Duration: " . $report_data['stats']['avg_duration'] . " seconds\n";
        $message .= "  Voicemails Detected: " . $report_data['stats']['voicemail_count'] . "\n";
        $message .= "  Success Rate: " . $report_data['stats']['success_rate'] . "%\n\n";
        
        $message .= "Full report attached.\n\n";
        $message .= "Generated by Simple Dialer Module";
        
        $headers = "From: " . $from_email . "\r\n";
        $headers .= "Reply-To: " . $from_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Try to send email with attachment
        if (file_exists($report_file)) {
            $attachment = chunk_split(base64_encode(file_get_contents($report_file)));
            $boundary = md5(time());
            
            $headers .= "\r\nMIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";
            
            $email_message = "--" . $boundary . "\r\n";
            $email_message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $email_message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $email_message .= $message . "\r\n\r\n";
            
            $email_message .= "--" . $boundary . "\r\n";
            $email_message .= "Content-Type: text/plain; charset=UTF-8; name=\"" . basename($report_file) . "\"\r\n";
            $email_message .= "Content-Disposition: attachment; filename=\"" . basename($report_file) . "\"\r\n";
            $email_message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $email_message .= $attachment . "\r\n";
            $email_message .= "--" . $boundary . "--\r\n";
            
            if (mail($admin_email, $subject, $email_message, $headers)) {
                echo "Campaign report emailed to: $admin_email\n";
                return true;
            } else {
                echo "Failed to send email report to: $admin_email\n";
                return false;
            }
        } else {
            echo "Report file not found, cannot send email\n";
            return false;
        }
    }
}

// Check command line arguments
if ($argc < 2) {
    die("Usage: php simpledialer_daemon.php <campaign_id>\n");
}

$campaign_id = $argv[1];

if (!is_numeric($campaign_id)) {
    die("Invalid campaign ID: $campaign_id\n");
}

// Run the daemon
try {
    $daemon = new SimpleDialerDaemon($campaign_id);
    $daemon->runCampaign();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
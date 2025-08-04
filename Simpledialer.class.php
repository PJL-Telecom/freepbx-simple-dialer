<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

class Simpledialer extends FreePBX_Helpers implements BMO {
    public function __construct($freepbx = null) {
        if ($freepbx == null) {
            throw new Exception("Not given a FreePBX Object");
        }
        $this->FreePBX = $freepbx;
        $this->db = $freepbx->Database;
    }

    public function install() {
        // Create default dialplan contexts
        $this->createDialplanContexts();
    }

    public function uninstall() {
        // Clean up dialplan contexts
        $this->removeDialplanContexts();
    }

    public function backup() {
        // Export campaigns and contacts
        return array();
    }

    public function restore($backup) {
        // Restore campaigns and contacts
        return true;
    }

    public function doConfigPageInit($page) {
        // Handle form submissions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_campaign':
                    $campaign_id = $this->addCampaign($_POST);
                    // If a CSV file was uploaded, process it
                    if ($campaign_id && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                        $this->uploadContacts($campaign_id, $_FILES['csv_file']);
                    }
                    break;
                case 'edit_campaign':
                    $this->updateCampaign($_POST);
                    break;
                case 'delete_campaign':
                    $this->deleteCampaign($_POST['campaign_id']);
                    break;
                case 'upload_contacts':
                    $this->uploadContacts($_POST['campaign_id'], $_FILES['csv_file']);
                    break;
                case 'start_campaign':
                    try {
                        $this->startCampaign($_POST['campaign_id']);
                        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                            header('Content-Type: application/json');
                            echo json_encode(array('success' => true, 'message' => 'Campaign started successfully'));
                            exit;
                        }
                    } catch (Exception $e) {
                        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                            header('Content-Type: application/json');
                            echo json_encode(array('success' => false, 'message' => $e->getMessage()));
                            exit;
                        } else {
                            throw $e; // Re-throw for non-AJAX requests
                        }
                    }
                    break;
                case 'stop_campaign':
                    $this->stopCampaign($_POST['campaign_id']);
                    break;
            }
        }
    }

    /**
     * Get all campaigns
     */
    public function getCampaigns() {
        $sql = "SELECT * FROM simpledialer_campaigns ORDER BY created_at DESC";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get campaign by ID
     */
    public function getCampaign($id) {
        $sql = "SELECT * FROM simpledialer_campaigns WHERE id = ?";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($id));
        return $sth->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Add new campaign
     */
    public function addCampaign($data) {
        // Handle scheduled_time - convert empty string to null
        $scheduled_time = (!empty($data['scheduled_time']) && $data['scheduled_time'] !== '') ? $data['scheduled_time'] : null;
        
        $sql = "INSERT INTO simpledialer_campaigns (name, description, audio_file, trunk, caller_id, max_concurrent, delay_between_calls, scheduled_time, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $sth = $this->db->prepare($sql);
        $result = $sth->execute(array(
            $data['name'],
            $data['description'],
            $data['audio_file'],
            $data['trunk'],
            $data['caller_id'],
            $data['max_concurrent'],
            $data['delay_between_calls'],
            $scheduled_time
        ));
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Update campaign
     */
    public function updateCampaign($data) {
        $sql = "UPDATE simpledialer_campaigns SET 
                name = ?, description = ?, audio_file = ?, trunk = ?, caller_id = ?, 
                max_concurrent = ?, delay_between_calls = ?, scheduled_time = ?, 
                updated_at = NOW() 
                WHERE id = ?";
        
        $scheduled_time = (!empty($data['scheduled_time']) && $data['scheduled_time'] !== '') ? $data['scheduled_time'] : null;
        
        $sth = $this->db->prepare($sql);
        return $sth->execute(array(
            $data['name'],
            $data['description'],
            $data['audio_file'],
            $data['trunk'],
            $data['caller_id'],
            $data['max_concurrent'],
            $data['delay_between_calls'],
            $scheduled_time,
            $data['campaign_id']
        ));
    }

    /**
     * Delete campaign
     */
    public function deleteCampaign($id) {
        // Delete contacts first
        $sth = $this->db->prepare("DELETE FROM simpledialer_contacts WHERE campaign_id = ?");
        $sth->execute(array($id));
        // Delete call logs
        $sth = $this->db->prepare("DELETE FROM simpledialer_call_logs WHERE campaign_id = ?");
        $sth->execute(array($id));
        // Delete campaign
        $sth = $this->db->prepare("DELETE FROM simpledialer_campaigns WHERE id = ?");
        return $sth->execute(array($id));
    }

    /**
     * Get contacts for campaign
     */
    public function getCampaignContacts($campaign_id) {
        $sql = "SELECT * FROM simpledialer_contacts WHERE campaign_id = ? ORDER BY id";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($campaign_id));
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Upload contacts from CSV
     */
    public function uploadContacts($campaign_id, $csv_file) {
        if (!$csv_file || $csv_file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error");
        }

        $handle = fopen($csv_file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception("Cannot read CSV file");
        }

        // Clear existing contacts
        $sth = $this->db->prepare("DELETE FROM simpledialer_contacts WHERE campaign_id = ?");
        $sth->execute(array($campaign_id));

        $header = fgetcsv($handle);
        $imported = 0;

        while (($data = fgetcsv($handle)) !== FALSE) {
            if (!empty($data[0])) {
                $phone = $this->normalizePhoneNumber($data[0]);
                $name = isset($data[1]) ? $data[1] : '';
                
                $sql = "INSERT INTO simpledialer_contacts (campaign_id, phone_number, name, created_at) VALUES (?, ?, ?, NOW())";
                $sth = $this->db->prepare($sql);
                $sth->execute(array($campaign_id, $phone, $name));
                $imported++;
            }
        }

        fclose($handle);
        return $imported;
    }

    /**
     * Normalize phone number format
     */
    private function normalizePhoneNumber($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add +1 if not present and number is 10 digits
        if (strlen($phone) == 10) {
            $phone = '+1' . $phone;
        } elseif (strlen($phone) == 11 && substr($phone, 0, 1) == '1') {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }

    /**
     * Start campaign
     */
    public function startCampaign($campaign_id) {
        // Get campaign details to check scheduled time
        $campaign = $this->getCampaign($campaign_id);
        if (!$campaign) {
            throw new Exception("Campaign not found");
        }
        
        // Check if campaign has a scheduled time
        if (!empty($campaign['scheduled_time'])) {
            $scheduled_time = strtotime($campaign['scheduled_time']);
            $current_time = time();
            
            if ($current_time < $scheduled_time) {
                throw new Exception("Campaign is scheduled for " . date('Y-m-d H:i:s', $scheduled_time) . ". Current time: " . date('Y-m-d H:i:s', $current_time));
            }
        }
        
        // Update campaign status
        $sth = $this->db->prepare("UPDATE simpledialer_campaigns SET status = 'active', updated_at = NOW() WHERE id = ?");
        $sth->execute(array($campaign_id));
        
        // Start the dialer daemon
        $this->startDialerDaemon($campaign_id);
        
        return true;
    }

    /**
     * Stop campaign
     */
    public function stopCampaign($campaign_id) {
        // Update campaign status
        $sth = $this->db->prepare("UPDATE simpledialer_campaigns SET status = 'stopped', updated_at = NOW() WHERE id = ?");
        $sth->execute(array($campaign_id));
        
        // Stop the dialer daemon
        $this->stopDialerDaemon($campaign_id);
        
        return true;
    }

    /**
     * Start dialer daemon for campaign
     */
    private function startDialerDaemon($campaign_id) {
        $daemon_script = __DIR__ . '/bin/simpledialer_daemon.php';
        $log_file = '/var/log/asterisk/simpledialer_' . $campaign_id . '.log';
        
        // Check if daemon script exists and is executable
        if (!file_exists($daemon_script)) {
            error_log("Simple Dialer: Daemon script not found at $daemon_script");
            return false;
        }
        
        if (!is_executable($daemon_script)) {
            chmod($daemon_script, 0755);
        }
        
        // Start the daemon with proper logging
        $command = "/usr/bin/php $daemon_script $campaign_id >> $log_file 2>&1 &";
        error_log("Simple Dialer: Starting daemon with command: $command");
        
        $output = array();
        $return_var = 0;
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            error_log("Simple Dialer: Failed to start daemon. Return code: $return_var");
            return false;
        }
        
        return true;
    }

    /**
     * Stop dialer daemon for campaign
     */
    private function stopDialerDaemon($campaign_id) {
        // Create stop file
        $stop_file = "/tmp/simpledialer_stop_$campaign_id";
        touch($stop_file);
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStats($campaign_id) {
        $stats = array();
        
        // Total contacts
        $sql = "SELECT COUNT(*) as total FROM simpledialer_contacts WHERE campaign_id = ?";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($campaign_id));
        $stats['total_contacts'] = $sth->fetchColumn();
        
        // Called contacts
        $sql = "SELECT COUNT(*) as called FROM simpledialer_contacts WHERE campaign_id = ? AND status != 'pending'";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($campaign_id));
        $stats['called_contacts'] = $sth->fetchColumn();
        
        // Successful calls
        $sql = "SELECT COUNT(*) as successful FROM simpledialer_call_logs WHERE campaign_id = ? AND status = 'answered'";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($campaign_id));
        $stats['successful_calls'] = $sth->fetchColumn();
        
        // Failed calls
        $sql = "SELECT COUNT(*) as failed FROM simpledialer_call_logs WHERE campaign_id = ? AND status IN ('failed', 'busy', 'noanswer')";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($campaign_id));
        $stats['failed_calls'] = $sth->fetchColumn();
        
        return $stats;
    }

    /**
     * Generate detailed campaign report
     */
    public function generateCampaignReport($campaign_id) {
        $campaign = $this->getCampaign($campaign_id);
        $stats = $this->getCampaignStats($campaign_id);
        $contacts = $this->getCampaignContacts($campaign_id);
        
        // Get call logs
        $sql = "SELECT cl.*, c.phone_number FROM simpledialer_call_logs cl 
                JOIN simpledialer_contacts c ON cl.contact_id = c.id 
                WHERE cl.campaign_id = ? ORDER BY cl.created_at";
        $sth = $this->db->prepare($sql);
        $sth->execute(array($campaign_id));
        $call_logs = $sth->fetchAll(\PDO::FETCH_ASSOC);
        
        $report = array(
            'campaign' => $campaign,
            'stats' => $stats,
            'contacts' => $contacts,
            'call_logs' => $call_logs,
            'generated_at' => date('Y-m-d H:i:s')
        );
        
        return $report;
    }

    /**
     * Save campaign report to file
     */
    public function saveCampaignReport($campaign_id) {
        $report = $this->generateCampaignReport($campaign_id);
        $report_dir = '/var/log/asterisk/simpledialer_reports';
        
        if (!is_dir($report_dir)) {
            mkdir($report_dir, 0755, true);
        }
        
        $filename = 'campaign_' . $campaign_id . '_' . date('Y-m-d_H-i-s') . '.txt';
        $filepath = $report_dir . '/' . $filename;
        
        $content = "SIMPLE DIALER CAMPAIGN REPORT\n";
        $content .= "================================\n\n";
        $content .= "Campaign: " . $report['campaign']['name'] . "\n";
        $content .= "Description: " . $report['campaign']['description'] . "\n";
        $content .= "Status: " . $report['campaign']['status'] . "\n";
        $content .= "Generated: " . $report['generated_at'] . "\n\n";
        
        $content .= "STATISTICS\n";
        $content .= "----------\n";
        $content .= "Total Contacts: " . $report['stats']['total_contacts'] . "\n";
        $content .= "Called Contacts: " . $report['stats']['called_contacts'] . "\n";
        $content .= "Successful Calls: " . $report['stats']['successful_calls'] . "\n";
        $content .= "Failed Calls: " . $report['stats']['failed_calls'] . "\n";
        $success_rate = $report['stats']['total_contacts'] > 0 ? 
            round(($report['stats']['called_contacts'] / $report['stats']['total_contacts']) * 100, 1) : 0;
        $content .= "Success Rate: " . $success_rate . "%\n\n";
        
        $content .= "CONTACT DETAILS\n";
        $content .= "---------------\n";
        foreach ($report['contacts'] as $contact) {
            $content .= $contact['phone_number'] . " - " . $contact['name'] . " - " . 
                ucfirst($contact['status']) . " (" . $contact['call_attempts'] . " attempts)\n";
        }
        
        if (!empty($report['call_logs'])) {
            $content .= "\nCALL LOGS\n";
            $content .= "---------\n";
            foreach ($report['call_logs'] as $log) {
                $content .= $log['created_at'] . " - " . $log['phone_number'] . " - " . 
                    ucfirst($log['status']) . " - Duration: " . $log['duration'] . "s\n";
            }
        }
        
        file_put_contents($filepath, $content);
        return $filepath;
    }

    /**
     * Cleanup old campaign reports
     */
    public function cleanupOldReports($days_old = 7) {
        $report_dir = '/var/log/asterisk/simpledialer_reports';
        $deleted_count = 0;
        $cutoff_time = time() - ($days_old * 24 * 60 * 60);
        
        if (!is_dir($report_dir)) {
            return 0;
        }
        
        $files = glob($report_dir . '/campaign_*.txt');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return $deleted_count;
    }

    /**
     * Get available trunks
     */
    public function getAvailableTrunks() {
        $trunks = array();
        
        // Get all trunks from the trunks table
        $sql = "SELECT trunkid, name, tech, channelid FROM trunks WHERE disabled = 'off' ORDER BY name";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $all_trunks = $sth->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($all_trunks as $trunk) {
            $tech = strtoupper($trunk['tech']);
            $trunks[] = array(
                'value' => $tech . '/' . $trunk['channelid'],
                'text' => $trunk['name'] . ' (' . $tech . '/' . $trunk['channelid'] . ')'
            );
        }
        
        return $trunks;
    }

    /**
     * Get audio files from FreePBX system recordings
     */
    public function getAudioFiles() {
        $files = array();
        
        // Get system recordings from FreePBX
        $sql = "SELECT id, displayname, filename FROM recordings ORDER BY displayname";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $recordings = $sth->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($recordings as $recording) {
            $files[] = array(
                'id' => $recording['id'],
                'name' => $recording['displayname'],
                'filename' => $recording['filename'],
                'formats' => $this->checkAudioFormats($recording['filename'])
            );
        }
        
        return $files;
    }
    
    /**
     * Check which audio formats exist for a recording
     */
    private function checkAudioFormats($filename) {
        $sounds_dir = '/var/lib/asterisk/sounds/en/';
        $base_filename = pathinfo($filename, PATHINFO_FILENAME);
        $formats = array('wav', 'gsm', 'ulaw', 'alaw', 'sln');
        $existing = array();
        
        foreach ($formats as $format) {
            $file_path = $sounds_dir . $base_filename . '.' . $format;
            if (file_exists($file_path)) {
                $existing[] = $format;
            }
        }
        
        return $existing;
    }


    /**
     * Create dialplan contexts
     */
    private function createDialplanContexts() {
        $context = "[simpledialer-outbound]\n";
        $context .= "exten => s,1,NoOp(Simple Dialer - Playing: \${AUDIO_FILE})\n";
        $context .= "exten => s,n,Set(TIMEOUT(absolute)=300)\n";
        $context .= "exten => s,n,Set(AUDIO_PATH=en/\${AUDIO_FILE})\n";
        $context .= "exten => s,n,AMD()\n";
        $context .= "exten => s,n,GotoIf(\$[\"\${AMDSTATUS}\" = \"MACHINE\"]?vm:human)\n";
        $context .= "exten => s,n(human),Playback(\${AUDIO_PATH})\n";
        $context .= "exten => s,n,Wait(2)\n";
        $context .= "exten => s,n,Hangup()\n";
        $context .= "exten => s,n(vm),Wait(3)\n";
        $context .= "exten => s,n,Playback(\${AUDIO_PATH})\n";
        $context .= "exten => s,n,Wait(1)\n";
        $context .= "exten => s,n,Hangup()\n\n";

        // Write to extensions_custom.conf
        $this->writeCustomDialplan($context);
    }

    /**
     * Remove dialplan contexts
     */
    private function removeDialplanContexts() {
        // Remove from extensions_custom.conf
        $this->removeCustomDialplan();
    }

    /**
     * Write custom dialplan
     */
    private function writeCustomDialplan($context) {
        $custom_file = '/etc/asterisk/extensions_custom.conf';
        $content = file_get_contents($custom_file);
        
        // Remove existing simpledialer context
        $content = preg_replace('/\[simpledialer-outbound\].*?(?=\[|\Z)/s', '', $content);
        
        // Add new context
        $content .= "\n" . $context;
        
        file_put_contents($custom_file, $content);
    }

    /**
     * Remove custom dialplan
     */
    private function removeCustomDialplan() {
        $custom_file = '/etc/asterisk/extensions_custom.conf';
        $content = file_get_contents($custom_file);
        
        // Remove simpledialer context
        $content = preg_replace('/\[simpledialer-outbound\].*?(?=\[|\Z)/s', '', $content);
        
        file_put_contents($custom_file, $content);
    }
}
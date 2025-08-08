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

/**
 * Simple Dialer Scheduler
 * 
 * This script checks for campaigns that should be started based on their scheduled time.
 * Run this script via cron every minute to check for scheduled campaigns.
 * 
 * Usage: php scheduler.php
 * 
 * Recommended cron entry:
 * * * * * * php /var/www/html/admin/modules/simpledialer/bin/scheduler.php
 */

class SimpleDialerScheduler {
    private $db;
    private $simpledialer;
    
    public function __construct() {
        $this->db = FreePBX::Database();
        $this->simpledialer = FreePBX::Simpledialer();
    }
    
    public function checkSchedule() {
        $current_time = date('Y-m-d H:i:s');
        
        echo "Simple Dialer Scheduler - " . $current_time . "\n";
        echo "========================================\n";
        
        // Find campaigns that should be started
        $sql = "SELECT id, name, scheduled_time, status FROM simpledialer_campaigns 
                WHERE status IN ('pending', 'inactive') 
                AND scheduled_time IS NOT NULL 
                AND scheduled_time <= NOW()
                ORDER BY scheduled_time";
        
        echo "Checking for campaigns with query: $sql\n";
        echo "Current MySQL time: ";
        $time_sth = $this->db->prepare("SELECT NOW() as mysql_time");
        $time_sth->execute();
        $current_mysql_time = $time_sth->fetchColumn();
        echo "$current_mysql_time\n";
        
        // First, show all scheduled campaigns for debugging
        $debug_sql = "SELECT id, name, scheduled_time, status FROM simpledialer_campaigns 
                      WHERE scheduled_time IS NOT NULL ORDER BY scheduled_time";
        $debug_sth = $this->db->prepare($debug_sql);
        $debug_sth->execute();
        $all_scheduled = $debug_sth->fetchAll(PDO::FETCH_ASSOC);
        
        echo "All scheduled campaigns:\n";
        foreach ($all_scheduled as $camp) {
            echo "  - ID: {$camp['id']}, Name: {$camp['name']}, Status: {$camp['status']}, Time: {$camp['scheduled_time']}\n";
        }
        
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $campaigns = $sth->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Campaigns ready to start: " . count($campaigns) . "\n";
        
        if (empty($campaigns)) {
            echo "No campaigns scheduled to start now.\n";
            return 0;
        }
        
        $started_count = 0;
        foreach ($campaigns as $campaign) {
            echo "Starting scheduled campaign: {$campaign['name']} (ID: {$campaign['id']})\n";
            echo "  Scheduled for: {$campaign['scheduled_time']}\n";
            
            try {
                // Check if campaign has contacts
                $contact_sql = "SELECT COUNT(*) FROM simpledialer_contacts WHERE campaign_id = ?";
                $contact_sth = $this->db->prepare($contact_sql);
                $contact_sth->execute(array($campaign['id']));
                $contact_count = $contact_sth->fetchColumn();
                
                if ($contact_count == 0) {
                    echo "  ERROR: Campaign has no contacts, skipping\n";
                    continue;
                }
                
                // Start the campaign
                $this->simpledialer->startCampaign($campaign['id']);
                $started_count++;
                echo "  SUCCESS: Campaign started with $contact_count contacts\n";
                
            } catch (Exception $e) {
                echo "  ERROR: " . $e->getMessage() . "\n";
                
                // Update campaign status to failed if there's an error
                $error_sql = "UPDATE simpledialer_campaigns SET status = 'failed', updated_at = NOW() WHERE id = ?";
                $error_sth = $this->db->prepare($error_sql);
                $error_sth->execute(array($campaign['id']));
            }
        }
        
        echo "\nScheduler Summary:\n";
        echo "- Campaigns checked: " . count($campaigns) . "\n";
        echo "- Campaigns started: $started_count\n";
        
        return $started_count;
    }
}

// Run the scheduler
try {
    $scheduler = new SimpleDialerScheduler();
    $started = $scheduler->checkSchedule();
    exit($started > 0 ? 0 : 0); // Always exit successfully
} catch (Exception $e) {
    echo "Scheduler error: " . $e->getMessage() . "\n";
    exit(1);
}
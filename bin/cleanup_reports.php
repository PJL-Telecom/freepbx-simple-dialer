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
 * Simple Dialer Report Cleanup Script
 * 
 * This script cleans up campaign reports older than the specified number of days.
 * Can be run manually or via cron job.
 * 
 * Usage: php cleanup_reports.php [days]
 * Default: 7 days
 */

class SimpleDialerCleanup {
    private $report_dir;
    private $days_old;
    
    public function __construct($days_old = 7) {
        $this->report_dir = '/var/log/asterisk/simpledialer_reports';
        $this->days_old = intval($days_old);
        
        if ($this->days_old < 1) {
            $this->days_old = 7; // Default to 7 days
        }
    }
    
    public function cleanup() {
        if (!is_dir($this->report_dir)) {
            echo "Report directory does not exist: {$this->report_dir}\n";
            return 0;
        }
        
        $cutoff_time = time() - ($this->days_old * 24 * 60 * 60);
        $deleted_count = 0;
        $total_size = 0;
        
        echo "Simple Dialer Report Cleanup\n";
        echo "============================\n";
        echo "Looking for reports older than {$this->days_old} days...\n";
        echo "Cutoff date: " . date('Y-m-d H:i:s', $cutoff_time) . "\n\n";
        
        $files = glob($this->report_dir . '/campaign_*.txt');
        
        if (empty($files)) {
            echo "No campaign reports found.\n";
            return 0;
        }
        
        foreach ($files as $file) {
            $file_time = filemtime($file);
            $file_size = filesize($file);
            $file_age_days = round((time() - $file_time) / 86400, 1);
            
            if ($file_time < $cutoff_time) {
                echo "Deleting: " . basename($file) . " (age: {$file_age_days} days, size: " . number_format($file_size) . " bytes)\n";
                
                if (unlink($file)) {
                    $deleted_count++;
                    $total_size += $file_size;
                } else {
                    echo "  ERROR: Failed to delete file\n";
                }
            } else {
                echo "Keeping: " . basename($file) . " (age: {$file_age_days} days)\n";
            }
        }
        
        echo "\nCleanup Summary:\n";
        echo "- Files deleted: $deleted_count\n";
        echo "- Space freed: " . number_format($total_size) . " bytes (" . round($total_size / 1024, 1) . " KB)\n";
        echo "- Files remaining: " . (count($files) - $deleted_count) . "\n";
        
        return $deleted_count;
    }
}

// Get days parameter from command line
$days_old = isset($argv[1]) ? intval($argv[1]) : 7;

if ($days_old < 1) {
    echo "Usage: php cleanup_reports.php [days]\n";
    echo "  days: Number of days old before reports are deleted (default: 7)\n";
    echo "\nExample: php cleanup_reports.php 14  # Delete reports older than 14 days\n";
    exit(1);
}

// Run cleanup
$cleanup = new SimpleDialerCleanup($days_old);
$deleted = $cleanup->cleanup();

exit($deleted > 0 ? 0 : 0); // Always exit successfully
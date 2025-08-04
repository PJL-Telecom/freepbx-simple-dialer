<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Initialize the Simple Dialer module
try {
    $simpledialer = FreePBX::Simpledialer();
} catch (Exception $e) {
    die("Error initializing Simple Dialer module: " . $e->getMessage());
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$campaign_id = isset($_REQUEST['campaign_id']) ? $_REQUEST['campaign_id'] : '';

// Handle AJAX requests
if ($action == 'get_campaign_stats' && !empty($campaign_id)) {
    header('Content-Type: application/json');
    $stats = $simpledialer->getCampaignStats($campaign_id);
    echo json_encode($stats);
    exit;
}

if ($action == 'get_campaign' && !empty($campaign_id)) {
    header('Content-Type: application/json');
    $campaign = $simpledialer->getCampaign($campaign_id);
    echo json_encode($campaign);
    exit;
}

if ($action == 'get_contacts' && !empty($campaign_id)) {
    $contacts = $simpledialer->getCampaignContacts($campaign_id);
    $html = '<h4>' . _('Contacts') . ' (' . count($contacts) . ')</h4>';
    if (count($contacts) > 0) {
        $html .= '<div class="table-responsive"><table class="table table-striped table-sm">';
        $html .= '<thead><tr><th>' . _('Phone Number') . '</th><th>' . _('Name') . '</th><th>' . _('Status') . '</th><th>' . _('Attempts') . '</th></tr></thead><tbody>';
        foreach ($contacts as $contact) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($contact['phone_number']) . '</td>';
            $html .= '<td>' . htmlspecialchars($contact['name']) . '</td>';
            $html .= '<td><span class="label label-' . ($contact['status'] == 'pending' ? 'default' : ($contact['status'] == 'called' ? 'success' : 'warning')) . '">' . ucfirst($contact['status']) . '</span></td>';
            $html .= '<td>' . $contact['call_attempts'] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
    } else {
        $html .= '<p class="text-muted">' . _('No contacts uploaded yet.') . '</p>';
    }
    echo $html;
    exit;
}

// Handle sample CSV download
if ($action == 'download_sample_csv') {
    $csv_content = "phone_number,name\n";
    $csv_content .= "15551234567,John Doe\n";
    $csv_content .= "15559876543,Jane Smith\n";
    $csv_content .= "15555551234,Bob Johnson\n";
    $csv_content .= "15552223333,Alice Williams\n";
    $csv_content .= "15554445555,Charlie Brown\n";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_contacts.csv"');
    header('Content-Length: ' . strlen($csv_content));
    echo $csv_content;
    exit;
}

// Handle campaign progress updates
if ($action == 'get_campaign_progress') {
    header('Content-Type: application/json');
    
    $campaigns = $simpledialer->getCampaigns();
    $hasActiveCampaigns = false;
    $campaignData = array();
    
    foreach ($campaigns as $campaign) {
        $stats = $simpledialer->getCampaignStats($campaign['id']);
        if ($campaign['status'] === 'active') {
            $hasActiveCampaigns = true;
        }
        
        $campaignData[] = array(
            'id' => $campaign['id'],
            'name' => $campaign['name'],
            'status' => $campaign['status'],
            'total_contacts' => $stats['total_contacts'],
            'called_contacts' => $stats['called_contacts'],
            'successful_calls' => $stats['successful_calls'],
            'failed_calls' => $stats['failed_calls']
        );
    }
    
    echo json_encode(array(
        'hasActiveCampaigns' => $hasActiveCampaigns,
        'campaigns' => $campaignData
    ));
    exit;
}

// Handle reports listing
if ($action == 'get_reports') {
    $report_dir = '/var/log/asterisk/simpledialer_reports';
    $reports = array();
    
    if (is_dir($report_dir)) {
        $files = glob($report_dir . '/campaign_*.txt');
        foreach ($files as $file) {
            $reports[] = array(
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'campaign_id' => preg_replace('/campaign_(\d+)_.*\.txt/', '$1', basename($file))
            );
        }
        // Sort by modification time, newest first
        usort($reports, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
    }
    
    $html = '<div class="table-responsive">';
    if (count($reports) > 0) {
        $html .= '<div class="text-right" style="margin-bottom: 10px;">';
        $html .= '<button type="button" class="btn btn-sm btn-warning" onclick="cleanupOldReports()">';
        $html .= '<i class="fa fa-trash"></i> Cleanup Old Reports (7+ days)</button>';
        $html .= '</div>';
        
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>Campaign</th><th>Generated</th><th>Age</th><th>Size</th><th>Actions</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($reports as $report) {
            $campaign = $simpledialer->getCampaign($report['campaign_id']);
            $campaign_name = $campaign ? $campaign['name'] : 'Campaign ' . $report['campaign_id'];
            
            // Calculate age
            $age_seconds = time() - $report['modified'];
            $age_days = floor($age_seconds / 86400);
            $age_hours = floor(($age_seconds % 86400) / 3600);
            
            if ($age_days > 0) {
                $age_text = $age_days . 'd ' . $age_hours . 'h';
                $age_class = $age_days >= 7 ? 'text-danger' : ($age_days >= 3 ? 'text-warning' : 'text-muted');
            } else {
                $age_text = $age_hours . 'h';
                $age_class = 'text-success';
            }
            
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($campaign_name) . '</td>';
            $html .= '<td>' . date('Y-m-d H:i:s', $report['modified']) . '</td>';
            $html .= '<td><span class="' . $age_class . '">' . $age_text . '</span></td>';
            $html .= '<td>' . number_format($report['size']) . ' bytes</td>';
            $html .= '<td>';
            $html .= '<div class="btn-group">';
            $html .= '<a href="?display=simpledialer&action=download_report&file=' . urlencode($report['filename']) . '" class="btn btn-xs btn-primary" target="_blank" title="Download Report">';
            $html .= '<i class="fa fa-download"></i></a> ';
            $html .= '<button type="button" class="btn btn-xs btn-info" onclick="viewReport(\'' . $report['filename'] . '\')" title="View Report">';
            $html .= '<i class="fa fa-eye"></i></button> ';
            $html .= '<button type="button" class="btn btn-xs btn-danger" onclick="deleteReport(\'' . $report['filename'] . '\')" title="Delete Report">';
            $html .= '<i class="fa fa-trash"></i></button>';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html .= '<p class="text-muted">No reports generated yet.</p>';
    }
    $html .= '</div>';
    
    echo $html;
    exit;
}

// Handle report download
if ($action == 'download_report' && !empty($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = '/var/log/asterisk/simpledialer_reports/' . $filename;
    
    if (file_exists($filepath) && strpos($filename, 'campaign_') === 0) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Report not found';
    }
    exit;
}

// Handle report viewing
if ($action == 'view_report' && !empty($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = '/var/log/asterisk/simpledialer_reports/' . $filename;
    
    if (file_exists($filepath) && strpos($filename, 'campaign_') === 0) {
        header('Content-Type: text/plain');
        echo file_get_contents($filepath);
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Report not found';
    }
    exit;
}

// Handle regenerating audio formats for existing recordings
if ($action == 'regenerate_formats' && !empty($_POST['recording_id'])) {
    try {
        $recording_id = $_POST['recording_id'];
        
        // Get recording details
        $sql = "SELECT filename, displayname FROM recordings WHERE id = ?";
        $sth = $simpledialer->db->prepare($sql);
        $sth->execute(array($recording_id));
        $recording = $sth->fetch(PDO::FETCH_ASSOC);
        
        if ($recording) {
            $upload_dir = '/var/lib/asterisk/sounds/en/';
            $filename = $recording['filename'];
            $base_filename = pathinfo($filename, PATHINFO_FILENAME);
            
            // Check if WAV source exists
            $source_wav = $upload_dir . $base_filename . '.wav';
            if (file_exists($source_wav)) {
                $formats = array('gsm', 'ulaw', 'alaw', 'sln');
                
                foreach ($formats as $format) {
                    $target_file = $upload_dir . $base_filename . '.' . $format;
                    
                    switch ($format) {
                        case 'gsm':
                            exec("sox " . escapeshellarg($source_wav) . " -r 8000 -c 1 " . escapeshellarg($target_file) . " 2>/dev/null");
                            break;
                        case 'ulaw':
                            exec("sox " . escapeshellarg($source_wav) . " -r 8000 -c 1 -e mu-law " . escapeshellarg($target_file) . " 2>/dev/null");
                            break;
                        case 'alaw':
                            exec("sox " . escapeshellarg($source_wav) . " -r 8000 -c 1 -e a-law " . escapeshellarg($target_file) . " 2>/dev/null");
                            break;
                        case 'sln':
                            exec("sox " . escapeshellarg($source_wav) . " -r 8000 -c 1 -e signed-integer -b 16 " . escapeshellarg($target_file) . " 2>/dev/null");
                            break;
                    }
                    
                    if (file_exists($target_file)) {
                        chown($target_file, 'asterisk');
                        chgrp($target_file, 'asterisk');
                        chmod($target_file, 0644);
                    }
                }
                
                echo json_encode(array('success' => true, 'message' => 'Audio formats regenerated successfully'));
            } else {
                echo json_encode(array('success' => false, 'message' => 'Source WAV file not found'));
            }
        } else {
            echo json_encode(array('success' => false, 'message' => 'Recording not found'));
        }
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
    }
    exit;
}

// Handle report deletion
if ($action == 'delete_report' && !empty($_POST['filename'])) {
    $filename = basename($_POST['filename']);
    $filepath = '/var/log/asterisk/simpledialer_reports/' . $filename;
    
    if (file_exists($filepath) && strpos($filename, 'campaign_') === 0) {
        if (unlink($filepath)) {
            echo json_encode(array('success' => true, 'message' => 'Report deleted successfully'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Failed to delete report'));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => 'Report not found'));
    }
    exit;
}

// Handle bulk report cleanup (older than 7 days)
if ($action == 'cleanup_old_reports') {
    $days_old = isset($_POST['days_old']) ? intval($_POST['days_old']) : 7;
    if ($days_old < 1) $days_old = 7;
    
    $deleted_count = $simpledialer->cleanupOldReports($days_old);
    
    echo json_encode(array(
        'success' => true, 
        'message' => "Deleted $deleted_count old reports (older than $days_old days)"
    ));
    exit;
}

// Get data for page
$campaigns = $simpledialer->getCampaigns();
$trunks = $simpledialer->getAvailableTrunks();
$audio_files = $simpledialer->getAudioFiles();

// Handle system recording uploads
if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK && isset($_POST['recording_name'])) {
    try {
        // Use standard Asterisk sounds directory
        $upload_dir = '/var/lib/asterisk/sounds/en/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['recording_name']);
        $temp_file = $_FILES['audio_file']['tmp_name'];
        $file_ext = pathinfo($_FILES['audio_file']['name'], PATHINFO_EXTENSION);
        
        // Create all audio formats that FreePBX expects
        $formats = array('wav', 'gsm', 'ulaw', 'alaw', 'sln', 'g729');
        $temp_wav = $upload_dir . $filename . '_temp.wav';
        
        // First, convert uploaded file to a standardized WAV format
        if (strtolower($file_ext) === 'wav') {
            move_uploaded_file($temp_file, $temp_wav);
        } else {
            move_uploaded_file($temp_file, $upload_dir . $filename . '.' . $file_ext);
            // Convert to standardized WAV format (16-bit, 8kHz, mono)
            exec("sox " . escapeshellarg($upload_dir . $filename . '.' . $file_ext) . " -r 8000 -c 1 -b 16 " . escapeshellarg($temp_wav) . " 2>/dev/null");
            unlink($upload_dir . $filename . '.' . $file_ext);
        }
        
        // Create all required formats from the WAV file
        foreach ($formats as $format) {
            $target_file = $upload_dir . $filename . '.' . $format;
            
            switch ($format) {
                case 'wav':
                    // Copy the standardized WAV
                    copy($temp_wav, $target_file);
                    break;
                case 'gsm':
                    // Convert to GSM
                    exec("sox " . escapeshellarg($temp_wav) . " -r 8000 -c 1 " . escapeshellarg($target_file) . " 2>/dev/null");
                    break;
                case 'ulaw':
                    // Convert to μ-law
                    exec("sox " . escapeshellarg($temp_wav) . " -r 8000 -c 1 -e mu-law " . escapeshellarg($target_file) . " 2>/dev/null");
                    break;
                case 'alaw':
                    // Convert to A-law
                    exec("sox " . escapeshellarg($temp_wav) . " -r 8000 -c 1 -e a-law " . escapeshellarg($target_file) . " 2>/dev/null");
                    break;
                case 'sln':
                    // Convert to signed linear
                    exec("sox " . escapeshellarg($temp_wav) . " -r 8000 -c 1 -e signed-integer -b 16 " . escapeshellarg($target_file) . " 2>/dev/null");
                    break;
                case 'g729':
                    // Skip G.729 as it requires licensed codec
                    continue 2;
            }
            
            // Set proper permissions for each file
            if (file_exists($target_file)) {
                chown($target_file, 'asterisk');
                chgrp($target_file, 'asterisk');
                chmod($target_file, 0644);
            }
        }
        
        // Clean up temporary WAV file
        if (file_exists($temp_wav)) {
            unlink($temp_wav);
        }
        
        // Add to recordings table
        $sql = "INSERT INTO recordings (displayname, filename, description) VALUES (?, ?, ?)";
        $sth = $simpledialer->db->prepare($sql);
        $sth->execute(array(
            $_POST['recording_name'],
            $filename,
            'Simple Dialer recording - uploaded via module'
        ));
        
        $audio_files = $simpledialer->getAudioFiles(); // Refresh list
        
        echo "<script>alert('Recording uploaded successfully!');</script>";
    } catch (Exception $e) {
        error_log("Simple Dialer audio upload error: " . $e->getMessage());
        echo "<script>alert('Error uploading recording. Please check the logs.');</script>";
    }
}

?>

<style>
/* Disable all animations for completed campaign progress bars */
.progress-bar.campaign-completed,
.progress-bar.campaign-completed::before,
.progress-bar.campaign-completed::after {
    animation: none !important;
    -webkit-animation: none !important;
    -moz-animation: none !important;
    -o-animation: none !important;
    -ms-animation: none !important;
}

/* Remove any striped background for completed campaigns */
.progress-bar.campaign-completed,
.progress-bar.campaign-completed.progress-bar-striped {
    background-image: none !important;
    background: #5cb85c !important;
}

/* Ensure no Bootstrap striped pattern overrides */
.progress-striped .progress-bar.campaign-completed,
.progress-bar-striped.campaign-completed {
    background-image: none !important;
    background: #5cb85c !important;
}
</style>

<div class="container-fluid">
    <h1><?php echo _('Simple Dialer'); ?> <small id="refreshStatus" style="display: none;"><i class="fa fa-spinner fa-spin"></i> <?php echo _('Auto-refreshing...'); ?></small></h1>
    
    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active">
            <a href="#campaigns" aria-controls="campaigns" role="tab" data-toggle="tab"><?php echo _('Campaigns'); ?></a>
        </li>
        <li role="presentation">
            <a href="#audio" aria-controls="audio" role="tab" data-toggle="tab"><?php echo _('Audio Files'); ?></a>
        </li>
        <li role="presentation">
            <a href="#reports" aria-controls="reports" role="tab" data-toggle="tab"><?php echo _('Reports'); ?></a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Campaigns Tab -->
        <div role="tabpanel" class="tab-pane active" id="campaigns">
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <!-- Add Campaign Button and Refresh -->
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#campaignModal">
                        <i class="fa fa-plus"></i> <?php echo _('Add Campaign'); ?>
                    </button>
                    <button type="button" class="btn btn-default" onclick="refreshCampaignTable()" title="Refresh campaign data">
                        <i class="fa fa-refresh"></i> <?php echo _('Refresh'); ?>
                    </button>
                    
                    <!-- Campaigns Table -->
                    <div style="margin-top: 20px;">
                        <table class="table table-striped" id="campaignsTable">
                            <thead>
                                <tr>
                                    <th><?php echo _('Name'); ?></th>
                                    <th><?php echo _('Description'); ?></th>
                                    <th><?php echo _('Status'); ?></th>
                                    <th><?php echo _('Contacts'); ?></th>
                                    <th><?php echo _('Progress'); ?></th>
                                    <th><?php echo _('Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <?php $stats = $simpledialer->getCampaignStats($campaign['id']); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                        <td><?php echo htmlspecialchars($campaign['description']); ?></td>
                                        <td>
                                            <span class="label label-<?php echo $campaign['status'] == 'active' ? 'success' : ($campaign['status'] == 'stopped' ? 'danger' : 'default'); ?>">
                                                <?php echo ucfirst($campaign['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $stats['total_contacts']; ?></td>
                                        <td>
                                            <?php if ($stats['total_contacts'] > 0): ?>
                                                <div class="progress">
                                                    <div class="progress-bar <?php 
                                                        if ($campaign['status'] == 'active') {
                                                            echo 'progress-bar-striped active';
                                                        } elseif ($campaign['status'] == 'completed') {
                                                            echo 'progress-bar-success campaign-completed';
                                                        }
                                                    ?>" role="progressbar" 
                                                         style="width: <?php echo ($stats['called_contacts'] / $stats['total_contacts']) * 100; ?>%;<?php echo ($campaign['status'] == 'completed') ? ' background-color: #5cb85c; animation: none !important;' : ''; ?>">
                                                        <?php echo $stats['called_contacts']; ?>/<?php echo $stats['total_contacts']; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No contacts</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-default" onclick="editCampaign(<?php echo $campaign['id']; ?>)" title="Edit Campaign">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info" onclick="manageContacts(<?php echo $campaign['id']; ?>, '<?php echo htmlspecialchars($campaign['name']); ?>')" title="Manage Contacts">
                                                    <i class="fa fa-users"></i>
                                                </button>
                                                <?php if ($campaign['status'] == 'active'): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="stopCampaign(<?php echo $campaign['id']; ?>)" title="Stop Campaign">
                                                        <i class="fa fa-stop"></i>
                                                    </button>
                                                <?php elseif (!empty($campaign['scheduled_time'])): ?>
                                                    <!-- Scheduled campaigns show status instead of start button -->
                                                    <?php
                                                    $scheduled_time = strtotime($campaign['scheduled_time']);
                                                    $current_time = time();
                                                    // If campaign is completed/stopped, show that status instead of scheduling status
                                                    if ($campaign['status'] == 'completed'): ?>
                                                        <span class="btn btn-sm btn-success" title="Campaign completed successfully">
                                                            <i class="fa fa-check"></i> Completed
                                                        </span>
                                                    <?php elseif ($campaign['status'] == 'stopped'): ?>
                                                        <span class="btn btn-sm btn-danger" title="Campaign was stopped">
                                                            <i class="fa fa-stop"></i> Stopped
                                                        </span>
                                                    <?php elseif ($current_time < $scheduled_time): ?>
                                                        <span class="btn btn-sm btn-info" title="Scheduled for <?php echo date('Y-m-d H:i:s', $scheduled_time); ?>">
                                                            <i class="fa fa-clock-o"></i> Scheduled
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="btn btn-sm btn-warning" title="Scheduled for <?php echo date('Y-m-d H:i:s', $scheduled_time); ?> - waiting for scheduler">
                                                            <i class="fa fa-hourglass-half"></i> Pending
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="startCampaign(<?php echo $campaign['id']; ?>)" title="Start Campaign" <?php echo ($stats['total_contacts'] == 0) ? 'disabled' : ''; ?>>
                                                        <i class="fa fa-play"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteCampaign(<?php echo $campaign['id']; ?>)" title="Delete Campaign">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audio Files Tab -->
        <div role="tabpanel" class="tab-pane" id="audio">
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-6">
                    <h3><?php echo _('Upload System Recording'); ?></h3>
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label><?php echo _('Recording Name'); ?> *</label>
                            <input type="text" name="recording_name" class="form-control" placeholder="e.g., welcome_message" required>
                            <small class="form-text text-muted"><?php echo _('Alphanumeric characters, underscores and dashes only'); ?></small>
                        </div>
                        <div class="form-group">
                            <label><?php echo _('Audio File'); ?> *</label>
                            <input type="file" name="audio_file" class="form-control" accept=".wav,.mp3,.gsm" required>
                            <small class="form-text text-muted"><?php echo _('Supported formats: WAV, MP3, GSM'); ?></small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-upload"></i> <?php echo _('Upload to System Recordings'); ?>
                        </button>
                    </form>
                </div>
                <div class="col-md-6">
                    <h3><?php echo _('Available System Recordings'); ?></h3>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> <?php echo _('These are FreePBX System Recordings that can be used in campaigns.'); ?>
                        <br><small><?php echo _('You can also manage recordings in Admin > System Recordings'); ?></small>
                    </div>
                    <div class="list-group" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($audio_files as $file): ?>
                            <div class="list-group-item">
                                <div class="list-group-item-heading">
                                    <i class="fa fa-volume-up"></i> <strong><?php echo htmlspecialchars($file['name']); ?></strong>
                                    <?php if (count($file['formats']) < 5): ?>
                                        <button type="button" class="btn btn-xs btn-warning pull-right" onclick="regenerateFormats(<?php echo $file['id']; ?>)" title="Regenerate Missing Audio Formats">
                                            <i class="fa fa-refresh"></i> Fix Formats
                                        </button>
                                    <?php else: ?>
                                        <span class="label label-success pull-right">All Formats OK</span>
                                    <?php endif; ?>
                                </div>
                                <div class="list-group-item-text">
                                    <small class="text-muted">ID: <?php echo $file['id']; ?> | File: <?php echo htmlspecialchars($file['filename']); ?></small><br>
                                    <small>
                                        <strong>Available formats:</strong> 
                                        <?php if (!empty($file['formats'])): ?>
                                            <span class="text-success"><?php echo strtoupper(implode(', ', $file['formats'])); ?></span>
                                        <?php else: ?>
                                            <span class="text-danger">None found!</span>
                                        <?php endif; ?>
                                        <?php if (count($file['formats']) < 5): ?>
                                            <span class="text-warning"> (<?php echo (5 - count($file['formats'])); ?> missing)</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($audio_files)): ?>
                            <div class="list-group-item">
                                <span class="text-muted"><?php echo _('No system recordings found. Upload one above or create them in Admin > System Recordings.'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-warning" style="margin-top: 15px;">
                        <strong><i class="fa fa-exclamation-triangle"></i> Missing Audio Formats?</strong><br>
                        <?php echo _('If you see red recordings in FreePBX System Recordings, click "Fix Formats" above to regenerate missing audio format files (GSM, μ-law, A-law, etc.).'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div role="tabpanel" class="tab-pane" id="reports">
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <h3><?php echo _('Campaign Reports'); ?></h3>
                    <div class="row">
                        <div class="col-md-8">
                            <button type="button" class="btn btn-primary" onclick="loadReports()">
                                <i class="fa fa-refresh"></i> <?php echo _('Refresh Reports'); ?>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info" style="margin-bottom: 0; padding: 8px;">
                                <small>
                                    <i class="fa fa-info-circle"></i> <strong><?php echo _('Auto Cleanup:'); ?></strong><br>
                                    <?php echo _('Reports older than 7 days are automatically deleted when campaigns complete.'); ?><br>
                                    <a href="#" data-toggle="modal" data-target="#cleanupInfoModal"><?php echo _('Cron setup info'); ?></a>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div id="reportsContainer" style="margin-top: 20px;">
                        <!-- Reports will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Campaign Modal -->
<div class="modal fade" id="campaignModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _('Campaign Configuration'); ?></h4>
            </div>
            <form id="campaignForm" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="campaignAction" value="add_campaign">
                    <input type="hidden" name="campaign_id" id="campaignId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo _('Campaign Name'); ?> *</label>
                                <input type="text" name="name" id="campaignName" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo _('Trunk'); ?> *</label>
                                <select name="trunk" id="campaignTrunk" class="form-control" required>
                                    <option value=""><?php echo _('Select Trunk'); ?></option>
                                    <?php foreach ($trunks as $trunk): ?>
                                        <option value="<?php echo $trunk['value']; ?>"><?php echo $trunk['text']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo _('Description'); ?></label>
                        <textarea name="description" id="campaignDescription" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo _('Audio File'); ?> *</label>
                                <select name="audio_file" id="campaignAudioFile" class="form-control" required>
                                    <option value=""><?php echo _('Select System Recording'); ?></option>
                                    <?php foreach ($audio_files as $file): ?>
                                        <option value="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo htmlspecialchars($file['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted"><?php echo _('Choose from FreePBX System Recordings'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo _('Caller ID'); ?></label>
                                <input type="text" name="caller_id" id="campaignCallerId" class="form-control" placeholder="Name <1234567890>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo _('Max Concurrent Calls'); ?></label>
                                <input type="number" name="max_concurrent" id="campaignMaxConcurrent" class="form-control" value="5" min="1" max="50">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo _('Delay Between Calls (seconds)'); ?></label>
                                <input type="number" name="delay_between_calls" id="campaignDelayBetweenCalls" class="form-control" value="2" min="1" max="300">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo _('Scheduled Time (optional)'); ?></label>
                                <input type="datetime-local" name="scheduled_time" id="campaignScheduledTime" class="form-control">
                                <small class="form-text text-muted">
                                    <strong><?php echo _('Auto-scheduling:'); ?></strong><br>
                                    <?php echo _('1. Set scheduled time and save campaign'); ?><br>
                                    <?php echo _('2. Upload contacts to the campaign'); ?><br>
                                    <?php echo _('3. Campaign will start automatically at scheduled time'); ?><br>
                                    <?php echo _('4. Leave blank to start immediately with Start button'); ?>
                                    <br><a href="#" data-toggle="modal" data-target="#schedulerInfoModal"><?php echo _('Scheduler setup info'); ?></a>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Upload Section -->
                    <hr>
                    <h4><?php echo _('Upload Contacts'); ?></h4>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label><?php echo _('CSV File (optional)'); ?></label>
                                <input type="file" name="csv_file" id="campaignCsvFile" class="form-control" accept=".csv">
                                <small class="form-text text-muted">
                                    <?php echo _('CSV format: phone_number,name (header row optional)'); ?><br>
                                    <?php echo _('Example: 15551234567,John Doe'); ?><br>
                                    <?php echo _('You can upload contacts now or add them later'); ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info" style="margin-top: 25px;">
                                <strong><?php echo _('Sample Format:'); ?></strong><br>
                                <code style="font-size: 11px;">phone_number,name<br>15551234567,John Doe</code>
                                <br><br>
                                <a href="?display=simpledialer&action=download_sample_csv" class="btn btn-xs btn-info" target="_blank">
                                    <i class="fa fa-download"></i> <?php echo _('Sample CSV'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo _('Save Campaign & Upload Contacts'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Contacts Modal -->
<div class="modal fade" id="contactsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _('Manage Contacts'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title"><?php echo _('Upload Contacts'); ?></h4>
                            </div>
                            <div class="panel-body">
                                <form id="contactsForm" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_contacts">
                                    <input type="hidden" name="campaign_id" id="contactsCampaignId">
                                    
                                    <div class="form-group">
                                        <label><?php echo _('CSV File'); ?></label>
                                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                        <small class="form-text text-muted">
                                            <?php echo _('CSV format: phone_number,name (header row optional)'); ?><br>
                                            <?php echo _('Example: 15551234567,John Doe'); ?>
                                        </small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-upload"></i> <?php echo _('Upload Contacts'); ?>
                                    </button>
                                </form>
                                
                                <hr>
                                
                                <div class="alert alert-info">
                                    <strong><?php echo _('Sample CSV Format:'); ?></strong><br>
                                    <code>phone_number,name<br>15551234567,John Doe<br>15559876543,Jane Smith</code>
                                    <br><br>
                                    <a href="?display=simpledialer&action=download_sample_csv" class="btn btn-xs btn-info">
                                        <i class="fa fa-download"></i> <?php echo _('Download Sample CSV'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title"><?php echo _('Current Contacts'); ?></h4>
                            </div>
                            <div class="panel-body" style="max-height: 400px; overflow-y: auto;">
                                <div id="contactsList">
                                    <!-- Contacts will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report View Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _('Campaign Report'); ?></h4>
            </div>
            <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
                <pre id="reportContent" style="white-space: pre-wrap; font-family: monospace;"></pre>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Info Modal -->
<div class="modal fade" id="cleanupInfoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _('Report Cleanup Information'); ?></h4>
            </div>
            <div class="modal-body">
                <h5><?php echo _('Automatic Cleanup'); ?></h5>
                <p><?php echo _('Reports are automatically cleaned up in two ways:'); ?></p>
                <ul>
                    <li><?php echo _('When campaigns complete: Reports older than 7 days are deleted'); ?></li>
                    <li><?php echo _('Manual cleanup: Use the "Cleanup Old Reports" button in the Reports tab'); ?></li>
                </ul>
                
                <h5><?php echo _('Scheduled Cleanup (Optional)'); ?></h5>
                <p><?php echo _('For automated daily cleanup, add this cron job:'); ?></p>
                <div class="well">
                    <code># Daily cleanup at 2 AM - delete reports older than 7 days<br>
0 2 * * * php /var/www/html/admin/modules/simpledialer/bin/cleanup_reports.php 7</code>
                </div>
                
                <p><?php echo _('For custom retention periods:'); ?></p>
                <div class="well">
                    <code># Keep reports for 14 days<br>
0 2 * * * php /var/www/html/admin/modules/simpledialer/bin/cleanup_reports.php 14<br><br>
# Keep reports for 30 days<br>
0 2 * * * php /var/www/html/admin/modules/simpledialer/bin/cleanup_reports.php 30</code>
                </div>
                
                <h5><?php echo _('Manual Cleanup'); ?></h5>
                <p><?php echo _('Run cleanup manually from command line:'); ?></p>
                <div class="well">
                    <code>cd /var/www/html/admin/modules/simpledialer<br>
php bin/cleanup_reports.php [days]</code>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Close'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Scheduler Info Modal -->
<div class="modal fade" id="schedulerInfoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _('Campaign Scheduler Information'); ?></h4>
            </div>
            <div class="modal-body">
                <h5><?php echo _('Automatic Scheduling'); ?></h5>
                <p><?php echo _('How to use scheduled campaigns:'); ?></p>
                <ol>
                    <li><?php echo _('Set the "Scheduled Time" when creating/editing a campaign'); ?></li>
                    <li><?php echo _('Upload contacts to the campaign'); ?></li>
                    <li><?php echo _('Campaign will start automatically at the scheduled time'); ?></li>
                    <li><?php echo _('Monitor progress with the auto-refresh feature'); ?></li>
                </ol>
                <div class="alert alert-info">
                    <strong><?php echo _('Note:'); ?></strong> <?php echo _('If you try to start before the scheduled time, you\'ll get an error message with the exact scheduled time.'); ?>
                </div>
                
                <h5><?php echo _('Automatic Scheduling (Optional)'); ?></h5>
                <p><?php echo _('For fully automatic campaign starting, set up this cron job:'); ?></p>
                <div class="well">
                    <code># Check every minute for scheduled campaigns<br>
* * * * * php /var/www/html/admin/modules/simpledialer/bin/scheduler.php</code>
                </div>
                
                <p><?php echo _('With automatic scheduling:'); ?></p>
                <ul>
                    <li><?php echo _('Campaigns with scheduled times will start automatically'); ?></li>
                    <li><?php echo _('No need to press the Start button'); ?></li>
                    <li><?php echo _('Check logs at /var/log/asterisk/ for scheduler activity'); ?></li>
                </ul>
                
                <h5><?php echo _('Best Practices'); ?></h5>
                <ul>
                    <li><?php echo _('Always upload contacts before the scheduled time'); ?></li>
                    <li><?php echo _('Test your audio files beforehand'); ?></li>
                    <li><?php echo _('Set realistic scheduled times (not in the past)'); ?></li>
                    <li><?php echo _('Monitor campaign progress in the web interface'); ?></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Close'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
function editCampaign(campaignId) {
    // Load campaign data and show modal
    $.get('?display=simpledialer&action=get_campaign&campaign_id=' + campaignId, function(data) {
        $('#campaignAction').val('edit_campaign');
        $('#campaignId').val(campaignId);
        $('#campaignName').val(data.name);
        $('#campaignDescription').val(data.description);
        $('#campaignAudioFile').val(data.audio_file);
        $('#campaignTrunk').val(data.trunk);
        $('#campaignCallerId').val(data.caller_id);
        $('#campaignMaxConcurrent').val(data.max_concurrent);
        $('#campaignDelayBetweenCalls').val(data.delay_between_calls);
        $('#campaignScheduledTime').val(data.scheduled_time);
        $('#campaignModal').modal('show');
    });
}

function manageContacts(campaignId, campaignName) {
    $('#contactsCampaignId').val(campaignId);
    $('#contactsModal .modal-title').text('Manage Contacts - ' + campaignName);
    loadContacts(campaignId);
    $('#contactsModal').modal('show');
}

function loadContacts(campaignId) {
    $.get('?display=simpledialer&action=get_contacts&campaign_id=' + campaignId, function(data) {
        $('#contactsList').html(data);
    });
}

function startCampaign(campaignId) {
    if (confirm('<?php echo _('Start this campaign?'); ?>')) {
        $.post('?display=simpledialer', {
            action: 'start_campaign',
            campaign_id: campaignId
        }, function(response) {
            if (response && response.success === false) {
                alert('Error: ' + response.message);
                return;
            }
            // Refresh campaign data and start auto-refresh
            refreshCampaignTable();
            setTimeout(function() {
                startSmartAutoRefresh();
            }, 1000);
        }, 'json').fail(function(xhr) {
            try {
                var response = JSON.parse(xhr.responseText);
                alert('Error: ' + response.message);
            } catch (e) {
                alert('Error starting campaign. Please try again.');
            }
        });
    }
}

function stopCampaign(campaignId) {
    if (confirm('<?php echo _('Stop this campaign?'); ?>')) {
        $.post('?display=simpledialer', {
            action: 'stop_campaign',
            campaign_id: campaignId
        }, function() {
            location.reload();
        });
    }
}

function deleteCampaign(campaignId) {
    if (confirm('<?php echo _('Delete this campaign and all its data?'); ?>')) {
        $.post('?display=simpledialer', {
            action: 'delete_campaign',
            campaign_id: campaignId
        }, function() {
            location.reload();
        });
    }
}


// Reset modal when adding new campaign
$('#campaignModal').on('hidden.bs.modal', function() {
    $('#campaignForm')[0].reset();
    $('#campaignAction').val('add_campaign');
    $('#campaignId').val('');
});

// Handle campaign form submission
$('#campaignForm').on('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    var scheduledTime = $('#campaignScheduledTime').val();
    var hasFile = $('#campaignCsvFile')[0].files.length > 0;
    
    $.ajax({
        url: '?display=simpledialer',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            // Close modal and reset form
            $('#campaignModal').modal('hide');
            $('#campaignForm')[0].reset();
            
            // Show success message
            var message = 'Campaign created successfully!';
            if (hasFile) {
                message += ' Contacts uploaded.';
            }
            if (scheduledTime) {
                var scheduledDate = new Date(scheduledTime);
                var now = new Date();
                if (scheduledDate > now) {
                    message += ' Campaign scheduled for ' + scheduledDate.toLocaleString() + '.';
                } else {
                    message += ' Campaign will start as soon as the scheduler runs.';
                }
            }
            alert(message);
            
            // Refresh the page to show the new campaign
            location.reload();
        },
        error: function() {
            alert('Error creating campaign. Please try again.');
        }
    });
});

// Handle contact form submission
$('#contactsForm').on('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    var campaignId = $('#contactsCampaignId').val();
    
    $.ajax({
        url: '?display=simpledialer',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            // Reset form
            $('#contactsForm')[0].reset();
            // Reload contacts list
            loadContacts(campaignId);
            // Show success message
            alert('<?php echo _('Contacts uploaded successfully!'); ?>');
            // Refresh campaign table to update statistics immediately and again after delay
            refreshCampaignTable();
            setTimeout(function() {
                refreshCampaignTable();
            }, 1000);
        },
        error: function() {
            alert('<?php echo _('Error uploading contacts. Please try again.'); ?>');
        }
    });
});

// Auto-refresh functionality for active campaigns
var refreshInterval;
var activeCampaigns = [];

function startSmartAutoRefresh() {
    // Always start auto-refresh every 30 seconds
    if (!refreshInterval) {
        console.log('Starting smart auto-refresh (30s intervals)...');
        refreshInterval = setInterval(function() {
            var onCampaignsTab = isOnCampaignsTab();
            var modalsOpen = areModalsOpen();
            
            console.log('Auto-refresh check - On campaigns tab:', onCampaignsTab, 'Modals open:', modalsOpen);
            
            // Only refresh if on campaigns tab and no modals are open
            if (onCampaignsTab && !modalsOpen) {
                console.log('Auto-refreshing campaign table...');
                refreshCampaignTable();
            } else {
                console.log('Skipping auto-refresh - Tab:', onCampaignsTab, 'Modals:', modalsOpen);
            }
        }, 30000);
    }
}

function stopAutoRefresh() {
    if (refreshInterval) {
        console.log('Stopping auto-refresh...');
        clearInterval(refreshInterval);
        refreshInterval = null;
        $('#refreshStatus').hide();
    }
}

function isOnCampaignsTab() {
    // Check if campaigns tab is the active tab
    var campaignsTabActive = $('#campaigns').hasClass('active');
    var campaignsLiActive = $('a[href="#campaigns"]').closest('li').hasClass('active');
    
    console.log('Tab detection - #campaigns.active:', campaignsTabActive, 'li.active:', campaignsLiActive);
    
    return campaignsTabActive || campaignsLiActive;
}

function areModalsOpen() {
    return $('.modal').hasClass('in') || $('.modal').is(':visible');
}

function refreshCampaignTable() {
    $.get('?display=simpledialer&action=get_campaign_progress', function(data) {
        console.log('Refreshing campaign table...', data);
        // Update all campaigns regardless of active status
        data.campaigns.forEach(function(campaign) {
                var row = $('#campaignsTable tbody tr').filter(function() {
                    return $(this).find('td:first').text().trim() === campaign.name;
                });
                
                if (row.length > 0) {
                    // Update status
                    var statusLabel = row.find('.label');
                    statusLabel.removeClass('label-success label-danger label-default');
                    if (campaign.status === 'active') {
                        statusLabel.addClass('label-success').text('Active');
                    } else if (campaign.status === 'completed') {
                        statusLabel.addClass('label-default').text('Completed');
                    } else if (campaign.status === 'stopped') {
                        statusLabel.addClass('label-danger').text('Stopped');
                    }
                    
                    // Update contacts count
                    row.find('td:eq(3)').text(campaign.total_contacts);
                    
                    // Update progress bar and handle cases where there are contacts
                    var progressContainer = row.find('td:eq(4)'); // Progress column
                    var progressBar = row.find('.progress-bar');
                    
                    if (campaign.total_contacts > 0) {
                        // Show progress bar if there are contacts
                        if (progressContainer.find('.progress').length === 0) {
                            // Need to create progress bar structure
                            progressContainer.html('<div class="progress"><div class="progress-bar" role="progressbar" style="width: 0%">0/' + campaign.total_contacts + '</div></div>');
                            progressBar = row.find('.progress-bar');
                        }
                        
                        var percentage = campaign.total_contacts > 0 ? 
                            (campaign.called_contacts / campaign.total_contacts) * 100 : 0;
                        progressBar.css('width', percentage + '%');
                        progressBar.text(campaign.called_contacts + '/' + campaign.total_contacts);
                    } else {
                        // No contacts, show message
                        progressContainer.html('<span class="text-muted">No contacts</span>');
                    }
                    
                    // Update progress bar styling based on status
                    if (campaign.status === 'completed') {
                        progressBar.removeClass('progress-bar-striped active');
                        progressBar.addClass('progress-bar-success campaign-completed');
                        // Force green background color and disable animation for completed campaigns
                        progressBar.css({
                            'background-color': '#5cb85c',
                            'animation': 'none',
                            '-webkit-animation': 'none',
                            '-moz-animation': 'none',
                            '-o-animation': 'none'
                        });
                    } else if (campaign.status === 'active') {
                        progressBar.addClass('progress-bar-striped active');
                        progressBar.removeClass('progress-bar-success campaign-completed');
                        progressBar.css({
                            'background-color': '',
                            'animation': '',
                            '-webkit-animation': '',
                            '-moz-animation': '',
                            '-o-animation': ''
                        }); // Reset to default
                    } else {
                        progressBar.removeClass('progress-bar-striped active progress-bar-success campaign-completed');
                        progressBar.css({
                            'background-color': '',
                            'animation': '',
                            '-webkit-animation': '',
                            '-moz-animation': '',
                            '-o-animation': ''
                        }); // Reset to default
                    }
                    
                    // Update action buttons
                    var startBtn = row.find('.btn-success');
                    var stopBtn = row.find('.btn-danger[title="Stop Campaign"]');
                    
                    if (campaign.status === 'active') {
                        startBtn.hide();
                        stopBtn.show();
                    } else {
                        stopBtn.hide();
                        startBtn.show();
                        
                        // Enable/disable start button based on contact count
                        if (campaign.total_contacts > 0) {
                            startBtn.prop('disabled', false);
                        } else {
                            startBtn.prop('disabled', true);
                        }
                    }
                }
            });
        
        // Check if we should start or stop auto-refresh
        // Just update the UI - smart auto-refresh handles the timing
        if (data.hasActiveCampaigns) {
            $('#refreshStatus').show();
            console.log('Active campaigns detected');
        } else {
            $('#refreshStatus').hide();
            console.log('No active campaigns detected');
        }
    }).fail(function() {
        console.log('Failed to refresh campaign data');
    });
}

// Load reports
function loadReports() {
    $.get('?display=simpledialer&action=get_reports', function(data) {
        $('#reportsContainer').html(data);
    }).fail(function() {
        $('#reportsContainer').html('<div class="alert alert-danger">Failed to load reports</div>');
    });
}

// View report in modal
function viewReport(filename) {
    $.get('?display=simpledialer&action=view_report&file=' + encodeURIComponent(filename), function(data) {
        $('#reportContent').text(data);
        $('#reportModal').modal('show');
    }).fail(function() {
        alert('Failed to load report');
    });
}

// Regenerate missing audio formats
function regenerateFormats(recordingId) {
    if (confirm('<?php echo _('Regenerate missing audio formats for this recording?'); ?>')) {
        $.post('?display=simpledialer', {
            action: 'regenerate_formats',
            recording_id: recordingId
        }, function(response) {
            if (response.success) {
                alert(response.message);
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json').fail(function() {
            alert('<?php echo _('Failed to regenerate audio formats'); ?>');
        });
    }
}

// Delete individual report
function deleteReport(filename) {
    if (confirm('<?php echo _('Delete this campaign report? This action cannot be undone.'); ?>')) {
        $.post('?display=simpledialer', {
            action: 'delete_report',
            filename: filename
        }, function(response) {
            if (response.success) {
                alert(response.message);
                loadReports(); // Refresh the reports list
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json').fail(function() {
            alert('<?php echo _('Failed to delete report'); ?>');
        });
    }
}

// Cleanup old reports
function cleanupOldReports() {
    if (confirm('<?php echo _('Delete all campaign reports older than 7 days? This action cannot be undone.'); ?>')) {
        $.post('?display=simpledialer', {
            action: 'cleanup_old_reports'
        }, function(response) {
            if (response.success) {
                alert(response.message);
                loadReports(); // Refresh the reports list
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json').fail(function() {
            alert('<?php echo _('Failed to cleanup old reports'); ?>');
        });
    }
}

// Start auto-refresh when page loads
$(document).ready(function() {
    startSmartAutoRefresh();
    
    // Load reports when reports tab is shown
    $('a[href="#reports"]').on('shown.bs.tab', function() {
        loadReports();
    });
});
</script>
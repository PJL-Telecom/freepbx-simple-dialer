# SimpleDailer Fixes Documentation

## Issues Found and Fixed

### 1. Channel String Format Issue for PJSIP Trunks
**Problem:** The daemon was incorrectly formatting the channel string for PJSIP trunks, causing "Extension does not exist" errors.

**Original Code (simpledialer_daemon.php line 176):**
```php
$channel = "{$trunk_tech}/{$contact['phone_number']}@{$trunk_name}";
```

**Fixed Code:**
```php
// Build channel string based on trunk technology
if (strtoupper($trunk_tech) == 'PJSIP') {
    // For PJSIP, use the format: PJSIP/number@trunk
    $channel = "PJSIP/{$contact['phone_number']}@{$trunk_name}";
} else if (strtoupper($trunk_tech) == 'SIP') {
    // For SIP, use the format: SIP/trunk/number
    $channel = "SIP/{$trunk_name}/{$contact['phone_number']}";
} else {
    // Default format for other technologies
    $channel = "{$trunk_tech}/{$trunk_name}/{$contact['phone_number']}";
}
```

### 2. Database Field Missing Default Value
**Problem:** The `hangup_cause` field in `simpledialer_call_logs` table didn't have a default value, causing SQL errors during call logging.

**Database Fix:**
```sql
ALTER TABLE simpledialer_call_logs MODIFY hangup_cause VARCHAR(50) DEFAULT '';
```

### 3. Incomplete INSERT Statement in logCall Function
**Problem:** The logCall function wasn't including all required fields in the INSERT statement.

**Original Code (simpledialer_daemon.php line 301-302):**
```php
$sql = "INSERT INTO simpledialer_call_logs (campaign_id, contact_id, phone_number, call_id, status, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())";
```

**Fixed Code:**
```php
$sql = "INSERT INTO simpledialer_call_logs (campaign_id, contact_id, phone_number, call_id, status, duration, hangup_cause, voicemail_detected, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
```

With corresponding execute array:
```php
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
```

## Required Updates for install.php

The install.php file needs to be updated to include the DEFAULT value for the hangup_cause field:

**Original Table Creation:**
```sql
CREATE TABLE IF NOT EXISTS simpledialer_call_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    campaign_id INT(11) NOT NULL,
    contact_id INT(11) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    call_id VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL,
    duration INT(11) NOT NULL DEFAULT 0,
    answer_time DATETIME,
    hangup_time DATETIME,
    hangup_cause VARCHAR(50) NOT NULL,
    voicemail_detected TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME,
    PRIMARY KEY (id),
    KEY idx_campaign (campaign_id),
    KEY idx_contact (contact_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Should be changed to:**
```sql
CREATE TABLE IF NOT EXISTS simpledialer_call_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Files Modified

1. `/var/www/html/admin/modules/simpledialer/bin/simpledialer_daemon.php`
   - Fixed makeCall() function (lines 167-185)
   - Fixed logCall() function (lines 300-320)

2. Database modification:
   - ALTER TABLE simpledialer_call_logs to add DEFAULT value to hangup_cause

## Testing Notes

After applying these fixes:
1. The daemon should be able to correctly originate calls through PJSIP trunks
2. Call logging should work without SQL errors
3. The system should properly handle different trunk technologies (PJSIP, SIP, etc.)

## GitHub Repository Update Required

The following files in the GitHub repository need to be updated:
1. `bin/simpledialer_daemon.php` - Apply the channel string and logCall fixes
2. `install.php` - Update the CREATE TABLE statement for simpledialer_call_logs
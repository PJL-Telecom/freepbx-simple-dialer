# FreePBX Simple Dialer Module

A comprehensive autodialer module for FreePBX that allows you to create and manage automated calling campaigns with scheduling, contact management, and detailed reporting.

## Features

### 🚀 Core Functionality
- **Automated Dialing**: Create campaigns that automatically dial through contact lists
- **Smart Scheduling**: Set campaigns to start automatically at specific times
- **Contact Management**: Upload CSV files with contact lists during campaign creation
- **Real-time Progress**: Live progress tracking with visual indicators
- **Campaign Reports**: Detailed reports with call statistics and results
- **Audio Integration**: Uses FreePBX system recordings for campaign audio

### 📊 Campaign Management
- **Multiple Campaign Types**: Immediate start or scheduled campaigns
- **Contact Upload**: Integrated CSV upload during campaign creation
- **Progress Tracking**: Real-time progress bars with completion status
- **Status Management**: Visual indicators for scheduled, active, completed campaigns
- **Automatic Cleanup**: Auto-delete old reports after 7 days

### ⏰ Advanced Scheduling
- **Automatic Scheduling**: Campaigns start automatically at scheduled times
- **Smart Validation**: Prevents manual starting of scheduled campaigns
- **Scheduler Daemon**: Background process monitors and starts campaigns
- **Visual Feedback**: Clear status indicators for scheduled vs active campaigns

### 🎯 User Experience
- **Streamlined Workflow**: Create campaign and upload contacts in one step
- **Smart Auto-refresh**: Updates every 30 seconds when viewing campaigns
- **Modal-aware Refresh**: Pauses updates when editing to avoid interruptions
- **Visual Progress**: Color-coded progress bars (blue=active, green=completed)
- **Confirmation Messages**: Clear feedback for all actions

## Installation

### Prerequisites
- FreePBX system with administrative access
- PHP 7.4+ with CLI access
- MySQL/MariaDB database
- Asterisk Manager Interface (AMI) configured
- `sox` audio conversion tool (for audio processing)

### Installation Steps

1. **Download the module:**
   ```bash
   cd /var/www/html/admin/modules/
   git clone https://github.com/PJL-Telecom/freepbx-simple-dialer.git simpledialer
   ```

2. **Install AGI script (required for call status tracking):**
   ```bash
   cp /var/www/html/admin/modules/simpledialer/agi/simpledialer_update.php /var/lib/asterisk/agi-bin/
   chmod +x /var/lib/asterisk/agi-bin/simpledialer_update.php
   chown asterisk:asterisk /var/lib/asterisk/agi-bin/simpledialer_update.php
   ```

3. **Install dialplan context:**
   ```bash
   cp /var/www/html/admin/modules/simpledialer/extensions_simpledialer.conf /etc/asterisk/
   asterisk -rx "dialplan reload"
   ```

4. **Set permissions:**
   ```bash
   chmod +x /var/www/html/admin/modules/simpledialer/bin/*.php
   chown -R asterisk:asterisk /var/www/html/admin/modules/simpledialer/
   ```

5. **Install the module:**
   - Go to Admin → Module Admin in FreePBX
   - Click "Scan for new modules"
   - Find "Simple Dialer" and click Install
   - Apply configuration changes

6. **Set up scheduler (required for automatic scheduling):**
   ```bash
   # Add to crontab for automatic campaign scheduling
   crontab -e

   # Add this line:
   * * * * * php /var/www/html/admin/modules/simpledialer/bin/scheduler.php >> /var/log/asterisk/simpledialer_scheduler.log 2>&1
   ```

### Upgrading from Previous Versions

If you're upgrading from an earlier version:

```bash
cd /var/www/html/admin/modules/simpledialer
git pull origin main

# Install/update AGI script
cp agi/simpledialer_update.php /var/lib/asterisk/agi-bin/
chmod +x /var/lib/asterisk/agi-bin/simpledialer_update.php
chown asterisk:asterisk /var/lib/asterisk/agi-bin/simpledialer_update.php

# Update dialplan
cp extensions_simpledialer.conf /etc/asterisk/
asterisk -rx "dialplan reload"

# No database migrations required
```

## Usage

### Creating a Campaign

1. **Navigate to Simple Dialer:**
   - Go to Applications → Simple Dialer

2. **Create New Campaign:**
   - Click "Add Campaign"
   - Fill in campaign details:
     - **Name**: Campaign identifier
     - **Description**: Optional description
     - **Audio File**: Select from system recordings
     - **Trunk**: Choose outbound trunk
     - **Caller ID**: Set caller ID for calls
     - **Max Concurrent**: Maximum simultaneous calls
     - **Delay Between Calls**: Seconds between call attempts
     - **Scheduled Time**: Optional - set for automatic scheduling

3. **Upload Contacts:**
   - In the same modal, upload a CSV file with contacts
   - CSV format: `phone_number,name`
   - Example: `15551234567,John Doe`

4. **Save and Start:**
   - Click "Save Campaign & Upload Contacts"
   - For immediate campaigns: Click the green play button
   - For scheduled campaigns: They start automatically at the scheduled time

### Campaign Types

#### Immediate Campaigns
- No scheduled time set
- Start manually with the green play button
- Begin dialing immediately when started

#### Scheduled Campaigns
- Set a specific date/time for automatic start
- Show blue "Scheduled" or orange "Pending" status
- Start automatically via the scheduler daemon
- Cannot be started manually (prevents conflicts)

### Monitoring Campaigns

#### Real-time Updates
- **Live Progress Tracking**: Campaign progress updates every 2 seconds during active calls
- **Frontend Auto-refresh**: Page refreshes every 30 seconds to show latest campaign status
- **Progress Bars**: Visual completion status with live "X/Y calling" indicators
- **Color Coding**: Blue (active), Green (completed), Red (stopped)
- **Call Status**: Shows "calling" → "called" transition in real-time

#### Campaign Status
- **Inactive**: Newly created, ready to start
- **Scheduled**: Future scheduled time set
- **Pending**: Past scheduled time, waiting for scheduler
- **Active**: Currently running and making calls (shows live progress)
- **Completed**: Finished successfully
- **Stopped**: Manually stopped
- **Failed**: Error occurred during execution

### Reports and Analytics

#### Automatic Report Generation
- Generated automatically when campaigns complete
- Saved to `/var/log/asterisk/simpledialer_reports/`
- Include detailed call statistics, contact details, and success rates
- Emailed to system administrator if configured

#### Detailed Call Status Tracking
Reports now include granular call outcome statistics:
- **Answered**: Calls that were successfully connected
- **No Answer**: Calls that rang but weren't picked up
- **Busy**: Line was busy
- **Congestion**: Network or trunk congestion
- **Unavailable**: Channel unavailable
- **Cancelled**: Call was cancelled before completion

Additional metrics:
- Total and average call duration
- Voicemail detection (human vs. answering machine)
- Answer and hangup timestamps
- Hangup cause codes for troubleshooting

#### Report Management
- View reports in the Reports tab
- Download individual reports
- Automatic cleanup after 7 days
- Manual deletion available

## Configuration

### Audio Files
The module uses FreePBX system recordings for campaign audio:

1. **Upload Recordings:**
   - Go to Audio Files tab in Simple Dialer
   - Upload WAV, MP3, or GSM files
   - Files are automatically converted to all required formats

2. **System Integration:**
   - Recordings are stored in `/var/lib/asterisk/sounds/en/`
   - Automatically generates multiple audio formats
   - Integrated with FreePBX recording management

### Trunk Configuration
- Use existing FreePBX trunks for outbound calling
- Supports SIP, IAX, and other trunk types
- Configure trunk capacity for concurrent calls

### Scheduler Configuration
The scheduler daemon enables automatic campaign starting:

```bash
# Check scheduler status
tail -f /var/log/asterisk/simpledialer_scheduler.log

# Manual scheduler run (for testing)
php /var/www/html/admin/modules/simpledialer/bin/scheduler.php
```

## File Structure

```
simpledialer/
├── README.md                          # This file
├── CHANGELOG.md                       # Detailed version history
├── module.xml                         # FreePBX module definition
├── Simpledialer.class.php            # Main module class
├── page.simpledialer.php             # Web interface
├── extensions_simpledialer.conf      # Asterisk dialplan context
├── agi/
│   └── simpledialer_update.php       # AGI script for database updates
├── bin/
│   ├── simpledialer_daemon.php       # Campaign dialing daemon
│   └── scheduler.php                  # Automatic campaign scheduler
├── install.php                       # Installation script
└── uninstall.php                     # Uninstallation script
```

## Database Schema

The module creates three main tables:

- **simpledialer_campaigns**: Campaign definitions and settings
- **simpledialer_contacts**: Contact lists for each campaign
- **simpledialer_call_logs**: Detailed call attempt logs

## Troubleshooting

### Common Issues

#### Campaigns Not Starting Automatically
```bash
# Check scheduler is running
ps aux | grep scheduler.php

# Check scheduler logs
tail -f /var/log/asterisk/simpledialer_scheduler.log

# Verify cron job
crontab -l | grep scheduler
```

#### Audio Not Playing
```bash
# Check audio file formats exist
ls -la /var/lib/asterisk/sounds/en/your_audio_file.*

# Check asterisk can access files
chown asterisk:asterisk /var/lib/asterisk/sounds/en/*
```

#### Calls Not Connecting
```bash
# Check AMI connection
asterisk -rx "manager show connected"

# Check trunk availability
asterisk -rx "sip show peers" # or "pjsip show endpoints"

# Check dialplan
asterisk -rx "dialplan show simpledialer-outbound"
```

**Note on Trunk Authentication (v1.1.1+):**
The module now routes calls through FreePBX's outbound routes (`Local/{number}@from-internal`) instead of dialing trunks directly. This ensures:
- Proper trunk authentication (no more 403 errors)
- Outbound route rules are applied correctly
- Caller ID manipulation works as configured
- Compatible with all trunk types (PJSIP, SIP, IAX, etc.)

If you're upgrading from an older version and experiencing issues, ensure:
1. Your outbound routes are configured correctly in FreePBX
2. The trunk you selected in the campaign is accessible via outbound routes
3. Test by making a regular call from an extension first

### Log Files
- **Scheduler**: `/var/log/asterisk/simpledialer_scheduler.log`
- **Campaign Logs**: `/var/log/asterisk/simpledialer_[campaign_id].log`
- **Reports**: `/var/log/asterisk/simpledialer_reports/`
- **FreePBX**: `/var/log/asterisk/freepbx.log`

## Development

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Code Style
- Follow FreePBX module development standards
- Use PSR-4 autoloading conventions
- Document all public methods
- Include error handling and logging

## License

This project is licensed under the GPL v3 License - see the LICENSE file for details.

## Support

- **Issues**: Report bugs and feature requests on GitHub
- **Documentation**: Check the FreePBX wiki for general module development
- **Community**: Join the FreePBX community forums

## Changelog

### v1.1.1 (Latest)

#### Fixed Issues
- **403 Trunk Authentication Errors**: Campaigns now route calls through FreePBX outbound routes (`Local/{number}@from-internal`) instead of dialing trunks directly. Ensures proper trunk authentication and routing rules are applied.
- **All Calls Showing "Answered"**: Implemented granular call status tracking with accurate reporting of answered, no-answer, busy, congestion, unavailable, and cancelled calls.
- **Campaign Stuck In-Progress**: Fixed daemon getting stuck waiting for calls to complete. Campaigns now properly transition to "completed" status.
- **Caller ID Not Honored**: Fixed caller ID propagation through Local channels. Campaign caller ID now displays correctly on receiving end.
- **Progress Bar Not Updating Live**: Implemented real-time progress tracking with database polling. Frontend now shows live updates every 2 seconds during campaign execution.
- **Incorrect Call Durations**: Fixed duration calculation bug that showed millions of seconds. Now uses proper ANSWER_EPOCH for accurate duration tracking.
- **Premature Campaign Completion**: Removed channel status checking that caused campaigns to complete before calls finished.

#### New Features
- **AGI Database Update Script**: New `agi/simpledialer_update.php` provides reliable database updates using FreePBX's authenticated connection
- **Live Progress Tracking**: Real-time campaign progress with "X/Y calling" updates during active calls
- **Granular Call Status**: Detailed breakdown of call outcomes in reports
- **Enhanced Campaign Reports**: Comprehensive statistics with call metrics, duration analysis, and voicemail detection
- **Active Campaign Status**: Campaign status now changes to "active" during execution for better visibility

#### Technical Improvements
- Database polling every 2 seconds for status updates
- Campaign and contact status tracking throughout call lifecycle
- Prevention of duplicate database updates via flag system
- Proper handling of h-extension to avoid status overwrites
- Maps Asterisk DIALSTATUS to user-friendly status names

### v1.1.0
- Added AMD (Answering Machine Detection) support
- New dialplan context for voicemail detection
- Updated database schema
- Improved deployment scripts

### v1.0.0
- Initial release
- Campaign creation and management
- Contact CSV upload integration
- Automatic scheduling with cron
- Real-time progress tracking
- Report generation and email
- Smart auto-refresh interface
- System recording integration
- Comprehensive error handling

## Credits

Developed for the FreePBX community. Special thanks to all contributors and testers.

---

**Note**: This module handles outbound calling. Ensure compliance with local telemarketing and calling regulations in your jurisdiction.

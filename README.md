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
   git clone https://github.com/yourusername/freepbx-simple-dialer.git simpledialer
   ```

2. **Set permissions:**
   ```bash
   chmod +x /var/www/html/admin/modules/simpledialer/bin/*.php
   chown -R asterisk:asterisk /var/www/html/admin/modules/simpledialer/
   ```

3. **Install the module:**
   - Go to Admin → Module Admin in FreePBX
   - Click "Scan for new modules"
   - Find "Simple Dialer" and click Install
   - Apply configuration changes

4. **Set up scheduler (required for automatic scheduling):**
   ```bash
   # Add to crontab for automatic campaign scheduling
   crontab -e
   
   # Add this line:
   * * * * * php /var/www/html/admin/modules/simpledialer/bin/scheduler.php >> /var/log/asterisk/simpledialer_scheduler.log 2>&1
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
- Auto-refresh every 30 seconds when viewing campaigns
- Progress bars show completion status
- Color coding: Blue (active), Green (completed), Red (stopped)

#### Campaign Status
- **Inactive**: Newly created, ready to start
- **Scheduled**: Future scheduled time set
- **Pending**: Past scheduled time, waiting for scheduler
- **Active**: Currently running and making calls
- **Completed**: Finished successfully
- **Stopped**: Manually stopped
- **Failed**: Error occurred during execution

### Reports and Analytics

#### Automatic Report Generation
- Generated automatically when campaigns complete
- Saved to `/var/log/asterisk/simpledialer_reports/`
- Include call statistics, contact details, and success rates
- Emailed to system administrator if configured

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
├── module.xml                         # FreePBX module definition
├── Simpledialer.class.php            # Main module class
├── page.simpledialer.php             # Web interface
├── bin/
│   ├── simpledialer_daemon.php       # Campaign dialing daemon
│   └── scheduler.php                  # Automatic campaign scheduler
└── install.php                       # Installation script
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
# FreePBX Simple Dialer - Codebase Documentation for AI Assistants

This document provides comprehensive information about the FreePBX Simple Dialer module structure, architecture, and conventions to assist AI assistants in understanding and working with this codebase.

## Document Purpose

This CLAUDE.md file serves as the primary reference for AI assistants (like Claude) working with this codebase. It provides:
- Complete codebase structure and organization
- Development patterns and conventions
- Integration points with FreePBX and Asterisk
- Database schemas and query patterns
- Deployment and testing approaches
- Historical context and common pitfalls

**Related Documentation:**
- `README.md` - User-facing installation and usage guide
- `CHANGELOG.md` - Version history and release notes (v1.1.1 current)
- `CONTRIBUTING.md` - Contribution guidelines and coding standards
- `FIXES_DOCUMENTATION.md` - Historical bug fixes and solutions reference

## 1. Overall Directory Structure and Organization

```
freepbx-simple-dialer/
├── README.md                        # Main user documentation
├── README-v2.md                     # Alternative documentation version
├── CHANGELOG.md                     # Semantic versioning change history
├── CONTRIBUTING.md                  # Contributor guidelines
├── LICENSE                          # GPL v3 license
├── .gitignore                       # Git ignore patterns
├── CLAUDE.md                        # This file - AI assistant documentation
├── module.xml                       # FreePBX module metadata and database schema
├── module.sig                       # Module signature (auto-generated)
├── Simpledialer.class.php           # Main module class (BMO implementation) - 592 lines
├── functions.inc.php                # FreePBX hook functions - 22 lines
├── install.php                      # Installation script - 130 lines
├── uninstall.php                    # Uninstallation script - 45 lines
├── page.simpledialer.php            # Web UI and AJAX handlers - 1387 lines
├── extensions_simpledialer.conf     # Asterisk dialplan context (47 lines)
├── agi/
│   └── simpledialer_update.php      # AGI script for call status updates - 80 lines
├── bin/
│   ├── simpledialer_daemon.php      # Campaign execution daemon - 737 lines
│   ├── scheduler.php                # Automatic campaign scheduler - 126 lines
│   └── cleanup_reports.php          # Report cleanup utility
├── assets/
│   └── css/
│       └── simpledialer.css         # UI styling - ~100 lines
└── .git/                            # Git repository data

Total Project Size: ~3500 lines of PHP code + dialplan + CSS
```

### Directory Breakdown by Function

**Root Level** - Module metadata, entry points, and configuration
**agi/** - FreePBX Asterisk Gateway Interface integration
**bin/** - Command-line utilities (daemon, scheduler, cleanup)
**assets/** - Frontend styling and resources

## 2. Key Files and Their Purposes

### Core Module Files

#### `Simpledialer.class.php` (592 lines)
**Purpose**: Main module class implementing FreePBX BMO interface
**Key Responsibilities**:
- Campaign CRUD operations (create, read, update, delete)
- Contact management and CSV import/normalization
- Campaign statistics and reporting
- Audio file discovery from FreePBX recordings
- Trunk discovery from FreePBX database
- Dialplan context creation/removal
- Daemon process management
- Report generation and file management

**Key Methods**:
- `addCampaign($data)` - Create campaign with validation
- `uploadContacts($campaign_id, $csv_file)` - Import contacts from CSV
- `startCampaign($campaign_id)` - Initiate campaign execution
- `stopCampaign($campaign_id)` - Terminate running campaign
- `getCampaignStats($campaign_id)` - Query campaign progress
- `generateCampaignReport($campaign_id)` - Create detailed reports
- `getAvailableTrunks()` - Query FreePBX trunks table
- `getAudioFiles()` - List available system recordings
- `normalizePhoneNumber($phone)` - Standardize phone number format
- `createDialplanContexts()` - Write to extensions_custom.conf
- `startDialerDaemon($campaign_id)` - Fork daemon process

#### `page.simpledialer.php` (1,387 lines)
**Purpose**: Web interface and AJAX request handler
**Key Responsibilities**:
- HTTP routing for AJAX and traditional requests
- Campaign form UI rendering
- Audio file management and upload handling
- Report display and management
- Real-time progress tracking
- Contact list visualization
- Interactive tabs for campaigns, audio files, and reports

**Key AJAX Endpoints** (action parameter routing):
- `get_campaign_stats` - Fetch campaign progress
- `get_campaign` - Fetch campaign details
- `get_contacts` - Display contact list
- `get_campaign_progress` - All campaigns for live refresh
- `get_reports` - List available reports
- `download_report` - File download handler
- `view_report` - Display report content
- `delete_report` - Remove report file
- `cleanup_old_reports` - Bulk delete aged reports
- `download_sample_csv` - CSV template download
- `regenerate_formats` - Audio codec conversion
- Audio file upload handling with sox conversion

#### `module.xml` (64 lines)
**Purpose**: FreePBX module metadata and database schema definition
**Key Sections**:
- Module metadata (name, version, publisher, license)
- Three-table database schema with field definitions:
  - `simpledialer_campaigns` - Campaign configuration and status
  - `simpledialer_contacts` - Phone numbers and attempt tracking
  - `simpledialer_call_logs` - Detailed call records and outcomes
- Menu item registration (Applications → Simple Dialer)
- Changelog entries

#### `functions.inc.php` (22 lines)
**Purpose**: FreePBX hook integration
**Functionality**:
- `simpledialer_get_config($engine)` - Register dialplan includes
- `simpledialer_hook_core($viewing_itemid, $request)` - Core hook stub
- Uses FreePBX extension API to include simpledialer-outbound context

#### `install.php` (130 lines)
**Purpose**: Module initialization on installation
**Tasks**:
- Create three database tables with proper foreign keys
- Create audio sounds directory
- Copy extension context to extensions_simpledialer.conf
- Add include to extensions_custom.conf
- Make daemon script executable
- Reload Asterisk dialplan
- Auto-copy sample audio files if available

#### `uninstall.php` (45 lines)
**Purpose**: Clean uninstallation
**Tasks**:
- Remove dialplan context files
- Remove includes from extensions_custom.conf
- Drop all three database tables
- Reload Asterisk dialplan
- Preserve sounds directory for user data

### Backend Processing Files

#### `bin/simpledialer_daemon.php` (737 lines)
**Purpose**: Campaign execution engine running as separate CLI process
**Key Components**:

**Class**: SimpleDialerDaemon
**Responsibilities**:
- Load campaign configuration from database
- Query pending contacts for dialing
- Manage concurrent call limiting
- Interact with Asterisk Manager Interface (AMI) for origination
- Track active calls in memory
- Poll database for call status updates
- Handle call completion and cleanup
- Generate campaign reports
- Email reports to administrator

**Key Methods**:
- `runCampaign()` - Main execution loop
- `makeCall($contact)` - Originate call via AMI
- `waitForAvailableSlot()` - Enforce max concurrent calls
- `cleanupCompletedCalls()` - Poll database for status changes
- `processAMIEvents()` - Check call_logs table for updates
- `handleCallStatusEvent($event)` - Parse and store call results
- `mapDialStatus($asterisk_status)` - Convert DIALSTATUS codes
- `updateCampaignStatus($status)` - Transition campaign state
- `getCallStatistics()` - Generate final metrics
- `generateCampaignReport()` - Create detailed report
- `saveCampaignReport()` - Write report to file
- `emailCampaignReport()` - Send email notification

**Execution Flow**:
1. Invoked by Simpledialer class with campaign_id argument
2. Loads campaign settings and pending contacts
3. Establishes AMI connection to Asterisk
4. Iterates contacts with configured delay between calls
5. For each contact, originates call to Local/{number}@from-internal
6. Maintains active_calls array tracking in-progress calls
7. Polls database every 2 seconds for call status updates
8. Waits for completion with 2-minute timeout
9. Generates and saves report
10. Updates campaign status to "completed"

**Environment**:
- CLI-only execution (php_sapi_name() === 'cli')
- Requires FreePBX bootstrap via /etc/freepbx.conf
- Uses FreePBX::Database() and AMI_AsteriskManager
- Runs with output to campaign-specific log file
- Manages stop signal via /tmp/simpledialer_stop_[campaign_id]

#### `bin/scheduler.php` (126 lines)
**Purpose**: Cron-based campaign scheduler
**Key Responsibilities**:
- Runs every minute via crontab
- Queries for campaigns with scheduled_time <= NOW()
- Filters by status (pending, inactive)
- Validates campaigns have contacts
- Invokes startCampaign() for each ready campaign
- Logs all activities and errors
- Handles exceptions without crashing

**Key Query**:
```sql
SELECT id, name, scheduled_time, status FROM simpledialer_campaigns 
WHERE status IN ('pending', 'inactive') 
AND scheduled_time IS NOT NULL 
AND scheduled_time <= NOW()
ORDER BY scheduled_time
```

**Cron Entry** (from README):
```
* * * * * php /var/www/html/admin/modules/simpledialer/bin/scheduler.php >> /var/log/asterisk/simpledialer_scheduler.log 2>&1
```

#### `agi/simpledialer_update.php` (80 lines)
**Purpose**: AGI script for call status database updates
**Invoked By**: Asterisk dialplan (extensions_simpledialer.conf)
**Responsibility**: Update call_logs table with call completion details

**Call from Dialplan**:
```
AGI(simpledialer_update.php,${CALL_ID},ANSWER,${CALL_DURATION},${ANSWER_TIME},${HANGUP_TIME},NORMAL,${VOICEMAIL})
```

**Parameters**:
1. call_id - Unique call identifier
2. status - DIALSTATUS from Asterisk
3. duration - Call duration in seconds
4. answer_time - Timestamp when call answered
5. hangup_time - Timestamp when call ended
6. hangup_cause - Technical hangup reason
7. voicemail - 0 or 1 from AMD detection

**Processing**:
- Maps Asterisk DIALSTATUS to friendly names (ANSWER→answered, NOANSWER→no-answer, etc.)
- Updates simpledialer_call_logs via prepared statement
- Logs to Asterisk error log for debugging
- Writes VERBOSE response to Asterisk

### Asterisk Integration

#### `extensions_simpledialer.conf` (47 lines)
**Purpose**: Asterisk dialplan context for campaign calls
**Context**: [simpledialer-outbound]

**Call Flow**:
1. **Caller ID Setup** (lines 5-18):
   - Checks if CAMPAIGN_CID_NUM is set
   - Applies campaign caller ID override or preserves original
   - Sets CALLERID and CONNECTEDLINE variables with __ prefix for Local channel inheritance

2. **Answer Tracking** (lines 19-20):
   - Records ANSWER_EPOCH and ANSWER_TIME before routing

3. **Voicemail Detection** (lines 21-31):
   - Runs AMD() function
   - Routes to "human" label for human answer (immediate playback)
   - Routes to "vm" label for voicemail (wait for beep + playback)

4. **Audio Playback** (lines 24-25, 27-31):
   - Plays AUDIO_PATH (from campaign config)
   - Applies different timing for human vs voicemail

5. **Cleanup and Reporting** (lines 32-35):
   - Records HANGUP_TIME
   - Calculates CALL_DURATION from EPOCH values
   - Sets SIMPLEDIALER_UPDATED flag to prevent duplicate AGI calls
   - Calls AGI script with call details

6. **Hangup Handler** (lines 38-47):
   - h-extension catches all call scenarios
   - Checks SIMPLEDIALER_UPDATED flag to avoid duplicate updates
   - Handles answered vs unanswered calls differently
   - Passes DIALSTATUS for not-answered calls
   - Calls AGI with appropriate parameters

## 3. Programming Languages and Technologies Used

### Core Technologies

**PHP** (Primary)
- Version: 7.4+ required (from README)
- Dialect: Procedural + Object-Oriented
- Database: PDO with prepared statements
- CLI: php-cli for daemon/scheduler execution
- Framework Integration: FreePBX framework classes

**SQL** (MySQL/MariaDB)
- Database: asterisk (FreePBX default)
- Engine: InnoDB with charset utf8mb4
- Queries: Always prepared statements with parameterized bindings
- Indexes: status, campaign_id, contact_id on key tables

**Asterisk Dialplan** (Asterisk Extensible Language - AEL)
- Context-based syntax
- AMD() application for voicemail detection
- AGI() for call status database updates
- Variable-based call tracking
- h-extension for hangup handling

**JavaScript** (Frontend)
- ES5 for FreePBX compatibility
- jQuery for AJAX requests
- Bootstrap 3 UI framework
- Auto-refresh logic every 30 seconds on campaigns tab
- Modal state detection to pause refresh during editing

**CSS** (Bootstrap-based)
- Bootstrap 3 components
- Custom campaign status styling
- Progress bar animations
- Responsive layout

**Command-line Tools**
- `sox` - Audio format conversion (WAV, GSM, μ-law, A-law, SLN)
- `asterisk -rx` - Dialplan reload commands
- Standard Unix utilities (mkdir, chmod, chown, etc.)

### FreePBX Framework Integration

**Key Classes Used**:
- `FreePBX` - Main framework class
- `FreePBX::Database()` - Database connection pool
- `FreePBX::Simpledialer()` - Module instance via automatic loading
- `FreePBX_Helpers` - Base class for module
- `BMO` interface - FreePBX module base requirement

**External Libraries**:
- `/var/www/html/admin/libraries/php-asmanager.php` - AGI_AsteriskManager class
  - `send_request()` - AMI command sending
  - `connect()` - AMI connection management
  - `disconnect()` - Clean connection closure

## 4. Configuration File Patterns and Conventions

### Database Configuration
- **Location**: Defined in module.xml database section
- **Character Set**: utf8mb4 (supports international characters)
- **Engine**: InnoDB (transaction support, foreign keys)
- **Naming Convention**: `simpledialer_[entity]` prefix

**Table Pattern**:
```xml
<table name="simpledialer_[entity]">
  <field name="id" type="integer" primarykey="true" autoincrement="true"/>
  <field name="campaign_id" type="integer"/>
  <field name="status" type="string" length="20"/>
  <field name="created_at" type="datetime"/>
  <field name="updated_at" type="datetime"/>
</table>
```

### File Path Conventions

**Audio Files**: `/var/lib/asterisk/sounds/en/[filename].[format]`
- Formats: wav, gsm, ulaw, alaw, sln
- Ownership: asterisk:asterisk
- Permissions: 0644

**Reports**: `/var/log/asterisk/simpledialer_reports/campaign_[id]_[timestamp].txt`
- Format: Plain text with section headers
- Retention: 7 days (configurable cleanup)
- Ownership: asterisk:asterisk

**Logs**: `/var/log/asterisk/simpledialer_[id].log`
- Campaign-specific execution logs
- Scheduler log: simpledialer_scheduler.log
- Format: Echo-based text output

**Stop Signal**: `/tmp/simpledialer_stop_[campaign_id]`
- File-based IPC for campaign termination
- Checked in daemon loop

**Dialplan Context**: `/etc/asterisk/extensions_simpledialer.conf`
- Separate from core Asterisk configs
- Included via `/etc/asterisk/extensions_custom.conf`

### Module Configuration

**module.xml Sections**:
```xml
<module>
  <rawname>simpledialer</rawname>              <!-- Module identifier -->
  <repo>standard</repo>                        <!-- Repository source -->
  <category>Applications</category>            <!-- FreePBX menu location -->
  <depends><version>15.0</version></depends>   <!-- Minimum FreePBX version -->
  <database>                                   <!-- Schema definition -->
    <table name="...">...</table>
  </database>
  <menuitems>                                  <!-- UI menu registration -->
    <simpledialer>Simple Dialer</simpledialer>
  </menuitems>
</module>
```

### PHP Variable Naming
- **Public fields**: $campaign, $contacts, $db
- **Private fields**: $campaign_id, $ami, $active_calls (underscore prefix)
- **Method params**: $data, $campaign_id, $contact_id
- **Returned data**: $sql, $sth, $result (prepared statement handle)
- **Constants**: FREEPBX_IS_AUTH, UPLOAD_ERR_OK

## 5. Database Interaction Patterns

### PDO Prepared Statements

**Pattern**: All database access uses prepared statements to prevent SQL injection

**Standard Pattern**:
```php
$sql = "SELECT * FROM simpledialer_campaigns WHERE id = ?";
$sth = $this->db->prepare($sql);
$sth->execute(array($campaign_id));
$campaign = $sth->fetch(PDO::FETCH_ASSOC);
```

**Multi-row Pattern**:
```php
$sql = "SELECT * FROM simpledialer_contacts WHERE campaign_id = ? ORDER BY id";
$sth = $this->db->prepare($sql);
$sth->execute(array($campaign_id));
$contacts = $sth->fetchAll(PDO::FETCH_ASSOC);
```

**Insert Pattern**:
```php
$sql = "INSERT INTO simpledialer_campaigns (name, description) VALUES (?, ?)";
$sth = $this->db->prepare($sql);
$sth->execute(array($name, $description));
return $this->db->lastInsertId();  // Get auto-increment ID
```

**Update Pattern**:
```php
$sql = "UPDATE simpledialer_campaigns SET status = ?, updated_at = NOW() WHERE id = ?";
$sth = $this->db->prepare($sql);
$sth->execute(array($status, $campaign_id));
```

### Key Queries

**Campaign Lookup**:
```sql
SELECT * FROM simpledialer_campaigns WHERE id = ?
```

**Contact Query**:
```sql
SELECT * FROM simpledialer_contacts WHERE campaign_id = ? AND status = 'pending' ORDER BY id
```

**Statistics Query**:
```sql
SELECT 
  COUNT(*) as total,
  SUM(CASE WHEN status = 'answered' THEN 1 ELSE 0 END) as answered,
  SUM(CASE WHEN status IN ('busy', 'no-answer', 'congestion', 'unavailable', 'cancelled') THEN 1 ELSE 0 END) as failed
FROM simpledialer_call_logs
WHERE campaign_id = ?
```

**Call Status Polling** (daemon use):
```sql
SELECT status, duration, hangup_time FROM simpledialer_call_logs WHERE call_id = ?
```

**Scheduled Campaign Query**:
```sql
SELECT id, name, scheduled_time, status FROM simpledialer_campaigns 
WHERE status IN ('pending', 'inactive') 
AND scheduled_time IS NOT NULL 
AND scheduled_time <= NOW()
ORDER BY scheduled_time
```

### Database Table Structure

#### simpledialer_campaigns
| Field | Type | Purpose |
|-------|------|---------|
| id | INT AUTO_INCREMENT | Primary key |
| name | VARCHAR(255) | Campaign display name |
| description | TEXT | Optional campaign details |
| audio_file | VARCHAR(255) | Recording filename |
| trunk | VARCHAR(100) | Trunk identifier (TECH/CHANNEL) |
| caller_id | VARCHAR(100) | Outbound caller ID |
| max_concurrent | INT (default 5) | Concurrent call limit |
| delay_between_calls | INT (default 2) | Seconds between dials |
| status | VARCHAR(20) | inactive/scheduled/pending/active/completed/stopped/failed |
| scheduled_time | DATETIME NULL | Auto-start time |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last modification |

**Indexes**: status, created_at DESC

#### simpledialer_contacts
| Field | Type | Purpose |
|-------|------|---------|
| id | INT AUTO_INCREMENT | Primary key |
| campaign_id | INT | Foreign key to campaigns |
| phone_number | VARCHAR(20) | Dialed number |
| name | VARCHAR(255) | Contact name |
| status | VARCHAR(20) | pending/calling/called/failed |
| call_attempts | INT | Number of attempts |
| last_called | DATETIME NULL | Last dial timestamp |
| created_at | DATETIME | Import timestamp |

**Indexes**: campaign_id (FK), status, created_at

#### simpledialer_call_logs
| Field | Type | Purpose |
|-------|------|---------|
| id | INT AUTO_INCREMENT | Primary key |
| campaign_id | INT | Foreign key |
| contact_id | INT | Foreign key |
| phone_number | VARCHAR(20) | Dialed number (denormalized) |
| call_id | VARCHAR(100) | Unique call identifier |
| status | VARCHAR(20) | answered/no-answer/busy/congestion/unavailable/cancelled/timeout |
| duration | INT | Seconds (0 if unanswered) |
| answer_time | DATETIME NULL | When call answered |
| hangup_time | DATETIME NULL | When call ended |
| hangup_cause | VARCHAR(50) | Asterisk hangup code |
| voicemail_detected | BOOLEAN | AMD voicemail flag |
| created_at | DATETIME | Call attempt time |

**Indexes**: campaign_id, contact_id, status, created_at

### Database Transactions
- Not explicitly used (single statement operations)
- PDO autocommit mode enabled
- Foreign key constraints at table definition level

## 6. FreePBX/Asterisk Integration Points

### Asterisk Manager Interface (AMI)

**Connection Pattern**:
```php
require_once('/var/www/html/admin/libraries/php-asmanager.php');
$ami = new AGI_AsteriskManager();
if (!$ami->connect('localhost', $ami_user, $ami_pass)) {
  die("Failed to connect to AMI");
}
```

**Credentials Source**:
- `$amp_conf['AMPMGRUSER']` - Default 'admin'
- `$amp_conf['AMPMGRPASS']` - From freepbx.conf

**Originate Call Request**:
```php
$originate_params = array(
  'Channel' => 'Local/{number}@from-internal',
  'Context' => 'simpledialer-outbound',
  'Exten' => 's',
  'Priority' => '1',
  'Timeout' => '30000',  // 30 seconds
  'CallerID' => '"Name" <number>',
  'Variable' => 'KEY=value,KEY2=value2',  // Comma-separated
  'Async' => 'true'  // Non-blocking
);
$response = $ami->send_request('Originate', $originate_params);
if ($response['Response'] == 'Success') {
  // Call was queued successfully
}
```

**Channel Types**:
- Local channel: `Local/{number}@from-internal` (routes through FreePBX dialplan)
- Preserves caller ID through extensions
- Allows trunk selection via outbound routes
- Alternative (legacy): Direct trunk `SIP/trunk_name/{number}` (now avoided due to auth issues)

### Dialplan Context Integration

**Context Registration** (functions.inc.php):
```php
function simpledialer_get_config($engine) {
  global $ext;
  $ext->addInclude('from-internal', 'simpledialer-outbound');
}
```

**Dialplan Context** (extensions_simpledialer.conf):
- Receives calls from AMI Originate with Local channel
- Applies caller ID override if provided
- Runs AMD() for voicemail detection
- Plays audio file
- Applies call duration tracking
- Calls AGI script for database updates
- h-extension handles all hangup scenarios

### AGI Integration

**Script Location**: `/var/lib/asterisk/agi-bin/simpledialer_update.php`

**Call from Dialplan**:
```
exten => s,n,AGI(simpledialer_update.php,${CALL_ID},ANSWER,${CALL_DURATION},${ANSWER_TIME},${HANGUP_TIME},NORMAL,${VOICEMAIL})
```

**AGI Environment Variables Accessed**:
- agi_channel
- agi_extension
- agi_priority
- agi_type
- agi_callerid (only for reference, campaign CID overrides this)

**AGI Response**:
```
VERBOSE "SimpleDialer: Call {call_id} updated" 3
```

### Asterisk System Integration

**FreePBX Recordings Table Query**:
```sql
SELECT id, displayname, filename FROM recordings ORDER BY displayname
```

**Trunks Table Query**:
```sql
SELECT trunkid, name, tech, channelid FROM trunks WHERE disabled = 'off'
```

**Trunk Format**:
- PJSIP/endpoint (PJSIP technology)
- SIP/peer (SIP technology)
- IAX2/peer (IAX technology)
- Stored in campaigns as "TECH/CHANNEL"

### System Integration Commands

**Dialplan Reload**:
```bash
exec('asterisk -rx "dialplan reload"');
```

**Audio Format Conversion** (using sox):
```bash
// GSM format
exec("sox $source_wav -r 8000 -c 1 $target_gsm");

// μ-law format
exec("sox $source_wav -r 8000 -c 1 -e mu-law $target_ulaw");

// A-law format
exec("sox $source_wav -r 8000 -c 1 -e a-law $target_alaw");

// Signed linear 16-bit
exec("sox $source_wav -r 8000 -c 1 -e signed-integer -b 16 $target_sln");
```

**Daemon Spawning**:
```bash
cd $module_dir && /usr/bin/php $daemon_script $campaign_id >> $log_file 2>&1 &
```

## 7. Documentation Patterns

### Inline Code Comments

**File Header** (consistent):
```php
<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
```

**Function Documentation** (PHPDoc):
```php
/**
 * Get campaign statistics
 * 
 * @param int $campaign_id Campaign ID
 * @return array Statistics array with total, called, successful, failed
 */
public function getCampaignStats($campaign_id) {
```

**Debug Output** (to stdout/stderr):
```php
echo "DEBUG: Channel string: $channel\n";
echo "DEBUG: AMI Originate response: " . print_r($response, true) . "\n";
```

**Error Logging** (to Asterisk logs):
```php
error_log("Simple Dialer: Starting daemon with command: $command");
error_log("SimpleDialer: Updated {$call_id} - status={$mapped_status}, duration={$duration}s");
```

### README Structure

The README.md follows this pattern:
1. **Project description** - What does it do
2. **Features** - Bulleted feature list with emoji grouping
3. **Installation** - Step-by-step setup
4. **Usage** - How to use the module
5. **Configuration** - Advanced configuration options
6. **File Structure** - Tree diagram of files
7. **Database Schema** - Table descriptions
8. **Troubleshooting** - Common issues and solutions
9. **Development** - Contributing guidelines
10. **License** - GPL v3
11. **Support** - Where to get help
12. **Changelog** - Version history

### CHANGELOG.md Format

Follows Keep a Changelog and Semantic Versioning:
```markdown
## [1.1.1] - 2025-11-03

### Fixed
- **Issue Description**: Detailed explanation of what was fixed
  - Sub-bullet with technical details
  
### Added
- **New Feature**: Description
  
### Changed
- **Enhancement**: Description
```

### CONTRIBUTING.md Sections

- Code of Conduct
- How to Report Issues
- Feature Suggestions
- Development Setup
- Coding Standards (PHP, JavaScript, HTML/CSS, Database)
- Testing Procedures
- Pull Request Process
- Development Guidelines
- File Structure
- Debugging
- Common Development Tasks
- Release Process
- Getting Help

## 8. Testing Approach (Current)

### Manual Testing Areas

**As documented in CONTRIBUTING.md**:
1. **Functional Testing**:
   - Campaign creation scenarios
   - Scheduling verification
   - Contact upload validation
   - Audio playback confirmation
   - Report generation

2. **Edge Cases**:
   - Large contact lists (1000+ contacts)
   - Invalid CSV formats
   - Network connectivity issues
   - Database connectivity problems
   - Asterisk AMI failures

3. **Browser Testing**:
   - Chrome/Chromium
   - Firefox
   - Safari (if available)
   - Edge (if available)

4. **FreePBX Versions**:
   - Supported FreePBX versions
   - Database schema compatibility

### Testing Tools Available

**Manual CLI Testing**:
```bash
# Test scheduler
php bin/scheduler.php

# Test daemon
php bin/simpledialer_daemon.php [campaign_id]

# Check database
mysql -u root -p asterisk -e "SELECT * FROM simpledialer_campaigns;"
```

**Log Inspection**:
- Scheduler: `/var/log/asterisk/simpledialer_scheduler.log`
- Campaigns: `/var/log/asterisk/simpledialer_[id].log`
- FreePBX: `/var/log/asterisk/freepbx.log`

### Automated Testing

**Not Currently Implemented** - Noted in CONTRIBUTING.md as opportunity for:
- PHPUnit tests for campaign creation logic
- Contact validation tests
- Database operations tests
- Scheduling logic tests

## 9. Deployment/Installation Patterns

### Installation Process

**Step 1: Copy Module to FreePBX**
```bash
cd /var/www/html/admin/modules/
git clone https://github.com/PJL-Telecom/freepbx-simple-dialer.git simpledialer
```

**Step 2: Install AGI Script**
```bash
cp agi/simpledialer_update.php /var/lib/asterisk/agi-bin/
chmod +x /var/lib/asterisk/agi-bin/simpledialer_update.php
chown asterisk:asterisk /var/lib/asterisk/agi-bin/simpledialer_update.php
```

**Step 3: Install Dialplan Context**
```bash
cp extensions_simpledialer.conf /etc/asterisk/
asterisk -rx "dialplan reload"
```

**Step 4: Set Permissions**
```bash
chmod +x bin/*.php
chown -R asterisk:asterisk /var/www/html/admin/modules/simpledialer/
```

**Step 5: Install via Web UI**
- Admin → Module Admin
- Scan for new modules
- Click "Install" on Simple Dialer
- Apply configuration changes

**Step 6: Setup Scheduler (Cron)**
```bash
crontab -e
# Add line:
* * * * * php /var/www/html/admin/modules/simpledialer/bin/scheduler.php >> /var/log/asterisk/simpledialer_scheduler.log 2>&1
```

### Installation Hooks

**install.php** executes:
1. Database table creation (with IF NOT EXISTS)
2. Sounds directory creation
3. Sample audio file copying
4. Daemon executable permission setting
5. Dialplan context installation
6. Include line addition to extensions_custom.conf
7. Dialplan reload
8. Verification messages via out() function

### Upgrade Process

**For existing installations**:
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
```

**Notes**:
- No database migrations required (schema remains compatible)
- Existing call logs not retroactively updated
- New campaigns benefit from enhanced tracking

### File Permissions

**Daemon Scripts**: 0755 (executable)
**Audio Files**: 0644 (readable by asterisk)
**Sound Directories**: Owned by asterisk:asterisk
**Database**: Accessed via FreePBX connection pool

### Environment Variables

**Required for daemon**:
- FREEPBX_CONF=/etc/freepbx.conf (set by daemon startup code)

**Optional**:
- Custom log locations via shell redirection
- Cron environment limitations (no shell, limited PATH)

## 10. Code Organization Conventions

### File Organization Philosophy

**Single Responsibility**:
- Main class: Simpledialer.class.php (CRUD + business logic)
- Web interface: page.simpledialer.php (UI + AJAX handlers)
- Daemon: bin/simpledialer_daemon.php (Campaign execution)
- Scheduler: bin/scheduler.php (Time-based triggering)
- AGI: agi/simpledialer_update.php (Call status updates)

**Separation of Concerns**:
- Database operations confined to Simpledialer class methods
- AMI operations isolated in daemon
- UI routing isolated in page.php
- Asterisk dialplan separate from PHP code

### Naming Conventions

**Variables**:
- `$campaign`, `$contact`, `$call_log` (single entity)
- `$campaigns`, `$contacts` (collections)
- `$campaign_id`, `$contact_id` (identifiers)
- `$sth` (statement handle - common for PDO)
- `$sql` (SQL string)

**Methods**:
- `get[Entity]()` - Query single or list
- `add[Entity]($data)` - Create with array input
- `update[Entity]($data)` - Modify existing
- `delete[Entity]($id)` - Remove by ID
- `[action][Entity]()` - Perform action

**Class Methods**:
- Public: `getCampaign()`, `startCampaign()`, `generateCampaignReport()`
- Private: `connectAMI()`, `makeCall()`, `updateContactStatus()`

**Database Tables**:
- All prefixed with `simpledialer_`
- Plural entity names: `campaigns`, `contacts`, `call_logs`
- Consistent field naming across tables

### Code Style Standards

**As documented in CONTRIBUTING.md**:
- Follow PSR-4 autoloading standards
- Use meaningful variable and function names
- Add PHPDoc comments for all public methods
- Handle errors gracefully with try/catch blocks
- Sanitize all user inputs
- Use prepared statements for database queries
- Bootstrap 3 classes for CSS
- Semantic HTML practices
- Accessibility with ARIA labels
- ES5 JavaScript for compatibility
- jQuery conventions for AJAX

### Security Patterns

**Input Validation**:
```php
// Phone number normalization
$phone = preg_replace('/[^0-9]/', '', $phone);

// File path safety (basename() to remove directory traversal)
$filename = basename($_GET['file']);

// Audio filename sanitization
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['recording_name']);
```

**SQL Safety**:
```php
// Always use prepared statements
$sth = $this->db->prepare("SELECT * FROM table WHERE id = ?");
$sth->execute(array($id));
```

**Command Execution Safety**:
```php
// Use escapeshellarg() for sox commands
exec("sox " . escapeshellarg($source) . " " . escapeshellarg($target) . " 2>/dev/null");
```

**HTML Output Escaping**:
```php
// Always escape user data
echo htmlspecialchars($contact['phone_number']);
```

### Error Handling Patterns

**Exceptions**:
```php
try {
  $this->startCampaign($_POST['campaign_id']);
  // ... success handling
} catch (Exception $e) {
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
  } else {
    throw $e;
  }
}
```

**Validation**:
```php
if (!$campaign) {
  throw new Exception("Campaign not found");
}
if ($contact_count == 0) {
  echo "  ERROR: Campaign has no contacts, skipping\n";
  continue;
}
```

**Logging**:
```php
error_log("Simple Dialer: " . $message);
echo "DEBUG: " . $debug_message . "\n";
```

### Architectural Patterns

**MVC-lite Pattern**:
- **Model**: Simpledialer.class.php (database operations)
- **View**: page.simpledialer.php (HTML rendering)
- **Controller**: page.simpledialer.php (request routing)
- **Business Logic**: bin/simpledialer_daemon.php (campaign execution)

**Daemon Pattern**:
- Long-running process for campaign execution
- Managed lifecycle (started, monitored, stopped)
- Signal-based termination via stop file
- Activity logging to file

**Scheduler Pattern**:
- Cron-triggered execution (every minute)
- Simple query-based scheduling
- Loose coupling with campaign execution

**Event-Driven**:
- AMI Originate triggers call execution
- Dialplan context processes call
- AGI script reports completion
- Daemon polls for status updates

## Quick Reference

### Module Entry Points
- **Web UI**: `/admin/modules/simpledialer` (registered via module.xml)
- **Daemon**: `php /var/www/html/admin/modules/simpledialer/bin/simpledialer_daemon.php [campaign_id]`
- **Scheduler**: `php /var/www/html/admin/modules/simpledialer/bin/scheduler.php`
- **AGI**: `/var/lib/asterisk/agi-bin/simpledialer_update.php`

### Campaign Lifecycle States
1. **inactive** - Created, not scheduled
2. **scheduled** - Future start time set (user sees blue "Scheduled" badge)
3. **pending** - Past scheduled time, waiting for scheduler
4. **active** - Currently executing (daemon running)
5. **completed** - Successfully finished
6. **stopped** - Manually terminated by user
7. **failed** - Error occurred during execution

### Key Database Queries
- Campaign: `SELECT * FROM simpledialer_campaigns WHERE id = ?`
- Pending contacts: `SELECT * FROM simpledialer_contacts WHERE campaign_id = ? AND status = 'pending'`
- Stats: Grouped SUM() queries on call_logs by status
- Scheduler: Check scheduled_time <= NOW() with status filters

### Critical Files for Modification
- Campaign logic: `Simpledialer.class.php` (main class methods)
- Web UI: `page.simpledialer.php` (HTML + AJAX handlers)
- Call processing: `bin/simpledialer_daemon.php` (AMI integration)
- Call status: `agi/simpledialer_update.php` (database updates)
- Dialplan: `extensions_simpledialer.conf` (call handling)

### Performance Characteristics
- Scheduler: ~100ms per run (quick database queries)
- Daemon: Depends on concurrent calls (loop iteration ~1-2 seconds)
- Database polling: Every 2 seconds in daemon
- Web UI refresh: 30 seconds auto-refresh interval
- Report generation: Seconds for 1000+ contact campaigns

### Dependencies
- FreePBX 15.0+
- PHP 7.4+
- MySQL/MariaDB
- Asterisk with AMI enabled
- sox tool (for audio conversion)
- Cron (for scheduling)

## 11. Development Workflows for AI Assistants

### Git Workflow

**Branch Strategy**:
- `main` - Production-ready code (v1.1.1 current)
- Feature branches: `feature/description` or `claude/session-id`
- Bugfix branches: `fix/description`

**Commit Message Format**:
```
<action> <component>: <description>

Examples:
- "Fix live progress updates and prevent h-extension from overwriting status"
- "Update README.md with comprehensive installation and upgrade instructions"
- "Add AGI database update script for reliable call tracking"
```

**Recent Important Commits** (v1.1.1 development):
1. `7fbf200` - README installation/upgrade instructions update
2. `8cd5db7` - CHANGELOG v1.1.1 complete release notes
3. `6eeed48` - Live progress updates fix
4. `1ecb834` - AGI script implementation (critical fix)
5. `e9b332d` - Campaign completion and duration fixes

### Making Changes Safely

**Before Modifying Code**:
1. **Read the relevant files first** - Always use Read tool before Edit
2. **Check FIXES_DOCUMENTATION.md** - Understand previous bug fixes
3. **Review CHANGELOG.md** - Understand recent changes and context
4. **Test locally** - Changes affect production phone systems

**Critical Files (Modify with Caution)**:
- `extensions_simpledialer.conf` - Changes require dialplan reload
- `agi/simpledialer_update.php` - Must be executable and in /var/lib/asterisk/agi-bin/
- `bin/simpledialer_daemon.php` - Running campaigns use this code
- Database schema changes - Require migration planning

**Safe to Modify**:
- `page.simpledialer.php` - UI changes (test AJAX endpoints)
- `Simpledialer.class.php` - Methods (test database queries)
- `assets/css/simpledialer.css` - Styling only

### Common Development Tasks

**Adding a New Campaign Field**:
1. Modify `module.xml` database schema (add field to simpledialer_campaigns)
2. Update `Simpledialer.class.php` addCampaign/updateCampaign methods
3. Update `page.simpledialer.php` form HTML and AJAX handlers
4. Update `bin/simpledialer_daemon.php` if field affects execution
5. Test: Create campaign → Start campaign → Verify field used correctly

**Adding a New AJAX Endpoint**:
1. Add case to `page.simpledialer.php` switch statement (~line 30-100)
2. Implement logic using $this->FreePBX->Simpledialer() methods
3. Return JSON: `echo json_encode(['success' => true, 'data' => $result])`
4. Add JavaScript caller in page HTML section
5. Test: Network tab → Verify response → Check error handling

**Modifying Call Processing**:
1. Update `extensions_simpledialer.conf` dialplan if changing call flow
2. Update `agi/simpledialer_update.php` if changing status tracking
3. Update `bin/simpledialer_daemon.php` if changing origination logic
4. Test: Create test campaign → Monitor logs → Verify database updates
5. After changes: `cp extensions_simpledialer.conf /etc/asterisk/ && asterisk -rx "dialplan reload"`

### Common Pitfalls and Solutions

**Issue 1: Database Updates Not Reflecting in UI**
- **Cause**: 30-second auto-refresh delay or modal blocking refresh
- **Solution**: Force refresh or check database directly with SQL query
- **Debug**: Check browser console for AJAX errors, verify endpoint returns current data

**Issue 2: Calls Not Connecting (403 Errors)**
- **Cause**: Direct trunk dialing without authentication (pre-v1.1.1 issue)
- **Solution**: Use `Local/{number}@from-internal` channel (routes through FreePBX)
- **See**: FIXES_DOCUMENTATION.md section 1, CHANGELOG v1.1.1 fixes

**Issue 3: Campaign Stuck "In Progress"**
- **Cause**: Daemon waiting for channel status that never updates
- **Solution**: Use AGI script for database updates (v1.1.1+ approach)
- **Debug**: Check `/var/log/asterisk/simpledialer_[id].log` for stuck calls

**Issue 4: Progress Bar Not Updating**
- **Cause**: System() mysql commands fail due to authentication
- **Solution**: AGI script uses FreePBX database connection (implemented in v1.1.1)
- **Verify**: Check simpledialer_call_logs table has real-time updates during calls

**Issue 5: Caller ID Not Honored**
- **Cause**: Local channel doesn't inherit CALLERID variables
- **Solution**: Set __ prefix variables (CAMPAIGN_CID_NUM, CAMPAIGN_CID_NAME)
- **Location**: extensions_simpledialer.conf lines 5-18

**Issue 6: All Calls Show "Answered"**
- **Cause**: DIALSTATUS not captured or h-extension overwrites status
- **Solution**: Check SIMPLEDIALER_UPDATED flag, map DIALSTATUS correctly
- **Fixed in**: v1.1.1 with proper AGI implementation

### Testing Checklist

**Before Committing Changes**:
- [ ] PHP syntax check: `php -l file.php`
- [ ] Test campaign creation via UI
- [ ] Test campaign execution (small contact list 2-3 numbers)
- [ ] Check logs: scheduler, daemon, FreePBX
- [ ] Verify database updates (contacts, call_logs)
- [ ] Test scheduled campaigns (set time 2 minutes in future)
- [ ] Check reports generation and display
- [ ] Browser console - no JavaScript errors
- [ ] Test with PJSIP trunk (most common)

**Manual Testing Commands**:
```bash
# Test scheduler manually
php /var/www/html/admin/modules/simpledialer/bin/scheduler.php

# Test daemon directly (replace 1 with campaign ID)
php /var/www/html/admin/modules/simpledialer/bin/simpledialer_daemon.php 1

# Check database state
mysql -u root -p asterisk -e "SELECT id, name, status FROM simpledialer_campaigns;"
mysql -u root -p asterisk -e "SELECT * FROM simpledialer_call_logs ORDER BY id DESC LIMIT 10;"

# Check logs
tail -f /var/log/asterisk/simpledialer_scheduler.log
tail -f /var/log/asterisk/simpledialer_1.log
tail -f /var/log/asterisk/freepbx.log

# Verify dialplan loaded
asterisk -rx "dialplan show simpledialer-outbound"

# Test AMI connection
asterisk -rx "manager show connected"
```

### Debugging Strategies

**Campaign Not Starting**:
1. Check campaign status in database
2. Verify contacts exist for campaign
3. Check AMI credentials in freepbx.conf
4. Review daemon log file
5. Verify trunk is available: `asterisk -rx "pjsip show endpoints"`

**Calls Failing Immediately**:
1. Check dialplan: `asterisk -rx "dialplan show simpledialer-outbound"`
2. Verify audio files exist: `ls -la /var/lib/asterisk/sounds/en/[filename].*`
3. Check Asterisk full log: `tail -f /var/log/asterisk/full`
4. Test trunk manually from extension
5. Verify outbound routes configured in FreePBX

**Database Issues**:
1. Check table exists: `SHOW TABLES LIKE 'simpledialer_%';`
2. Verify schema: `DESCRIBE simpledialer_campaigns;`
3. Check for locks: `SHOW PROCESSLIST;`
4. Review query errors in FreePBX log
5. Ensure InnoDB engine: `SHOW TABLE STATUS WHERE Name LIKE 'simpledialer_%';`

### Integration Points to Remember

**FreePBX Framework**:
- Module MUST implement BMO interface
- Use `$this->FreePBX` for framework access
- Database via `$this->db` (PDO connection pool)
- Bootstrap via `/etc/freepbx.conf` for CLI scripts

**Asterisk AMI**:
- Connection requires credentials from freepbx.conf
- Originate is async - doesn't wait for answer
- UserEvents can communicate daemon ↔ dialplan
- Channel format critical: `Local/number@context` vs `TECH/trunk/number`

**Dialplan Context**:
- Must be included in from-internal for routing
- Variables with __ prefix inherit to child channels
- h-extension catches all hangup scenarios
- AGI scripts must return proper response format

**Database Consistency**:
- Campaign status drives UI display
- Contact status tracks individual progress
- Call logs provide detailed metrics
- Foreign keys maintain referential integrity

### Key Architectural Decisions (v1.1.1)

**Why Local Channels Instead of Direct Trunk Dialing?**
- Ensures FreePBX outbound route processing
- Handles trunk authentication automatically
- Applies caller ID manipulation rules
- Supports all trunk types uniformly
- Prevents 403 authentication errors

**Why AGI Script Instead of System() MySQL?**
- FreePBX database connection (authenticated)
- Reliable execution environment
- Proper error handling and logging
- Avoids mysql command-line authentication issues
- Real-time status updates during calls

**Why Database Polling Instead of AMI Events?**
- Simpler implementation (no event parsing)
- Works reliably across Asterisk versions
- Easier to debug (check database directly)
- AGI updates database, daemon reads it
- 2-second poll interval acceptable for UX

**Why File-Based Stop Signal?**
- Simple IPC mechanism
- No need for signal handling in PHP
- Works across process boundaries
- Easy to implement and debug
- Check is fast (file_exists)

---

## Additional Resources

### Key Log Locations
- **Scheduler**: `/var/log/asterisk/simpledialer_scheduler.log`
- **Campaign**: `/var/log/asterisk/simpledialer_[campaign_id].log`
- **Reports**: `/var/log/asterisk/simpledialer_reports/`
- **Asterisk Full**: `/var/log/asterisk/full` (all channel events)
- **FreePBX**: `/var/log/asterisk/freepbx.log` (framework errors)

### Useful Asterisk Commands
```bash
# Reload dialplan after changes
asterisk -rx "dialplan reload"

# Show specific context
asterisk -rx "dialplan show simpledialer-outbound"

# Check active channels during campaign
asterisk -rx "core show channels"

# Monitor AMI events (for debugging)
asterisk -rx "manager show events"

# Check PJSIP endpoints
asterisk -rx "pjsip show endpoints"

# View active calls
asterisk -rx "core show calls"
```

### Database Queries for Monitoring
```sql
-- Check all campaigns with status
SELECT id, name, status, scheduled_time, created_at FROM simpledialer_campaigns ORDER BY id DESC;

-- Campaign progress details
SELECT c.name,
       COUNT(co.id) as total_contacts,
       SUM(CASE WHEN co.status = 'called' THEN 1 ELSE 0 END) as completed,
       SUM(CASE WHEN co.status = 'calling' THEN 1 ELSE 0 END) as in_progress,
       SUM(CASE WHEN co.status = 'pending' THEN 1 ELSE 0 END) as pending
FROM simpledialer_campaigns c
LEFT JOIN simpledialer_contacts co ON c.id = co.campaign_id
WHERE c.id = ?
GROUP BY c.id;

-- Call outcome statistics
SELECT status, COUNT(*) as count, AVG(duration) as avg_duration
FROM simpledialer_call_logs
WHERE campaign_id = ?
GROUP BY status;

-- Recent call details
SELECT phone_number, status, duration, answer_time, hangup_time
FROM simpledialer_call_logs
WHERE campaign_id = ?
ORDER BY created_at DESC
LIMIT 20;
```

### File Permissions Reference
```bash
# Module directory ownership
chown -R asterisk:asterisk /var/www/html/admin/modules/simpledialer/

# Executable scripts
chmod +x /var/www/html/admin/modules/simpledialer/bin/*.php
chmod +x /var/lib/asterisk/agi-bin/simpledialer_update.php

# Audio files
chmod 644 /var/lib/asterisk/sounds/en/*
chown asterisk:asterisk /var/lib/asterisk/sounds/en/*

# Log directory
mkdir -p /var/log/asterisk/simpledialer_reports/
chown asterisk:asterisk /var/log/asterisk/simpledialer_reports/
```

---

**Document Version**: 1.1
**Last Updated**: 2025-11-13
**Applicable to**: FreePBX Simple Dialer v1.1.1
**Repository**: https://github.com/PJL-Telecom/freepbx-simple-dialer

This document serves as a technical reference for developers and AI assistants working with the FreePBX Simple Dialer codebase. Refer to README.md for user documentation and CONTRIBUTING.md for development guidelines.

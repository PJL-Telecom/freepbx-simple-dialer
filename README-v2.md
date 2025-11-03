# SimpleDialer v2.0 - FreePBX Module

## AMD (Answering Machine Detection) Implementation

SimpleDialer v2.0 introduces full **AMD (Answering Machine Detection)** functionality, providing intelligent call handling that automatically detects human vs voicemail answers.

## ✅ **What's New in v2.0**

### **AMD Features**
- **Automatic Detection**: Distinguishes between human answers and voicemail systems
- **Smart Timing**: Waits for voicemail beep using `WaitForSilence` before playing message
- **Optimized Delivery**: 
  - Human answers: Plays message immediately
  - Voicemail: Waits for greeting + beep, then plays message
- **Zero Configuration**: AMD works out of the box after installation

### **Technical Improvements**
- **Separate Dialplan Context**: Uses `simpledialer-outbound` context for proper AMD functionality
- **No Tamper Warnings**: Uses separate dialplan file instead of modifying FreePBX core files  
- **Enhanced Installer**: Automatically configures AMD context and includes
- **Proper Uninstaller**: Cleanly removes all AMD components
- **Database Enhancement**: Added `voicemail_detected` field for call logging

## 🚀 **Installation**

### **Requirements**
- FreePBX 15+ with AMD module enabled
- PJSIP or SIP trunks configured
- Asterisk with AMI access

### **Install Steps**
1. Upload module files to `/var/www/html/admin/modules/simpledialer/`
2. Install via FreePBX Module Admin or run: `fwconsole ma install simpledialer`
3. The installer automatically:
   - Creates database tables
   - Installs AMD dialplan context
   - Configures FreePBX includes
   - Makes daemon executable

### **AMD Configuration**
The following AMD parameters are automatically configured:
```
Initial silence: 2500ms
Greeting length: 1500ms  
After greeting silence: 800ms
Total analysis time: 5000ms
WaitForSilence: 1000ms (2 attempts)
```

## 📋 **Files Included**

### **Core Files**
- `Simpledialer.class.php` - Main module class
- `page.simpledialer.php` - Web interface
- `functions.inc.php` - Helper functions
- `install.php` - Installation script with AMD setup
- `uninstall.php` - Clean removal script
- `module.xml` - FreePBX module definition

### **Daemon & Scripts**
- `bin/simpledialer_daemon.php` - Campaign execution daemon
- `bin/simpledialer_scheduler.php` - Automatic campaign scheduler

### **AMD Dialplan**
- `extensions_simpledialer.conf` - AMD context with voicemail detection logic

### **Documentation**
- `CHANGELOG.md` - Complete version history
- `README.md` - Installation and usage guide
- `CONTRIBUTING.md` - Development guidelines

## 🎯 **How AMD Works**

### **Call Flow**
1. **Originate Call**: Daemon initiates call to contact
2. **AMD Analysis**: Asterisk analyzes answer for voice patterns
3. **Detection Result**: 
   - **HUMAN**: Branch to immediate playback
   - **MACHINE**: Branch to voicemail handling
4. **Voicemail Handling**:
   - Wait for silence (beep detection)
   - Wait additional 1 second
   - Play message
   - Hang up after completion

### **Dialplan Logic**
```asterisk
exten => s,n,AMD()
exten => s,n,GotoIf($["${AMDSTATUS}" = "MACHINE"]?vm:human)
exten => s,n(human),Playback(${AUDIO_PATH})
exten => s,n(vm),WaitForSilence(1000,2)
exten => s,n,Wait(1)
exten => s,n,Playback(${AUDIO_PATH})
```

## 🔧 **Configuration**

### **Campaign Creation**
- Select trunk for outbound calls
- Upload CSV contacts
- Choose system recording for audio
- Set concurrent call limits
- Configure caller ID

### **AMD Tuning** (Optional)
AMD parameters can be adjusted in `/etc/asterisk/amd.conf`:
- `initial_silence` - Max initial silence before assuming human
- `greeting` - Max greeting length for machines  
- `after_greeting_silence` - Min silence after greeting
- `total_analysis_time` - Max time to analyze

## 📊 **Logging & Reports**

### **Call Logs**
- Campaign progress tracking
- Individual call status
- AMD detection results
- Duration and hangup cause

### **Campaign Reports**
- Automatically generated after completion
- Stored in `/var/log/asterisk/simpledialer_reports/`
- Emailed to system administrator
- Auto-cleanup after 7 days

## 🛠 **Troubleshooting**

### **AMD Not Working**
1. Verify context loaded: `asterisk -rx "dialplan show simpledialer-outbound"`
2. Check AMD module: `asterisk -rx "module show app_amd"`
3. Review call logs: `/var/log/asterisk/simpledialer_X.log`

### **Common Issues**
- **"Extension does not exist"**: AMD context not loaded properly
- **Calls cut off**: Increase `total_analysis_time` in amd.conf
- **False machine detection**: Adjust `initial_silence` parameter

## 📞 **Testing AMD**

### **Test Human Answer**
1. Create campaign with your number
2. Answer immediately when called  
3. Message should play right away

### **Test Voicemail**
1. Create campaign with your number
2. Let it go to voicemail
3. Message should play after beep

## 🔄 **Upgrading from v1.0**

### **Automatic Upgrade**
- AMD context automatically installed
- Existing campaigns work immediately  
- Database schema updated automatically
- No configuration changes required

### **Manual Steps** (if needed)
If AMD doesn't work after upgrade:
```bash
# Reload dialplan
asterisk -rx "dialplan reload"

# Verify context loaded
asterisk -rx "dialplan show simpledialer-outbound"
```

## 🎉 **Version 2.0 Ready**

SimpleDialer v2.0 with AMD is production-ready and includes:
- ✅ Full AMD implementation
- ✅ Zero-configuration installation  
- ✅ No FreePBX tamper warnings
- ✅ Enhanced logging and reporting
- ✅ Comprehensive documentation
- ✅ Clean uninstallation

Perfect for professional autodialing campaigns with intelligent voicemail detection!
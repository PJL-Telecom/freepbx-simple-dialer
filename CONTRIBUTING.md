# Contributing to FreePBX Simple Dialer

Thank you for your interest in contributing to the FreePBX Simple Dialer module! This document provides guidelines and information for contributors.

## Code of Conduct

This project adheres to a code of conduct that we expect all contributors to follow:

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow
- Follow community guidelines

## How to Contribute

### Reporting Issues

Before creating an issue:
1. Check existing issues to avoid duplicates
2. Use the search function to find related problems
3. Test with the latest version

When creating an issue, include:
- **Environment**: FreePBX version, PHP version, OS
- **Steps to reproduce**: Clear, step-by-step instructions
- **Expected behavior**: What should happen
- **Actual behavior**: What actually happens
- **Logs**: Relevant log entries (sanitize sensitive data)
- **Screenshots**: If applicable

### Suggesting Features

Feature requests are welcome! Please:
1. Check if the feature already exists or is planned
2. Explain the use case and benefit
3. Consider implementation complexity
4. Be open to discussion and alternatives

### Contributing Code

#### Development Setup

1. **Fork the repository**
   ```bash
   git clone https://github.com/yourusername/freepbx-simple-dialer.git
   cd freepbx-simple-dialer
   ```

2. **Set up development environment**
   ```bash
   # Install in FreePBX modules directory
   ln -s /path/to/your/clone /var/www/html/admin/modules/simpledialer
   
   # Set permissions
   chown -R asterisk:asterisk /var/www/html/admin/modules/simpledialer/
   chmod +x bin/*.php
   ```

3. **Create a branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/issue-description
   ```

#### Coding Standards

**PHP Code:**
- Follow PSR-4 autoloading standards
- Use meaningful variable and function names
- Add PHPDoc comments for all public methods
- Handle errors gracefully with try/catch blocks
- Sanitize all user inputs
- Use prepared statements for database queries

**JavaScript Code:**
- Use ES5 for FreePBX compatibility
- Add comments for complex logic
- Handle AJAX errors gracefully
- Follow jQuery conventions

**HTML/CSS:**
- Use Bootstrap 3 classes (FreePBX standard)
- Ensure accessibility with proper ARIA labels
- Test with different screen sizes
- Follow semantic HTML practices

**Database:**
- Use PDO for all database interactions
- Always use prepared statements
- Handle database errors appropriately
- Follow FreePBX database naming conventions

#### Testing

Before submitting:

1. **Functional Testing**
   - Test all campaign creation scenarios
   - Verify scheduling works correctly
   - Test contact upload and validation
   - Confirm audio playback works
   - Test report generation

2. **Edge Cases**
   - Large contact lists (1000+ contacts)
   - Invalid CSV formats
   - Network connectivity issues
   - Database connectivity problems
   - Asterisk AMI failures

3. **Browser Testing**
   - Chrome/Chromium
   - Firefox
   - Safari (if available)
   - Edge (if available)

4. **FreePBX Versions**
   - Test on supported FreePBX versions
   - Verify database schema compatibility

#### Pull Request Process

1. **Before submitting:**
   - Ensure all tests pass
   - Update documentation if needed
   - Add changelog entry for significant changes
   - Rebase your branch if needed

2. **Pull request description:**
   - Clear title describing the change
   - Detailed description of what was changed
   - Reference any related issues
   - Include testing steps
   - Note any breaking changes

3. **Review process:**
   - Maintainers will review your code
   - Address feedback promptly
   - Be open to suggestions and changes
   - Update your PR based on feedback

## Development Guidelines

### FreePBX Module Standards

This module follows FreePBX development standards:

- **BMO Interface**: Implements all required BMO methods
- **Hook System**: Uses FreePBX hooks appropriately
- **Database**: Uses FreePBX database class and conventions
- **Internationalization**: Supports translation with _() function
- **Security**: Follows FreePBX security practices

### File Structure

```
simpledialer/
├── README.md                    # Main documentation
├── CHANGELOG.md                 # Version history
├── CONTRIBUTING.md              # This file
├── LICENSE                      # GPL v3 license
├── .gitignore                   # Git ignore rules
├── module.xml                   # FreePBX module definition
├── Simpledialer.class.php       # Main module class
├── page.simpledialer.php        # Web interface
├── install.php                  # Installation hooks
└── bin/                         # Command-line scripts
    ├── simpledialer_daemon.php  # Campaign execution
    └── scheduler.php             # Automatic scheduling
```

### Debugging

#### Log Files
- **Scheduler**: `/var/log/asterisk/simpledialer_scheduler.log`
- **Campaigns**: `/var/log/asterisk/simpledialer_[id].log`
- **FreePBX**: `/var/log/asterisk/freepbx.log`

#### Debug Mode
Enable debug logging by modifying the daemon:
```php
// Add to daemon constructor
error_log("Debug: Campaign $campaign_id starting");
```

#### Console Debugging
Use browser console for JavaScript debugging:
```javascript
console.log('Auto-refresh check:', data);
```

## Common Development Tasks

### Adding New Features

1. **Database Changes**
   - Modify `module.xml` database schema
   - Add upgrade scripts if needed
   - Test database migration

2. **Interface Changes**
   - Update `page.simpledialer.php`
   - Add JavaScript if needed
   - Test responsive design

3. **Backend Logic**
   - Modify `Simpledialer.class.php`
   - Add error handling
   - Update documentation

### Testing Procedures

#### Manual Testing
```bash
# Test scheduler
php bin/scheduler.php

# Test daemon
php bin/simpledialer_daemon.php [campaign_id]

# Check database
mysql -u root -p asterisk -e "SELECT * FROM simpledialer_campaigns;"
```

#### Automated Testing
Consider adding PHPUnit tests for:
- Campaign creation logic
- Contact validation
- Database operations
- Scheduling logic

## Release Process

1. **Version Update**
   - Update version in `module.xml`
   - Add changelog entry
   - Tag release in git

2. **Testing**
   - Full regression testing
   - Test on multiple environments
   - Validate upgrade path

3. **Documentation**
   - Update README if needed
   - Review installation instructions
   - Check all links work

## Getting Help

- **GitHub Issues**: For bugs and feature requests
- **FreePBX Forums**: For general FreePBX questions
- **Documentation**: Check README and code comments
- **Code Review**: Maintainers provide feedback on PRs

## Recognition

Contributors will be recognized in:
- CHANGELOG.md for significant contributions
- README.md credits section
- Git commit history

Thank you for contributing to the FreePBX Simple Dialer module! Your contributions help make this tool better for the entire FreePBX community.
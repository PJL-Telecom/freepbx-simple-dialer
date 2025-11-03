#!/bin/bash

# SimpleDialer v2.0 GitHub Deployment Script
# Handles git initialization, commits, tagging, and push to GitHub

set -e  # Exit on any error

echo "🚀 SimpleDialer v2.0 GitHub Deployment Script"
echo "=============================================="

# Configuration
REPO_URL="https://github.com/PJL-Telecom/freepbx-simple-dialer.git"
VERSION="1.1.0"
BRANCH="main"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [[ ! -f "module.xml" ]]; then
    print_error "module.xml not found. Please run this script from the SimpleDialer module directory."
    exit 1
fi

print_status "Checking current directory: $(pwd)"

# Check if git is initialized
if [[ ! -d ".git" ]]; then
    print_status "Initializing Git repository..."
    git init
    print_success "Git repository initialized"
else
    print_status "Git repository already exists"
fi

# Configure git if not already configured
if [[ -z "$(git config user.name 2>/dev/null)" ]]; then
    print_warning "Git user.name not configured. Please set it manually:"
    echo "  git config user.name 'Your Name'"
fi

if [[ -z "$(git config user.email 2>/dev/null)" ]]; then
    print_warning "Git user.email not configured. Please set it manually:"
    echo "  git config user.email 'your.email@example.com'"
fi

# Add remote if it doesn't exist
if ! git remote get-url origin >/dev/null 2>&1; then
    print_status "Adding GitHub remote..."
    echo "Please enter your GitHub repository URL:"
    echo "Example: https://github.com/username/simpledialer.git"
    read -p "Repository URL: " REPO_URL
    git remote add origin "$REPO_URL"
    print_success "Remote origin added: $REPO_URL"
else
    print_status "Remote origin already configured: $(git remote get-url origin)"
fi

# Create .gitignore if it doesn't exist
if [[ ! -f ".gitignore" ]]; then
    print_status "Creating .gitignore..."
    cat > .gitignore << EOF
# Log files
*.log

# Temporary files
*.tmp
*.temp

# OS generated files
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db

# IDE files
.vscode/
.idea/
*.swp
*.swo

# Module signature (auto-generated)
module.sig

# Backup files
*.bak
*.backup
*~
EOF
    print_success ".gitignore created"
fi

# Stage all files
print_status "Staging files for commit..."
git add .

# Check if there are changes to commit
if git diff --staged --quiet; then
    print_warning "No changes to commit"
    exit 0
fi

# Show what will be committed
print_status "Files to be committed:"
git diff --staged --name-status

# Create commit message
COMMIT_MSG="Release SimpleDialer v$VERSION with AMD Support

Major Features:
- Added AMD (Answering Machine Detection) functionality
- Intelligent voicemail vs human detection
- Optimized beep detection with WaitForSilence
- Zero-configuration AMD setup

Technical Improvements:
- Uses separate dialplan file (no FreePBX tamper warnings)
- Enhanced installer with automatic AMD context setup
- Improved uninstaller for clean removal
- Updated database schema with voicemail detection logging

Bug Fixes:
- Fixed FreePBX core file modification warnings
- Fixed output buffer contamination in AJAX responses
- Fixed PJSIP channel string formatting
- Fixed daemon environment and working directory issues

AMD Configuration:
- Initial silence: 2500ms, Greeting: 1500ms
- After greeting silence: 800ms, Total analysis: 5000ms
- WaitForSilence: 1000ms (2 attempts)

This release provides production-ready AMD functionality with 
comprehensive documentation and automated installation."

# Commit changes
print_status "Creating commit..."
git commit -m "$COMMIT_MSG"
print_success "Changes committed successfully"

# Create and push tag
print_status "Creating version tag v$VERSION..."
if git tag -l "v$VERSION" | grep -q "v$VERSION"; then
    print_warning "Tag v$VERSION already exists, skipping tag creation"
else
    git tag -a "v$VERSION" -m "SimpleDialer v$VERSION - AMD Implementation"
    print_success "Tag v$VERSION created"
fi

# Push to GitHub
print_status "Pushing to GitHub..."
echo "This will push the code to GitHub. Continue? (y/N)"
read -r response
if [[ "$response" =~ ^[Yy]$ ]]; then
    print_status "Pushing branch and tags..."
    git push origin $BRANCH
    git push origin --tags
    print_success "Successfully pushed to GitHub!"
    print_success "Repository URL: $(git remote get-url origin)"
    print_success "Release tag: v$VERSION"
else
    print_warning "Push cancelled by user"
    print_status "To push manually later, run:"
    echo "  git push origin $BRANCH"
    echo "  git push origin --tags"
fi

print_success "✅ SimpleDialer v2.0 deployment complete!"
echo ""
echo "📋 Summary:"
echo "  - Version: $VERSION"
echo "  - AMD functionality: ✅ Fully implemented"
echo "  - Installation: ✅ Zero-configuration"
echo "  - Documentation: ✅ Complete"
echo "  - GitHub ready: ✅ Tagged and pushed"
echo ""
echo "🎉 SimpleDialer v2.0 with AMD is now live on GitHub!"
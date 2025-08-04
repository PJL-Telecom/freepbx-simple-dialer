#!/bin/bash

# FreePBX Simple Dialer - GitHub Upload Script
# This script will initialize git and prepare for GitHub upload

echo "=== FreePBX Simple Dialer - GitHub Upload Script ==="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get current directory
MODULE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo -e "${BLUE}Module directory: ${MODULE_DIR}${NC}"

# Check if we're in the right directory
if [[ ! -f "${MODULE_DIR}/module.xml" ]]; then
    echo -e "${RED}Error: module.xml not found. Are you in the correct directory?${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 1: Git Configuration${NC}"

# Check if git is configured
if ! git config --global user.name >/dev/null 2>&1; then
    echo "Git user.name not configured."
    read -p "Enter your name: " USER_NAME
    git config --global user.name "$USER_NAME"
fi

if ! git config --global user.email >/dev/null 2>&1; then
    echo "Git user.email not configured."
    read -p "Enter your email: " USER_EMAIL
    git config --global user.email "$USER_EMAIL"
fi

echo -e "${GREEN}Git configured for: $(git config --global user.name) <$(git config --global user.email)>${NC}"

echo ""
echo -e "${YELLOW}Step 2: Repository Setup${NC}"

# Get repository information
read -p "Enter your GitHub username: " GITHUB_USERNAME
read -p "Enter repository name [freepbx-simple-dialer]: " REPO_NAME
REPO_NAME=${REPO_NAME:-freepbx-simple-dialer}

echo ""
echo -e "${YELLOW}Step 3: Initialize Git Repository${NC}"

# Initialize git if not already done
if [[ ! -d .git ]]; then
    echo "Initializing git repository..."
    git init
    echo -e "${GREEN}Git repository initialized${NC}"
else
    echo -e "${GREEN}Git repository already exists${NC}"
fi

# Create .gitignore if it doesn't exist
if [[ ! -f .gitignore ]]; then
    echo "Creating .gitignore..."
    cat > .gitignore << 'EOF'
# FreePBX Simple Dialer - Git Ignore
*.log
logs/
*.bak
*.backup
*~
*.tmp
*.temp
.DS_Store
Thumbs.db
.vscode/
.idea/
*.sublime-*
*.swp
*.swo
vendor/
composer.lock
.htaccess
*.sql
reports/
simpledialer_reports/
test_contacts.csv
sample_*.csv
local_config.php
dev_settings.php
*.wav
*.mp3
*.gsm
*.ulaw
*.alaw
*.sln
*.zip
*.tar.gz
*.rar
docs/_build/
site/
EOF
    echo -e "${GREEN}.gitignore created${NC}"
fi

echo ""
echo -e "${YELLOW}Step 4: Add Files to Git${NC}"

# Add all files
echo "Adding files to git..."
git add .

# Check if there are changes to commit
if git diff --staged --quiet; then
    echo -e "${YELLOW}No changes to commit${NC}"
else
    echo ""
    echo -e "${YELLOW}Step 5: Create Initial Commit${NC}"
    
    # Create commit message
    COMMIT_MSG="Initial release: FreePBX Simple Dialer Module v1.0.0

Features:
- Automated dialing campaigns with CSV contact upload
- Smart automatic scheduling with cron integration  
- Real-time progress tracking with visual indicators
- System recording integration with format conversion
- Comprehensive reporting with email notifications
- Smart auto-refresh interface (30s intervals)
- Modal-aware UI that pauses during editing
- Color-coded progress bars (blue=active, green=completed)
- Campaign status management and validation
- 7-day automatic report cleanup

🤖 Generated with Claude Code (https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>"

    echo "Creating initial commit..."
    git commit -m "$COMMIT_MSG"
    echo -e "${GREEN}Initial commit created${NC}"
fi

echo ""
echo -e "${YELLOW}Step 6: GitHub Instructions${NC}"
echo ""
echo -e "${BLUE}Now follow these steps to upload to GitHub:${NC}"
echo ""
echo -e "${GREEN}1. Go to GitHub.com and create a new repository:${NC}"
echo "   - Repository name: ${REPO_NAME}"
echo "   - Description: A comprehensive autodialer module for FreePBX with scheduling, contact management, and real-time progress tracking"
echo "   - Make it Public (for community access)"
echo "   - DON'T initialize with README, .gitignore, or license (we have them)"
echo ""
echo -e "${GREEN}2. After creating the repository, run these commands:${NC}"
echo ""
echo -e "${BLUE}git remote add origin https://github.com/${GITHUB_USERNAME}/${REPO_NAME}.git${NC}"
echo -e "${BLUE}git branch -M main${NC}"
echo -e "${BLUE}git push -u origin main${NC}"
echo ""
echo -e "${GREEN}3. Your repository will be available at:${NC}"
echo -e "${BLUE}https://github.com/${GITHUB_USERNAME}/${REPO_NAME}${NC}"
echo ""

# Show current status
echo -e "${YELLOW}Current Git Status:${NC}"
git status --short

echo ""
echo -e "${GREEN}=== Upload script completed! ===${NC}"
echo -e "${YELLOW}Follow the GitHub instructions above to complete the upload.${NC}"
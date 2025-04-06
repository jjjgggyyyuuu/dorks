#!/bin/bash
# Hostinger Git Setup Script for Domain Value Predictor

echo "Domain Value Predictor - Hostinger Git Setup"
echo "============================================="
echo ""

# Check if Git is installed
if ! command -v git &> /dev/null; then
    echo "Git is not installed. Please install Git first."
    exit 1
fi

# Check if we're in the project directory
if [ ! -f "domain-value-predictor.php" ]; then
    echo "Error: domain-value-predictor.php not found."
    echo "Please run this script from the project's root directory."
    exit 1
fi

# Get Hostinger Git repository URL
read -p "Enter your Hostinger Git repository URL: " git_url

if [ -z "$git_url" ]; then
    echo "Error: Repository URL cannot be empty."
    exit 1
fi

# Check if .git directory exists
if [ -d ".git" ]; then
    echo "Git repository already initialized."
    
    # Add Hostinger remote
    echo "Adding Hostinger as a remote repository..."
    git remote add hostinger "$git_url"
    
    if [ $? -ne 0 ]; then
        echo "Error: Failed to add remote repository."
        echo "If 'hostinger' remote already exists, try: git remote set-url hostinger $git_url"
        exit 1
    fi
else
    # Initialize Git repository
    echo "Initializing Git repository..."
    git init
    
    if [ $? -ne 0 ]; then
        echo "Error: Failed to initialize Git repository."
        exit 1
    fi
    
    # Add Hostinger remote
    echo "Adding Hostinger as a remote repository..."
    git remote add hostinger "$git_url"
    
    if [ $? -ne 0 ]; then
        echo "Error: Failed to add remote repository."
        exit 1
    fi
fi

# Create .gitignore file
echo "Creating .gitignore file..."
cat > .gitignore << 'EOF'
# Development files
.DS_Store
Thumbs.db
.idea/
.vscode/
*.sublime-project
*.sublime-workspace

# Build files
node_modules/
vendor/stripe-php.zip
*.zip
npm-debug.log
yarn-error.log

# WordPress specific
wp-config.php
wp-content/advanced-cache.php
wp-content/backup-db/
wp-content/backups/
wp-content/blogs.dir/
wp-content/cache/
wp-content/upgrade/
wp-content/uploads/
wp-content/wp-cache-config.php

# This script
hostinger-git-setup.sh
create-zip.php

# Sensitive files
.env
*.key
EOF

# Stage all files
echo "Staging all files..."
git add .

# Initial commit
echo "Creating initial commit..."
git commit -m "Initial commit of Domain Value Predictor"

# Push to Hostinger
echo "Pushing to Hostinger..."
git push -u hostinger master

if [ $? -ne 0 ]; then
    echo "Error: Failed to push to Hostinger."
    echo "Please check your Hostinger Git repository URL and permissions."
    exit 1
fi

echo ""
echo "Setup completed successfully!"
echo ""
echo "Next steps:"
echo "1. Check your Hostinger control panel to make sure the files were pushed correctly."
echo "2. Configure the plugin through the WordPress admin."
echo "3. For future updates, run: git push hostinger master"
echo "" 
#!/bin/bash

set -e

echo "🚀 ═══════════════════════════════════════════════════════════════"
echo "⚡    LARAVEL FTP DEPLOY - GITHUB ACTION    ⚡"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Validate required inputs
if [ -z "$FTP_SERVER" ]; then
    echo "❌ Error: FTP server is required"
    exit 1
fi

if [ -z "$FTP_USERNAME" ]; then
    echo "❌ Error: FTP username is required"
    exit 1
fi

if [ -z "$FTP_PASSWORD" ]; then
    echo "❌ Error: FTP password is required"
    exit 1
fi

if [ -z "$REMOTE_TREE_URL" ]; then
    echo "❌ Error: Remote tree URL is required"
    exit 1
fi

# Set working directory to the action path
cd "$ACTION_PATH"

# Determine local directory
if [ "$LOCAL_DIR" = "." ] || [ -z "$LOCAL_DIR" ]; then
    if [ -n "$GITHUB_WORKSPACE" ]; then
        LOCAL_DIR="$GITHUB_WORKSPACE"
        echo "📁 Using GitHub workspace as local directory: $LOCAL_DIR"
    else
        LOCAL_DIR="$(pwd)"
        echo "📁 Using current directory as local directory: $LOCAL_DIR"
    fi
else
    # If LOCAL_DIR is relative, make it relative to GITHUB_WORKSPACE
    if [[ "$LOCAL_DIR" != /* ]] && [ -n "$GITHUB_WORKSPACE" ]; then
        LOCAL_DIR="$GITHUB_WORKSPACE/$LOCAL_DIR"
    fi
    echo "📁 Using specified local directory: $LOCAL_DIR"
fi

# Verify local directory exists
if [ ! -d "$LOCAL_DIR" ]; then
    echo "❌ Error: Local directory does not exist: $LOCAL_DIR"
    exit 1
fi

# Build the artisan command
DEPLOY_CMD="php artisan deploy"
DEPLOY_CMD="$DEPLOY_CMD --server=\"$FTP_SERVER\""
DEPLOY_CMD="$DEPLOY_CMD --username=\"$FTP_USERNAME\""
DEPLOY_CMD="$DEPLOY_CMD --password=\"$FTP_PASSWORD\""
DEPLOY_CMD="$DEPLOY_CMD --local-dir=\"$LOCAL_DIR\""
DEPLOY_CMD="$DEPLOY_CMD --remote-tree-url=\"$REMOTE_TREE_URL\""

# Add optional parameters
if [ -n "$FTP_TIMEOUT" ] && [ "$FTP_TIMEOUT" != "60" ]; then
    DEPLOY_CMD="$DEPLOY_CMD --timeout=\"$FTP_TIMEOUT\""
fi

if [ -n "$FTP_MAX_RETRIES" ] && [ "$FTP_MAX_RETRIES" != "4" ]; then
    DEPLOY_CMD="$DEPLOY_CMD --max-retries=\"$FTP_MAX_RETRIES\""
fi

# Add dry-run flag if enabled
if [ "$DRY_RUN" = "true" ]; then
    DEPLOY_CMD="$DEPLOY_CMD --dry-run"
    echo "🔬 Dry run mode enabled"
fi

# Parse and add exclude paths
if [ -n "$EXCLUDE_PATHS" ]; then
    echo "🚫 Processing exclusion patterns..."

    # Convert multiline string to array and add each exclude path
    while IFS= read -r line; do
        # Trim whitespace and skip empty lines
        line=$(echo "$line" | xargs)
        if [ -n "$line" ]; then
            DEPLOY_CMD="$DEPLOY_CMD --exclude-paths=\"$line\""
            echo "   📝 Excluding: $line"
        fi
    done <<< "$EXCLUDE_PATHS"
fi

echo ""
echo "⚡ Executing deployment command..."
echo "🔧 Command: $DEPLOY_CMD"
echo ""

# Create storage directory if it doesn't exist
mkdir -p storage/app

# Execute the deploy command
eval "$DEPLOY_CMD"

# Check exit code
EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ ═══════════════════════════════════════════════════════════════"
    echo "🎉    DEPLOYMENT COMPLETED SUCCESSFULLY    🎉"
    echo "═══════════════════════════════════════════════════════════════"
else
    echo "❌ ═══════════════════════════════════════════════════════════════"
    echo "💥    DEPLOYMENT FAILED    💥"
    echo "═══════════════════════════════════════════════════════════════"
fi

exit $EXIT_CODE

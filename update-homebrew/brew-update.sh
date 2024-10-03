#!/bin/zsh

# Define script directory dynamically
SCRIPT_DIR="${0:A:h}"
LOG_FILE="$SCRIPT_DIR/brew_update.log"
CONFIG_FILE="$SCRIPT_DIR/config.env"

# Source the configuration file if it exists
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
else
    echo "Error: Configuration file not found: $CONFIG_FILE" >> "$LOG_FILE"
    exit 1
fi

# Check if the Slack webhook URL is set
if [ -z "$HOMEBREW_SLACK_WEBHOOK" ]; then
    echo "Error: HOMEBREW_SLACK_WEBHOOK is not set in the configuration file" >> "$LOG_FILE"
    exit 1
fi


# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Function to run command and log output
run_and_log() {
    local cmd="$1"
    local description="$2"
    
    log_message "Starting $description"
    output=$(eval "$cmd" 2>&1)
    exit_code=$?
    
    echo "$output" >> "$LOG_FILE"
    
    if [ $exit_code -eq 0 ]; then
        log_message "$description successful"
    else
        log_message "Error: $description failed with exit code $exit_code"
    fi
    log_message "----------------------------------------"
    
    echo "$output"
}

# Function to extract package updates
extract_updates() {
    local output="$1"
    local update_info=$(echo "$output" | awk '
        /^==> Upgrading [0-9]+ outdated package/ {
            print
            in_section = 1
            packages_to_upgrade = $3
            next
        }
        in_section && packages_to_upgrade > 0 {
            print
            if ($0 ~ /->/) packages_to_upgrade--
        }
        packages_to_upgrade == 0 {
            exit
        }
    ')
    echo "$update_info"
}



# Function to send Slack notification
send_slack_notification() {
    local message="$1"
    curl -X POST -H 'Content-type: application/json' --data "{\"text\":\"$message\"}" "$HOMEBREW_SLACK_WEBHOOK"
}

# Log start of script
log_message "Brew update script started"
log_message "Script directory: $SCRIPT_DIR"
log_message "----------------------------------------"

# Set up Homebrew command
if [ -f "$BREW_PATH" ]; then
    source "$BREW_PATH"
    brew() {
        mock_brew "$@"
    }
elif [ -f /opt/homebrew/bin/brew ]; then
    BREW_PATH="/opt/homebrew/bin/brew"
elif [ -f /usr/local/bin/brew ]; then
    BREW_PATH="/usr/local/bin/brew"
else
    log_message "Error: Homebrew executable not found"
    exit 1
fi

log_message "Using Homebrew at: $BREW_PATH"
if [ -f "$BREW_PATH" ]; then
    export PATH="/opt/homebrew/bin:$PATH"
else
    export PATH="$($BREW_PATH --prefix)/bin:$PATH"
fi
log_message "Updated PATH: $PATH"
log_message "----------------------------------------"

# Update Homebrew itself
update_output=$(run_and_log "brew update" "Homebrew update")
echo "$update_output"

# Upgrade all outdated packages and capture the output
upgrade_output=$(run_and_log "brew upgrade" "Homebrew upgrade")
echo "$upgrade_output"

# Clean up (removes old versions of installed formulae and clears old downloads)
cleanup_output=$(run_and_log "brew cleanup" "Homebrew cleanup")
echo "$cleanup_output"

# Extract updated packages
updated_packages=$(extract_updates "$upgrade_output")

if [ -n "$updated_packages" ]; then
    # Prepare Slack message
    slack_message="The following \`Homebrew\` packages were updated:\n\`\`\`\n$updated_packages\n\`\`\`"
    
    send_slack_notification "$slack_message"
    log_message "Slack notification sent for package updates"
else
    log_message "No packages were updated. Skipping Slack notification."
fi

# Log end of script
log_message "Brew update script completed"
log_message "========================================"
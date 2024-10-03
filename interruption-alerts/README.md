# Interruption Alerts Script

This script monitors your Messages database for specific keywords related to service interruptions and sends notifications to a Slack channel.

## Files

- `notify-interruption-alerts.php`: The main PHP script
- `com.codechilli.interruption-alerts.plist`: The launchd plist file for scheduling the script
- `config.env`: Configuration file for environment variables
- `README.md`: This file

All files are located in `~/Scripts/interruption-alerts/`.

## Setup

1. Ensure PHP is installed via Homebrew:

   ```bash
   brew install php
   ```

2. Create a `config.env` file in the script directory with your Slack webhook URL:

   ```bash
   echo "SLACK_WEBHOOK=https://hooks.slack.com/services/your/webhook/url" > ~/Scripts/interruption-alerts/config.env
   ```

   Replace `https://hooks.slack.com/services/your/webhook/url` with your actual Slack webhook URL.

3. Set appropriate permissions for the config file:

   ```bash
   chmod 600 ~/Scripts/interruption-alerts/config.env
   ```

4. Update the `notify-interruption-alerts.php` script with any other customizations you need (e.g., keywords, time frame).

5. Copy the plist file to the LaunchAgents directory:

   ```bash
   cp -f ~/Scripts/interruption-alerts/com.codechilli.interruption-alerts.plist ~/Library/LaunchAgents/
   ```

6. Load the launchd job:
   ```bash
   launchctl load ~/Library/LaunchAgents/com.codechilli.interruption-alerts.plist
   ```

## Usage

The script will run automatically based on the interval set in the plist file. By default, it runs every 30 minutes (1800 seconds).

### Manual Control

To start the job manually:

```bash
launchctl start ~/Library/LaunchAgents/com.codechilli.interruption-alerts.plist
```

To stop the job:

```bash
launchctl stop ~/Library/LaunchAgents/com.codechilli.interruption-alerts.plist
```

To unload the job (stop it from running automatically):

```bash
launchctl unload ~/Library/LaunchAgents/com.codechilli.interruption-alerts.plist
```

To reload the job after making changes:

```bash
launchctl unload ~/Library/LaunchAgents/com.codechilli.interruption-alerts.plist
launchctl load ~/Library/LaunchAgents/com.codechilli.interruption-alerts.plist
```

## Logs

- Standard output: `~/Scripts/interruption-alerts/interruption-alerts.log`
- Error log: `~/Scripts/interruption-alerts/interruption-alerts-error.log`

Check these logs for any issues or to confirm the script is running as expected.

## Customization

To modify the script's behavior:

1. Edit `notify-interruption-alerts.php` to change keywords, time frame, or other parameters.
2. Edit `com.codechilli.interruption-alerts.plist` to change the run interval or other launchd settings.
3. Edit `config.env` to update the Slack webhook URL or add other environment variables.
4. After any changes, reload the launchd job as described above.

## Troubleshooting

If the script isn't running as expected:

1. Check the log files for any error messages.
2. Ensure the PHP path in the plist file matches your Homebrew PHP installation:
   ```bash
   which php
   ```
3. Verify the script and config file have the correct permissions:
   ```bash
   ls -l ~/Scripts/interruption-alerts/notify-interruption-alerts.php
   ls -l ~/Scripts/interruption-alerts/config.env
   ```
4. Try running the script manually to catch any immediate errors:
   ```bash
   /opt/homebrew/bin/php ~/Scripts/interruption-alerts/notify-interruption-alerts.php
   ```
5. Ensure the `config.env` file contains the correct Slack webhook URL.

For any persistent issues, review the script, plist file, and config.env contents and ensure all paths and settings are correct for your system.

## Security Note

The `config.env` file contains sensitive information (your Slack webhook URL). Ensure it's not accessible to other users on your system and never commit it to version control systems.

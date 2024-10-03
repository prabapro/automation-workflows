# Homebrew Auto-Update Script

This script automates the process of updating Homebrew and its packages on macOS. It runs daily at 9 AM, ensuring your Homebrew packages are always up to date, and sends a Slack notification with the update details.

## Features

- Automatically updates Homebrew
- Upgrades all outdated packages
- Cleans up old versions and downloads
- Logs all activities for easy monitoring
- Sends Slack notifications with package update information
- Runs daily at 9 AM

## Installation

1. Clone this repository or copy the script files to your preferred location. By default, it's located at:

   ```
   ~/Scripts/update-homebrew/
   ```

2. Make sure the main script is executable:

   ```bash
   chmod +x ~/Scripts/update-homebrew/brew-update.sh
   ```

3. Copy the `com.codechilli.brew-update.plist` launchd plist file to:

   ```bash
   ~/Library/LaunchAgents/
   ```

4. Create a `config.env` file in the same directory as the `brew-update.sh` script:

   ```bash
   touch ~/Scripts/update-homebrew/config.env
   ```

5. Add your Slack webhook URL to the `config.env` file:

   ```bash
   echo "HOMEBREW_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK" >> ~/Scripts/update-homebrew/config.env
   ```

   Replace `https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK` with your actual Slack webhook URL.

6. Ensure that `config.env` is listed in your `.gitignore` file to prevent it from being tracked by git.

7. Load the launchd job:
   ```bash
   launchctl load ~/Library/LaunchAgents/com.codechilli.brew-update.plist
   ```

## Configuration

The script uses a `config.env` file to store sensitive information like the Slack webhook URL. This file should not be committed to the repository.

**Note:** The `config.env` file contains sensitive information. Do not commit or share this file.

## Usage

Once installed and configured, the script will run automatically every day at 9 AM. You don't need to do anything else.

To manually run the script at any time:

```bash
launchctl start com.codechilli.brew-update
```

## Slack Notifications

When packages are updated, you'll receive a Slack notification with the following format:

```
The following `Homebrew` packages were updated:

==> Upgrading X outdated packages:
package1 old_version -> new_version
package2 old_version -> new_version
```

If no packages are updated, no Slack notification will be sent.

## Debugging

For debugging purposes, a separate script `debug-brew-update.sh` is provided. This script allows you to test the functionality without actually updating your Homebrew packages.

To use the debug script:

1. Make it executable:

   ```bash
   chmod +x ~/Scripts/update-homebrew/debug-brew-update.sh
   ```

2. Run the debug script in mock mode:

   ```bash
   ~/Scripts/update-homebrew/debug-brew-update.sh mock
   ```

   This will simulate a Homebrew update process and send a test Slack notification.

3. To run the actual update process for debugging:

   ```bash
   ~/Scripts/update-homebrew/debug-brew-update.sh normal
   ```

## Logs

The script generates logs in the following location:

- Main log: `~/Scripts/update-homebrew/brew_update.log`

You can view the log with:

```bash
cat ~/Scripts/update-homebrew/brew_update.log
```

## Customization

- To change the time when the script runs, modify the `StartCalendarInterval` in the plist file.
- To change the log location, update the path in the shell script.

## Troubleshooting

If the script isn't running as expected:

1. Check the log file for any error messages.
2. Ensure the script has executable permissions.
3. Verify that the paths in the plist file are correct.
4. Make sure Homebrew is installed and accessible in the specified PATH.
5. Verify that the Slack webhook URL in `config.env` is correct and the associated Slack app has the necessary permissions.
6. Ensure the `config.env` file exists and contains the `HOMEBREW_SLACK_WEBHOOK` variable.

## Uninstalling

To stop the automatic updates:

1. Unload the launchd job:
   ```bash
   launchctl unload ~/Library/LaunchAgents/com.codechilli.brew-update.plist
   ```
2. Remove the plist file:
   ```bash
   rm ~/Library/LaunchAgents/com.codechilli.brew-update.plist
   ```
3. Optionally, remove the script and log files:
   ```bash
   rm -rf ~/Scripts/update-homebrew
   ```

## Security Note

The `config.env` file contains sensitive information (your Slack webhook URL). Ensure this file is not tracked by git and is not shared or exposed publicly. The `.gitignore` file in this repository is set up to ignore `config.env`, but verify this if you're using this script in your own repository.

## Contributing

Contributions to improve the script are welcome. Please ensure you do not commit any sensitive information when submitting pull requests.

## License

[MIT License](LICENSE)

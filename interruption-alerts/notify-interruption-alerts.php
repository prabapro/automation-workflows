
<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
error_reporting(E_ALL);

// Load environment variables from config.env
$config = parse_ini_file(__DIR__ . '/config.env');

// Customizable variables
$searchKeywords = ['interrupted', 'Electricity supply', 'Water supply'];  // Add or remove keywords as needed
$hoursToLookBack = 1;  // Change this to the number of hours you want to look back
$slackWebhookUrl = $config['SLACK_WEBHOOK'] ?? '';  // Get Slack webhook URL from config.env

if (empty($slackWebhookUrl)) {
    logMessage("ERROR: Slack webhook URL is not set in config.env");
    exit(1);
}

// Set the database path
$dbPath = $_SERVER['HOME'] . '/Library/Messages/chat.db';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Check if log file is writable
$logFile = __DIR__ . '/interruption-alerts.log';
if (!is_writable($logFile)) {
    error_log("Log file is not writable: $logFile");
    // Try to create it if it doesn't exist
    if (!file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    // Set permissions
    chmod($logFile, 0666);
}

// Function to log messages with timestamp in IST
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp IST] $message\n";
    file_put_contents(__DIR__ . '/interruption-alerts.log', $logEntry, FILE_APPEND);
    echo $logEntry; // This will output to stdout, which should be captured in the log file specified in the plist
}

// Function to clean up old log entries
function cleanupLogs($maxAgeDays = 7, $maxSizeMB = 10) {
    $logFiles = [
        __DIR__ . '/interruption-alerts.log',
        __DIR__ . '/interruption-alerts-error.log'
    ];

    foreach ($logFiles as $logFile) {
        if (!file_exists($logFile)) continue;

        // Check file size
        $sizeInMB = filesize($logFile) / 1024 / 1024;
        if ($sizeInMB > $maxSizeMB) {
            file_put_contents($logFile, "Log file truncated due to size limit.\n");
            $needsTruncate = true;
        } else {
            $needsTruncate = false;
        }

        $tempFile = $logFile . '.temp';
        $cutoffTime = strtotime("-{$maxAgeDays} days");

        $inHandle = fopen($logFile, 'r');
        $outHandle = fopen($tempFile, 'w');

        while (($line = fgets($inHandle)) !== false) {
            // Try to extract timestamp from the beginning of the line
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($logTime >= $cutoffTime && !$needsTruncate) {
                    fwrite($outHandle, $line);
                }
            } else {
                // If line doesn't start with a timestamp, keep it (could be part of a stack trace)
                if (!$needsTruncate) {
                    fwrite($outHandle, $line);
                }
            }
        }

        fclose($inHandle);
        fclose($outHandle);

        // Replace old file with new file
        rename($tempFile, $logFile);
    }
}

// Call cleanup function at the start of the script
cleanupLogs(7, 10);  // Keep logs for 7 days, truncate if larger than 10MB

logMessage("Script started execution");

// Function to send Slack notification
function sendSlackNotification($message) {
    global $slackWebhookUrl;
    $ch = curl_init($slackWebhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['text' => $message]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode(['text' => $message]))
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Function to check if a message has been notified before
function hasBeenNotified($messageId) {
    $notifiedFile = __DIR__ . '/notified-alerts.txt';
    if (!file_exists($notifiedFile)) {
        file_put_contents($notifiedFile, '');
        return false;
    }
    $notifiedMessages = file($notifiedFile, FILE_IGNORE_NEW_LINES);
    return in_array($messageId, $notifiedMessages);
}

// Function to mark a message as notified
function markAsNotified($messageId) {
    $notifiedFile = __DIR__ . '/notified-alerts.txt';
    file_put_contents($notifiedFile, $messageId . PHP_EOL, FILE_APPEND);
}

// Debug information
logMessage("Script executed by user: " . exec('whoami'));
logMessage("PHP version: " . phpversion());
logMessage("Database path: " . $dbPath);
logMessage("File exists: " . (file_exists($dbPath) ? 'Yes' : 'No'));
logMessage("Is readable: " . (is_readable($dbPath) ? 'Yes' : 'No'));
logMessage("File permissions: " . substr(sprintf('%o', fileperms($dbPath)), -4));
logMessage("File owner: " . posix_getpwuid(fileowner($dbPath))['name']);

if (!file_exists($dbPath)) {
    logMessage("ERROR: Messages database file does not exist.");
    exit(1);
}

if (!is_readable($dbPath)) {
    logMessage("ERROR: Messages database file is not readable.");
    logMessage("Current user: " . exec('whoami'));
    logMessage("File owner: " . posix_getpwuid(fileowner($dbPath))['name']);
    logMessage("File permissions: " . substr(sprintf('%o', fileperms($dbPath)), -4));
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logMessage("Successfully connected to the database");
} catch (PDOException $e) {
    logMessage("ERROR: Unable to connect to your Messages database.");
    logMessage("Error message: " . $e->getMessage());
    exit(1);
}

try {
    $keywordConditions = implode(' OR ', array_map(function($keyword) {
        return "message.text LIKE :keyword_" . md5($keyword);
    }, $searchKeywords));

    $query = $db->prepare("
        WITH RankedMessages AS (
            SELECT
                message.rowid,
                ifnull(handle.uncanonicalized_id, chat.chat_identifier) AS sender,
                message.service,
                datetime(message.date / 1000000000 + 978307200, 'unixepoch', 'localtime') AS message_date,
                message.text,
                ROW_NUMBER() OVER (PARTITION BY ifnull(handle.uncanonicalized_id, chat.chat_identifier) ORDER BY message.date DESC) as rn
            FROM
                message
                    LEFT JOIN chat_message_join
                            ON chat_message_join.message_id = message.ROWID
                    LEFT JOIN chat
                            ON chat.ROWID = chat_message_join.chat_id
                    LEFT JOIN handle
                            ON message.handle_id = handle.ROWID
            WHERE
                message.is_from_me = 0
                AND message.text IS NOT NULL
                AND length(message.text) > 0
                AND ($keywordConditions)
                AND datetime(message.date / 1000000000 + strftime('%s', '2001-01-01'), 'unixepoch', 'localtime')
                        >= datetime('now', :timeframe, 'localtime')
        )
        SELECT * FROM RankedMessages
        WHERE rn = 1
        ORDER BY message_date DESC
    ");
    logMessage("Query prepared successfully");

    $params = [':timeframe' => '-' . $hoursToLookBack . ' hours'];
    foreach ($searchKeywords as $keyword) {
        $params[':keyword_' . md5($keyword)] = '%' . $keyword . '%';
    }
    $query->execute($params);
    logMessage("Query executed successfully");

} catch (PDOException $e) {
    logMessage("ERROR: Unable to query your Messages database.");
    logMessage("Error message: " . $e->getMessage());
    exit(1);
}

$found = false;

while ($message = $query->fetch(PDO::FETCH_ASSOC)) {
    $found = true;
    $date = date('Y-m-d H:i:s', strtotime($message['message_date']));
    $sender = formatSender($message['sender']);
    $text = $message['text'];
    $messageId = $message['rowid'];

    logMessage("Debug - Message found:");
    logMessage("Date: " . $date);
    logMessage("From: " . $sender);
    logMessage("Text: " . $text);
    logMessage("");

    if (!hasBeenNotified($messageId)) {
        $slackMessage = "*Date:* `$date`\n\n";
        $slackMessage .= "*From:* `$sender`\n\n";
        $slackMessage .= "```$text```";

        sendSlackNotification($slackMessage);
        markAsNotified($messageId);

        logMessage("Sent notification for message:");
        logMessage($slackMessage);
    } else {
        logMessage("Already notified about message:");
        logMessage("Date: $date");
        logMessage("From: $sender");
        logMessage("Message: $text");
    }
    logMessage("");
}

logMessage($found ? "Found messages matching keywords" : "No new messages containing any of the keywords found in the last $hoursToLookBack hours.");

logMessage("Script execution completed.");
logMessage("----------------------------------------");

function formatSender($sender)
{
    $sender = trim($sender, '+');

    if (strlen($sender) === 11 && substr($sender, 0, 1) === '1') {
        $sender = substr($sender, 1);
    }

    if (strlen($sender) === 10) {
        return substr($sender, 0, 3) . '-' . substr($sender, 3, 3) . '-' . substr($sender, 6, 4);
    }

    return $sender;
}
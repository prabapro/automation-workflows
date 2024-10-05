<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug-error.log');
error_reporting(E_ALL);

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Set the database path
$dbPath = $_SERVER['HOME'] . '/Library/Messages/chat.db';

// Function to log messages with timestamp in IST
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp IST] $message\n";
}

// Function to get user input
function getUserInput($prompt) {
    echo $prompt;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return trim($line);
}

// Get user input for keywords and time interval
$keywords = explode(',', getUserInput("Enter keywords (comma-separated): "));
$timeInterval = getUserInput("Enter time interval (e.g., '24 hours', '7 days'): ");

// Establish database connection
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logMessage("Successfully connected to the database");
} catch (PDOException $e) {
    logMessage("ERROR: Unable to connect to your Messages database.");
    logMessage("Error message: " . $e->getMessage());
    exit(1);
}

// Prepare the query
try {
    $keywordConditions = implode(' OR ', array_map(function($keyword) {
        return "LOWER(message.text) LIKE LOWER(:keyword_" . md5($keyword) . ")";
    }, $keywords));

    $query = $db->prepare("
        SELECT
            message.rowid,
            ifnull(handle.uncanonicalized_id, chat.chat_identifier) AS sender,
            datetime(message.date / 1000000000 + 978307200, 'unixepoch', 'localtime') AS message_date,
            message.text
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
                    >= datetime('now', '-' || :time_interval, 'localtime')
        ORDER BY message_date DESC
    ");

    $params = [':time_interval' => $timeInterval];
    foreach ($keywords as $keyword) {
        $params[':keyword_' . md5($keyword)] = '%' . trim($keyword) . '%';
    }
    $query->execute($params);
    logMessage("Query executed successfully");

} catch (PDOException $e) {
    logMessage("ERROR: Unable to query your Messages database.");
    logMessage("Error message: " . $e->getMessage());
    exit(1);
}

// Fetch and display results
$results = $query->fetchAll(PDO::FETCH_ASSOC);
$count = count($results);

logMessage("Found $count message(s) matching the criteria:");
logMessage("Keywords: " . implode(', ', $keywords));
logMessage("Time Interval: $timeInterval");
logMessage("----------------------------------------");

foreach ($results as $message) {
    echo "Date: " . $message['message_date'] . "\n";
    echo "From: " . $message['sender'] . "\n";
    echo "Message: " . $message['text'] . "\n";
    echo "----------------------------------------\n";
}

logMessage("Debug search completed.");
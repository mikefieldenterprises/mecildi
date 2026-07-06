<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Basic security check: Only allow updates if a process is not running
$statusFile = __DIR__ . '/process.status';
if (file_exists($statusFile) && trim(file_get_contents($statusFile)) === 'running') {
    echo json_encode(['success' => false, 'message' => 'Cannot change settings while a process is running.']);
    exit;
}

// Get the inputs
$key = $_POST['key'] ?? null;
$value = $_POST['value'] ?? null;

if (!$key) {
    echo json_encode(['success' => false, 'message' => 'Missing key.']);
    exit;
}

// 1. Read existing preferences
$prefFile = __DIR__ . '/config/preferences.json';
$prefs = file_exists($prefFile) ? json_decode(file_get_contents($prefFile), true) : [];

// 2. Update the specific value
// Handle boolean strings from Javascript/AJAX
if ($value === 'true') $value = true;
if ($value === 'false') $value = false;

$prefs[$key] = $value;

// 3. Save back to file
if (file_put_contents($prefFile, json_encode($prefs, JSON_PRETTY_PRINT), LOCK_EX)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to write to preferences.json']);
}
?>
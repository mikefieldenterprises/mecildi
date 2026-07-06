<?php
declare(strict_types=1);

// Load config and helper functions
require_once __DIR__ . '/bootstrap.php';
logger_logInfo("Inside ajax-start-process.php | Attempting to begin process");
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false];

$statusFile    = __DIR__ . '/process.status';
$startTimeFile = __DIR__ . '/process.starttime';
$downloadPathFile = __DIR__ . '/process.downloadpath';

// --- GUARD: Check if process is already running via PS ---
// This is more reliable than checking a status file which might be stale
$checkCmd = "pgrep -f process-worker.php";
$existingPid = shell_exec($checkCmd);

if (!empty($existingPid)) {
    logger_logInfo("Inside ajax-start-process.php | Process already running with PID: " . trim($existingPid));
    echo json_encode(['success' => true, 'message' => 'Already running']);
    exit;
}

try {
    // 1. Mark process as running
    file_put_contents($statusFile, 'running', LOCK_EX);

    // 2. Record start time
    file_put_contents($startTimeFile, (string)time(), LOCK_EX);
    
    // 3. Clear download path file
    file_put_contents($downloadPathFile, "", LOCK_EX);

    // Use 'php' instead of PHP_BINARY to force the CLI version.
    // php-cgi often causes fork-loops or immediate crashes on shared servers.
    $phpPath = '/opt/alt/php82/usr/bin/php'; // The usual '/usr/local/bin/php' loads php v5.6 but we want 8.2
    if (!file_exists($phpPath)) $phpPath = 'php'; // Fallback
    
    $cmd = $phpPath . ' ' . __DIR__ . '/process-worker.php > /dev/null 2>&1 &';
    
    logger_logInfo("Inside ajax-start-process.php | About to run cmd: ".$cmd);
    exec($cmd);
    
    // Give it a micro-second to initialize
    usleep(500000); 
    
    $newPid = shell_exec($checkCmd);
    logger_logInfo("Inside ajax-start-process.php | Executed. New PID check: " . ($newPid ? trim($newPid) : "FAILED TO START"));

    $response['success'] = !empty($newPid);
    if (!$response['success']) {
        $response['error'] = 'Failed to start background worker.';
        file_put_contents($statusFile, '', LOCK_EX);
    }


} catch (Throwable $e) {
    logger_logError("Inside ajax-start-process.php | Server error");
    $response['error'] = 'Erreur serveur.';
}

logger_logInfo("Inside ajax-start-process.php | Finished attempt to begin process");


echo json_encode($response);
exit;
?>
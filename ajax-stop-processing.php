<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
logger_logInfo("Inside ajax-stop-process.php | Attempting to begin process");
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false];

$statusFile = __DIR__ . '/process.status';
$pidFile    = __DIR__ . '/process.pid';
$startTimeFile = __DIR__ . '/process.starttime';
$stopTimeFile = __DIR__ . '/process.stoptime';
$accumFile = __DIR__ . '/process.accumulated';

try {
    // 1. Request cooperative shutdown
    file_put_contents($statusFile, 'stopped', LOCK_EX);

    // 2. Record the stop time
    file_put_contents($stopTimeFile, (string)time(), LOCK_EX);

    // 3. Attempt to terminate the worker process if PID exists
    if (file_exists($pidFile)) {
        $pid = (int) trim(file_get_contents($pidFile));

        if ($pid > 0) {
            // Check process exists before killing
            if (function_exists('posix_kill')) {
                @posix_kill($pid, SIGTERM);
            } else {
                // Fallback (Linux)
                @exec('kill ' . $pid . ' 2>/dev/null');
            }
        }
    }
    
    
    // 3. Using -f to match the full command line path ensures we don't kill other PHP processes
    $workerName = 'process-worker.php';
    $checkCmd = "pgrep -f " . escapeshellarg($workerName);
    $pids = shell_exec($checkCmd);
    
    if (!empty($pids)) {
        // Split into array in case multiple instances are somehow running
        $pidArray = explode("\n", trim($pids));
        
        foreach ($pidArray as $pid) {
            $pid = (int)trim($pid);
            if ($pid > 0) {
                // Attempt a polite termination (SIGTERM)
                exec("kill $pid > /dev/null 2>&1");
                
                // Wait a moment and force kill if still alive (SIGKILL)
                usleep(250000); 
                exec("kill -9 $pid > /dev/null 2>&1");
            }
        }
        logger_logInfo("Inside ajax-stop-process.php | Terminated process-worker.php instances: " . str_replace("\n", ", ", $pids));
    } else {
        logger_logError("Inside ajax-stop-process.php | Stop requested but no process-worker.php found running.");
    }    
    
    // Update process.accumulated to keep track of total elapsed time
    $startTime = (int)file_get_contents($startTimeFile);
    $accumulated = (int)file_get_contents($accumFile) ?: 0;
    $currentRun = time() - $startTime;   // seconds for this run
    if ($currentRun < 0) $currentRun = 0;
    $accumulated += $currentRun;
    file_put_contents($accumFile, (string)$accumulated, LOCK_EX);


    $response['success'] = true;
    
} catch (Throwable $e) {
    $response['error'] = 'Erreur serveur.';
}

logger_logCrawler("Processus en arrière-plan arrêté manuellement");
echo json_encode($response);
exit;
?>
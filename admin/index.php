<?php
require_once '../bootstrap.php'; 

// Paths to log files
$systemLogFile = __DIR__ . '/../logs/log.txt';
$errorLogFile  = __DIR__ . '/../error_log'; 

/**
 * Helper to read the last N lines of a file
 */
function getLastLines($filepath, $lines = 100) {
    if (!file_exists($filepath)) return "Log file not found at: " . htmlspecialchars($filepath);
    
    $data = file($filepath);
    if (!$data) return "Log file is empty.";
    
    $lastLines = array_slice($data, -$lines);
    return implode("", $lastLines);
}

$numLinesSystem = 100;
$numLinesError  = 50;

$lastSystemLogs = getLastLines($systemLogFile, $numLinesSystem);
$lastErrorLogs  = getLastLines($errorLogFile, $numLinesError);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mecildi Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .log-container { 
            background: #212529; 
            color: #00ff00; 
            padding: 15px; 
            border-radius: 5px; 
            font-family: 'Courier New', Courier, monospace; 
            font-size: 0.82rem;
            white-space: pre-wrap;
            max-height: 350px;
            overflow-y: auto;
        }
        .error-log-container {
            background: #1a1010;
            color: #ffb3b3; 
            border: 1px solid #5c2b2b;
        }
        .sidebar { background: #fff; border-right: 1px solid #dee2e6; min-height: 100vh; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 sidebar p-3">
            <h5 class="mb-4">Mecildi Admin</h5>
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link active fw-bold" href="index.php">🏠 Dashboard</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-dark" href="edit-config.php">⚙️ Edit app.ini</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-dark" href="edit-preferences.php">🛠️ Edit preferences.json</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-dark" href="edit-phrases.php?file=construction">🚧 Edit Construction Phrases</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-dark" href="edit-phrases.php?file=hosting">🏢 Edit Hosting Phrases</a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger border-top pt-3" href="../index.php">← Back to App</a>
                </li>
            </ul>
        </nav>

        <main class="col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>System Dashboard</h2>
                <span class="badge bg-success text-white">Admin Access</span>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>System Logs (Last <?php echo $numLinesSystem; ?> Lines)</span>
                            <a href="log-viewer.php" class="btn btn-sm btn-outline-primary">View Full Logs</a>
                        </div>
                        <div class="card-body">
                            <div class="log-container"><?php echo htmlspecialchars($lastSystemLogs); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-danger">
                        <div class="card-header bg-danger text-white">
                            <span>PHP Error Log (Last <?php echo $numLinesError; ?> Lines)</span>
                        </div>
                        <div class="card-body">
                            <div class="log-container error-log-container"><?php echo htmlspecialchars($lastErrorLogs); ?></div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

</body>
</html>
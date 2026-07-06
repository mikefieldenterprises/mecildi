<?php
// Configuration
$logFile = __DIR__ . '/../logs/log.txt';
$allowedFilters = ['ERROR', 'WARN', 'INFO', 'DEBUG', 'ALL'];

$filter = isset($_GET['level']) ? strtoupper($_GET['level']) : 'ERROR';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 5000);

// Clamp per-page
$perPage = max(1000, min($perPage, 10000));

// Validate filter
if (!in_array($filter, $allowedFilters)) {
    $filter = 'ERROR';
}

// Security header
header('X-Content-Type-Options: nosniff');

// Check file
if (!is_readable($logFile)) {
    http_response_code(500);
    echo "Log file not accessible.";
    exit;
}

//
// ✅ DOWNLOAD MODE
//
if ($filter === 'ALL' && isset($_GET['download'])) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="log_all.txt"');
    @set_time_limit(0);

    $handle = fopen($logFile, 'r');
    while (($line = fgets($handle)) !== false) {
        echo $line;
    }
    fclose($handle);
    exit;
}

//
// ✅ COUNT TOTAL MATCHES (needed for LAST page)
//
$totalMatches = 0;
$handle = fopen($logFile, 'r');

while (($line = fgets($handle)) !== false) {
    if (strpos($line, "[$filter]") !== false) {
        $totalMatches++;
    }
}
fclose($handle);

$totalPages = max(1, (int)ceil($totalMatches / $perPage));
$page = min($page, $totalPages);

// Calculate range
$start = ($page - 1) * $perPage;
$end   = $start + $perPage;

//
// ✅ RENDER PAGE
//
header('Content-Type: text/html; charset=UTF-8');

$handle = fopen($logFile, 'r');

$currentIndex = 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Log Viewer</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; }
        a { color: #4ea3ff; text-decoration: none; margin-right: 8px; }
        .error { color: #ff6b6b; }
        .info { color: #6bff95; }
        .warn { color: #ff9f43; }
        .debug { color: #ffd36b; }
        .line { white-space: pre-wrap; }
        .nav { margin: 10px 0; }
    </style>
</head>
<body>

<h2>Log Viewer</h2>

<!-- FILTER LINKS -->
<div class="nav">
    <a href="?level=ERROR">[ERROR]</a>
    <a href="?level=WARN">[WARN]</a>
    <a href="?level=INFO">[INFO]</a>
    <a href="?level=DEBUG">[DEBUG]</a>
    <a href="?level=ALL&download=1">[ALL]</a>
</div>

<!-- NAVIGATION (TOP) -->
<div class="nav">
    Page <?php echo $page; ?> / <?php echo $totalPages; ?> |

    <a href="?level=<?php echo $filter; ?>&page=1&per_page=<?php echo $perPage; ?>">[FIRST]</a>

    <?php if ($page > 1): ?>
        <a href="?level=<?php echo $filter; ?>&page=<?php echo $page-1; ?>&per_page=<?php echo $perPage; ?>">[PREV]</a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
        <a href="?level=<?php echo $filter; ?>&page=<?php echo $page+1; ?>&per_page=<?php echo $perPage; ?>">[NEXT]</a>
    <?php endif; ?>

    <a href="?level=<?php echo $filter; ?>&page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>">[LAST]</a>
</div>

<hr>

<?php
while (($line = fgets($handle)) !== false) {

    if (strpos($line, "[$filter]") === false) {
        continue;
    }

    if ($currentIndex < $start) {
        $currentIndex++;
        continue;
    }

    if ($currentIndex >= $end) {
        break;
    }

    $safeLine = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
    $class = strtolower($filter);

    echo "<div class='line {$class}'>{$safeLine}</div>";

    $currentIndex++;
}
fclose($handle);
?>

<hr>

<!-- NAVIGATION (BOTTOM) -->
<div class="nav">
    <a href="?level=<?php echo $filter; ?>&page=1&per_page=<?php echo $perPage; ?>">[FIRST]</a>

    <?php if ($page > 1): ?>
        <a href="?level=<?php echo $filter; ?>&page=<?php echo $page-1; ?>&per_page=<?php echo $perPage; ?>">[PREV]</a>
    <?php endif; ?>

    <?php if ($page < $totalPages): ?>
        <a href="?level=<?php echo $filter; ?>&page=<?php echo $page+1; ?>&per_page=<?php echo $perPage; ?>">[NEXT]</a>
    <?php endif; ?>

    <a href="?level=<?php echo $filter; ?>&page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>">[LAST]</a>
</div>

</body>
</html>
<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');


// Initialize response structure
$response = [
    'process' => [
        'running' => false,
    ],
    'actionButtonsHtml' => '',
    'logViewerHtml'     => '',
    'fileStatusHtml'    => '',
    'downloadFilename'  => '',
];

// Paths and basic setup
$inputDir   = __DIR__ . '/temp-input';
$statusFile = __DIR__ . '/process.status';
$downloadPathFile = __DIR__ . '/process.downloadpath';

// 1. Check if process is currently running
$processRunning = false;

if (file_exists($statusFile)) {
    $status = trim((string)file_get_contents($statusFile));
    if ($status === 'running') {
        $processRunning = true;
    }
}

$response['process']['running'] = $processRunning;

// 2. Load file list
$files = is_dir($inputDir)
    ? array_map('basename', glob($inputDir . '/*.txt'))
    : [];

// 3. Load ALL file progress from DB ONCE
$fileProgress = [];

foreach ($files as $filename) {
    $row = db_getDataProgress($config, $filename);

    if ($row) {
        $fileProgress[$filename] = [
            'last'  => (int)$row['LAST_LINE_PROCESSED'],
            'total' => (int)$row['TOTAL_LINES'],
        ];
    } else {
        $fileProgress[$filename] = [
            'last'  => 0,
            'total' => 0,
        ];
    }
}


// 4. Get download path, if any
$downloadPath = (string)file_get_contents($downloadPathFile);

// 5. Aggregate progress (for buttons + ETA)
$totalFiles     = count($files);
$startedFiles   = 0;
$completedFiles = 0;
$totalRemainingSeconds = 0;
$totalTimeOverallSeconds = 0;
$downloadFileExists = !empty($downloadPath);
$totalExcelGenTimeRequired = 0;
$totalDomains = 0;

$ESTIMATED_SECONDS_PER_DOMAIN = $config->performance['seconds_per_domain'];
$ESTIMATED_SECONDS_PER_DOMAIN_FOR_EXCEL_GEN = $config->performance['seconds_per_domain_excel'];

foreach ($fileProgress as $data) {
    
    $totalTimeOverallSeconds += $data['total'] * $ESTIMATED_SECONDS_PER_DOMAIN;

    if ($data['last'] > 0) {
        $startedFiles++;
    }

    if ($data['total'] > 0 && $data['last'] >= $data['total']) {
        $completedFiles++;
    }

    if ($data['total'] > 0 && $data['last'] <= $data['total']) {
        $totalRemainingSeconds +=
            ($data['total'] - $data['last']) * $ESTIMATED_SECONDS_PER_DOMAIN;
    }
    
    $totalDomains += $data['total'];

}

$totalEstimatedConversionTime = ceil($totalDomains * $ESTIMATED_SECONDS_PER_DOMAIN_FOR_EXCEL_GEN);
$totalTimeOverallSeconds += $totalEstimatedConversionTime;
$totalRemainingSeconds += $totalEstimatedConversionTime;


// 6. Decide which action button to show
$DEBUG_CREATE_EXCEL_ONLY = $config->debug['create_excel_only'];

if ($processRunning) {

    $response['actionButtonsHtml'] = '
        <button class="button-58" id="stop-processing" type="button"
                style="background:#dc2626;">
            ARRÊTER LE TRAITEMENT
        </button>';
        
} elseif ($DEBUG_CREATE_EXCEL_ONLY) {

    $response['actionButtonsHtml'] = '
        <button class="button-58" id="continue-processing" type="button"
                style="background:#2563eb;">
            REPRENDRE LE TRAITEMENT
        </button>';


} elseif ($totalFiles > 0 && $completedFiles === $totalFiles && !empty($downloadPath)) {

    $safeDownloadPath = basename($downloadPath);
    $safeDownloadPath = htmlspecialchars($safeDownloadPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $response['actionButtonsHtml'] = "
        <a href=\"./temp-output-final/{$safeDownloadPath}\">
            <button class=\"button-59\" type=\"button\">
                TRAITEMENT TERMINÉ - TÉLÉCHARGER LES RÉSULTATS
            </button>
        </a>";

} elseif ($startedFiles > 0 && $completedFiles < $totalFiles) {

    $response['actionButtonsHtml'] = '
        <button class="button-58" id="continue-processing" type="button"
                style="background:#2563eb;">
            REPRENDRE LE TRAITEMENT
        </button>';

} else {
    
    $response['actionButtonsHtml'] = '
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <button class="button-58" id="start-processing" type="button" style="margin: 0;">
                DÉMARRER LE TRAITEMENT
            </button>
            
            <div style="display: flex; align-items: center; gap: 12px;">
                <div id="settings-link-container" style="margin-top:15px;">
                    <a href="#" id="open-settings" style="
                        color:#2563eb;
                        font-weight:600;
                        display:inline-flex;
                        align-items:center;
                        gap:6px;
                    ">
                        ⚙️ Paramètres
                    </a>
                </div>
            </div>
            
        </div>';
}

// 6. Log viewer
$logFile = __DIR__ . '/logs/log-crawler.txt';

if (!file_exists($logFile) || !is_readable($logFile)) {

    $response['logViewerHtml'] =
        "<div style='color:#777;'>Aucun journal disponible.</div>";

} else {

    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!$lines) {

        $response['logViewerHtml'] =
            "<div style='color:#777;'>Journal vide.</div>";

    } else {

        $lastLines = array_slice($lines, -30);

        $html = "<div style='font-family:monospace; white-space:pre-line;'>";
        foreach ($lastLines as $line) {
            $html .= htmlspecialchars(
                $line,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) . "\n";
        }
        $html .= "</div>";

        $response['logViewerHtml'] = $html;
    }
}

// 7. File status HTML
if (!is_dir($inputDir)) {

    $response['fileStatusHtml'] = '<p>Aucun fichier trouvé.</p>';

} elseif (empty($files)) {

    $response['fileStatusHtml'] = '<p>Aucun fichier à traiter.</p>';

} else {

    $startTimeFile = __DIR__ . '/process.starttime';
    $accumFile     = __DIR__ . '/process.accumulated';

    $startTime = file_exists($startTimeFile)
        ? (int) trim(file_get_contents($startTimeFile))
        : time();

    $accumulated = file_exists($accumFile)
        ? (int) trim(file_get_contents($accumFile))
        : 0;

    if ($processRunning) {
        $elapsedSeconds = $accumulated + (time() - $startTime);
        $processText   = 'En cours';
        $bgStatus      = '#d1e8ff';
    } else {
        $elapsedSeconds = $accumulated;
        $processText   = 'Arrêté';
        $bgStatus      = '#e5e5e5';
    }

    if ($elapsedSeconds < 0) {
        $elapsedSeconds = 0;
    }

    $totalRemainingTime = utils_formatDuration((int)$totalRemainingSeconds);
    $totalElapsedTime   = utils_formatDuration((int)$elapsedSeconds);
    $totalTimeOverall   = utils_formatDuration((int)$totalTimeOverallSeconds);
    $totalEstimatedConversionTime = utils_formatDuration((int)$totalEstimatedConversionTime);

    $html = "
        <div style=\"margin-bottom:15px; padding:12px; background:$bgStatus; border-radius:6px;\">
            <strong>Processus :</strong> $processText<br>
            <strong>Temps total estimé :</strong>
            <span style=\"font-size:1.1em;\">$totalTimeOverall</span>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Temps restant estimé :</strong>
            <span style=\"font-size:1.1em;\">$totalRemainingTime</span>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Temps écoulé :</strong>
            <span style=\"font-size:1.1em;\">$totalElapsedTime</span>
        </div>
    ";

    $allDomainsCrawled = true;

    foreach ($fileProgress as $filename => $data) {

        $last  = $data['last'];
        $total = $data['total'];

        if ($total > 0 && $last >= $total) {
            $statusText = 'Terminé';
            $bg = '#d1fae5';
        } elseif ($last > 0) {
            $statusText = 'En cours';
            $bg = '#fef3c7';
            $allDomainsCrawled = false;
        } else {
            $statusText = 'En attente';
            $bg = '#e5e7eb';
            $allDomainsCrawled = false;
        }

        $remainingSeconds =
            ($total > 0 && $last <= $total)
                ? ($total - $last) * $ESTIMATED_SECONDS_PER_DOMAIN
                : 0;

        $remainingTime = utils_formatDuration((int)$remainingSeconds);
        $safeFilename = htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html .= "
            <li style=\"background:$bg;\">
                <strong>$safeFilename</strong><br>
                $last / $total lignes<br>
                <em>$statusText</em><br>
                Temps restant estimé pour le traitement:
                <strong>$remainingTime</strong>
            </li>
        ";
        
    }
    

    if ($allDomainsCrawled && $downloadFileExists) {
        $statusText = 'Terminé';
        $bg = '#d1fae5';
    } elseif ($allDomainsCrawled && !$downloadFileExists) {
        $statusText = 'En cours';
        $bg = '#fef3c7';
    } else {
        $statusText = 'En attente';
        $bg = '#e5e7eb';
    }
        
    $html .= "
        <li style=\"background:$bg;\">
            <strong>Conversion de fichier JSON vers Excel</strong><br>
            <em>$statusText</em><br>
            Temps total estimé pour la conversion vers Excel:
                <strong>$totalEstimatedConversionTime</strong>
        </li>
    ";

    $response['fileStatusHtml'] = $html;
}

// Output JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
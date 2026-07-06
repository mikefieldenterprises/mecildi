<?php
require_once __DIR__ . '/bootstrap.php';
set_time_limit(0);
ignore_user_abort(true);

/*******************************************************
 * CONFIG FOR EXCEL CREATION
 *******************************************************/
ini_set('memory_limit', '2G');
if (ob_get_level()) ob_end_clean();
ini_set('zlib.output_compression', 0);
ini_set('output_buffering', 'off');




/*******************************************
 * INITIAL SETUP FOR CRAWL AND JSON CREATION
 *******************************************/
$statusFile = __DIR__ . '/process.status';
$downloadPathFile = __DIR__ . '/process.downloadpath';
$pidFile    = __DIR__ . '/process.pid';
$inputDir  = __DIR__ . '/temp-input';
$outputDir = __DIR__ . '/temp-output-raw';
$outputDirFinal = __DIR__ . '/temp-output-final';
$startTimeFile = __DIR__ . '/process.starttime';
$accumFile = __DIR__ . '/process.accumulated';


// Register PID + mark as running
file_put_contents($pidFile, getmypid(), LOCK_EX);
file_put_contents($statusFile, 'running', LOCK_EX);

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}




/*******************************************
 * MAIN WORKER LOOP
 * 
 * Load all files in the /temp-input/ folder
 * For each file, break it up into batches of $PARALLEL_BATCH_SIZE
 * For each batch, call processBatch() in functions/batch-processor.php
 * After all batches, convert to Excel
 * If in debug mode, skip directly to Excel conversion
 *******************************************/
try {
    
    logger_logCrawler("Processus en arrière-plan démarré");
    
    if (LOG_ALL_LANG_VALUES) {
        logger_logCrawler("Journalisation activée pour les valeurs HrefLang et Lang=");
    }
    
    $DEBUG_CREATE_EXCEL_ONLY = $config->debug['create_excel_only'];
    $PARALLEL_BATCH_SIZE = $config->app['parallel_batch_size'];
    
    logger_logCrawler("MECILDI Version ".APP_VERSION);

    
    // Get all .txt files in temp-input
    $inputFiles = glob($inputDir . '/*.txt');
    
    if ( !$DEBUG_CREATE_EXCEL_ONLY ) {
        
        foreach ($inputFiles as $inputFile) {
        
            $baseName = basename($inputFile);
            $outputFile = $outputDir . '/' . preg_replace('/\.txt$/i', '-.txt', $baseName);
        
            // Get DB progress for this file
            $row = db_getDataProgress($config, $baseName);
            $lastProcessedLine = 0;
                
            if ($row && isset($row['LAST_LINE_PROCESSED'])) {
                $lastProcessedLine = (int)$row['LAST_LINE_PROCESSED'];
            }
        
            $in  = fopen($inputFile, 'r');
            $out = fopen($outputFile, 'a');
        
            if (!$in || !$out) {
                if ($in) fclose($in);
                if ($out) fclose($out);
                continue;
            }
                
            $totalLines = 0;
            $lineNumber = 0;
                
            // Count lines efficiently
            $totalLines = count(file($inputFile, FILE_SKIP_EMPTY_LINES));
                
            if ($lastProcessedLine >= $totalLines && $totalLines > 0) {
                continue;
            }
                
            $batch = [];
            $batchLineNumbers = [];
                
            while (($line = fgets($in)) !== false) {
                
                if (shouldStop()) {
                    break; // exit file loop immediately
                }
            
                $lineNumber++;
                
                // Skip lines already processed
                if ($lineNumber <= $lastProcessedLine) {
                    continue;
                }
                
                // Skip empty lines
                $domain = trim($line);
                if ($domain === '') {
                    continue;
                }
                
                $batch[] = $domain;
                $batchLineNumbers[] = $lineNumber;
                
                // When batch is full, process it
                if (count($batch) >= $PARALLEL_BATCH_SIZE) {
        
                    if (!processBatch($config, $batch, $batchLineNumbers, $baseName, $totalLines, $out)) {
                        break; // exit file loop immediately
                    }
        
                    $batch = [];
                    $batchLineNumbers = [];
                }
            }
                
            // Process remaining items
            if (!empty($batch) && !shouldStop()) {
                processBatch($config, $batch, $batchLineNumbers, $baseName, $totalLines, $out);
            }      
        
            fclose($in);
            fclose($out);
        }
    
        logger_logCrawler("Crawl terminé");
    
    } else {
        
        logger_logCrawler("Mode débogage: le robot d’exploration est ignoré et le traitement JSON est traité directement. Pour désactiver le mode débogage, modifiez la valeur de DEBUG_CREATE_EXCEL_ONLY dans config.php.");
    
    }
        
    logger_logCrawler("Début de la conversion de JSON vers Excel");
    
    $downloadPath = convertAllJsonFilesToExcel( $config, $outputDir, $outputDirFinal );

    file_put_contents($downloadPathFile, $downloadPath, LOCK_EX);
    
    logger_logCrawler("Conversion de JSON en Excel terminée");
    logger_logCrawler("Download Filename | /custom-apps/mecildi/temp-output-final/".$downloadPath);
    logger_logCrawler("Processus en arrière-plan terminé");

} finally {
    // Mark worker as stopped
    file_put_contents($statusFile, 'stopped', LOCK_EX);
    
    // Update process.accumulated to keep track of total elapsed time
    $startTime = (int)file_get_contents($startTimeFile);
    $accumulated = (int)file_get_contents($accumFile) ?: 0;
    $currentRun = time() - $startTime;   // seconds for this run
    if ($currentRun < 0) $currentRun = 0;
    $accumulated += $currentRun;
    file_put_contents($accumFile, (string)$accumulated, LOCK_EX);
    
}




?>
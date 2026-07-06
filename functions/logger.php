<?php

function logger_logError($msg) {
    logger_logMsg("ERROR", $msg);
}

function logger_logDebug($msg) {
    logger_logMsg("DEBUG", $msg);
}

function logger_logInfo($msg) {
    logger_logMsg("INFO", $msg);
}

function logger_logWarning($msg) {
    logger_logMsg("WARN", $msg);
}

function logger_logMsg($type, $msg) {
    $dir = "./logs";
    
    // Check if the logs directory exists; if not, create it
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Opening with 'a' mode will automatically create log.txt if it doesn't exist
    $myfile = fopen($dir . "/log.txt", "a") or die("Unable to open file!");
    $txt = utils_getCurrentDateTime() . " | [$type] | $msg\n";
    fwrite($myfile, $txt);
    fclose($myfile);
}

function logger_logHrefLang($domain, $vals) {
    $myfile = fopen("./temp-output-final/log-hreflang.txt", "a") or die("Unable to open file!");
    $txt = $domain . "|$vals\n";
    fwrite($myfile, $txt);
    fclose($myfile);
}

function logger_logLangEquals($domain, $vals) {
    $myfile = fopen("./temp-output-final/log-lang-equals.txt", "a") or die("Unable to open file!");
    $txt = $domain . "|$vals\n";
    fwrite($myfile, $txt);
    fclose($myfile);
}

function logger_rollLogFiles() {
    logger_clearCrawlerLog();
    logger_renameMainLog();
}

function logger_renameMainLog() {
    $f = "./logs/log.txt";
    if (!file_exists($f)) return false;
    $n = "./logs/log-" . date("Ymd_His") . ".txt";
    return rename($f, $n) ? $n : false;
}

function logger_logCrawler($msg) {
    $dir = "./logs";
    
    // Check if the directory exists; if not, create it with 0755 permissions
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Opening with 'a' automatically creates the file if it doesn't exist
    $myfile = fopen($dir . "/log-crawler.txt", "a") or die("Unable to open file!");
    $txt = utils_getCurrentDateTime() . " | $msg\n";
    fwrite($myfile, $txt);
    fclose($myfile);
    
    logger_logInfo($msg); // Also adds a line to the regular log file
}

function logger_clearCrawlerLog() {
    $file = "./logs/log-crawler.txt";
    $myfile = fopen($file, "w");
    if ($myfile === false) {
        return false;
    }
    fclose($myfile);
    return true;
}

?>
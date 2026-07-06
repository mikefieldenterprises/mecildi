<?php

function db_getAllLangCodes(AppConfig $config) {
    $DB_SERVER = $config->database['host'];
    $DB_USER = $config->database['user'];
    $DB_PWD = $config->database['password'];
    $DB_NAME = $config->database['name'];
    $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PWD, $DB_NAME);
    if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
    }
    $sql = "SELECT * FROM CODE_LANG ORDER BY CASE WHEN CODE_LANG_ISO3 IS NULL OR CODE_LANG_ISO3 = '' THEN 1 ELSE 0 END, CODE_LANG_ISO3, CODE_LANG_NAME, CODE_LANG_GOOT";
    $result = $conn->query($sql);
    $conn->close();
    return $result;
}


function db_insertLangDetectLog(AppConfig $config, $numchars) {
    $outvar = null;
    $DB_SERVER = $config->database['host'];
    $DB_USER = $config->database['user'];
    $DB_PWD = $config->database['password'];
    $DB_NAME = $config->database['name'];
    $LANGDETECT_AUTH_KEY_OWNER = $config->langdetect['auth_key_owner'];
    $LANGDETECT_SERVICE_NAME = $config->langdetect['service_name'];
    $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PWD, $DB_NAME);
    if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
    }
    $numchars = htmlspecialchars(trim($numchars));
    $sql = "INSERT INTO LOG_LANG_DETECTOR (SERVICE_NAME, AUTH_KEY_OWNER, NUM_CHARS_SENT) VALUES ('".$LANGDETECT_SERVICE_NAME."','".$LANGDETECT_AUTH_KEY_OWNER."', '".$numchars."')";
    if ($conn->query($sql) === TRUE) {
      logger_logInfo("New record created successfully in LOG_LANG_DETECTOR");
    } else {
      logger_logError("Error: " . $sql . "<br>" . $conn->error);
    }
    $conn->close();
    return $outvar;    
}


function db_updateDataProgress(AppConfig $config, $filename, $fileTotal, $fileProcessed)
{
    $DB_SERVER = $config->database['host'];
    $DB_USER = $config->database['user'];
    $DB_PWD = $config->database['password'];
    $DB_NAME = $config->database['name'];
    try {
        // Create PDO connection
        $dsn = "mysql:host=$DB_SERVER;dbname=$DB_NAME;charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PWD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Check if row exists
        $sql = "SELECT PROGRESS_ID FROM DATA_PROGRESS WHERE FILENAME = :filename LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':filename' => $filename]);

        if ($stmt->rowCount() > 0) {
            // Row exists — update it
            $updateSql = "
                UPDATE DATA_PROGRESS
                SET TOTAL_LINES = :total,
                    LAST_LINE_PROCESSED = GREATEST(LAST_LINE_PROCESSED + 0, :processed + 0),
                    MODIFIED_DATE = CURRENT_TIMESTAMP
                WHERE FILENAME = :filename
            ";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':total'     => $fileTotal,
                ':processed' => (int)$fileProcessed,
                ':filename'  => $filename
            ]);

        } else {
            // Row does NOT exist — insert new row
            $insertSql = "
                INSERT INTO DATA_PROGRESS (FILENAME, TOTAL_LINES, LAST_LINE_PROCESSED, MODIFIED_DATE)
                VALUES (:filename, :total, :processed, CURRENT_TIMESTAMP)
            ";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':filename'  => $filename,
                ':total'     => $fileTotal,
                ':processed' => (int)$fileProcessed
            ]);
        }

        return true; // success

    } catch (PDOException $e) {
        // Handle DB errors (log them in real applications)
        error_log("DB Error: " . $e->getMessage());
        return false;
    }
}

function db_clearDataProgress(AppConfig $config)
{
    $DB_SERVER = $config->database['host'];
    $DB_USER = $config->database['user'];
    $DB_PWD = $config->database['password'];
    $DB_NAME = $config->database['name'];
    try {
        // Create PDO connection
        $dsn = "mysql:host=$DB_SERVER;dbname=$DB_NAME;charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PWD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Clear all data
        // TRUNCATE is faster and resets AUTO_INCREMENT
        $sql = "TRUNCATE TABLE DATA_PROGRESS";
        $pdo->exec($sql);

        return true;

    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        return false;
    }
}

function db_getDataProgress(AppConfig $config, $filename)
{
    $DB_SERVER = $config->database['host'];
    $DB_USER = $config->database['user'];
    $DB_PWD = $config->database['password'];
    $DB_NAME = $config->database['name'];

    try {
        // Create PDO connection
        $dsn = "mysql:host=$DB_SERVER;dbname=$DB_NAME;charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PWD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Prepare and execute SELECT query
        $sql = "SELECT PROGRESS_ID, FILENAME, TOTAL_LINES, LAST_LINE_PROCESSED, MODIFIED_DATE
                FROM DATA_PROGRESS
                WHERE FILENAME LIKE :filename
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([':filename' => $filename]);

        // Fetch row (if any)
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;

    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Creates and returns a PDO connection using the AppConfig object.
 * * @param AppConfig $config
 * @param string $statusFile Path to the status file for error reporting
 * @return PDO
 */
function db_connect(AppConfig $config, $statusFile) {
    $DB_SERVER = $config->database['host'];
    $DB_USER   = $config->database['user'];
    $DB_PWD    = $config->database['password'];
    $DB_NAME   = $config->database['name'];

    try {
        $pdo = new PDO(
            "mysql:host=$DB_SERVER;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER,
            $DB_PWD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Good for background workers to prevent hanging
                PDO::ATTR_TIMEOUT => 30, 
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Handle the status file update before dying
        if (file_exists($statusFile)) {
            file_put_contents($statusFile, 'stopped', LOCK_EX);
        }
        die("Database connection failed: " . $e->getMessage());
    }
}


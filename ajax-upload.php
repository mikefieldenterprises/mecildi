<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('max_input_time', '600');

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false];

/* ----------------------------
   CONFIG
---------------------------- */

$dirs = [
    __DIR__ . '/temp-input/',
    __DIR__ . '/temp-output-raw/',
    __DIR__ . '/temp-output-final/'
];

$uploadDir = __DIR__ . '/temp-input/';

$maxFileSize = 2 * 1024 * 1024; // 2MB

$allowedExtensions = ['txt', 'zip'];

$allowedMimeTypes = [
    'text/plain',
    'text/utf8',
    'text/ascii'
];

/* ----------------------------
   INIT DIRECTORIES
---------------------------- */

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur (création répertoire).']);
            exit;
        }
    }
}

/* ----------------------------
   RESET DIRECTORIES
---------------------------- */

foreach ($dirs as $dir) {
    foreach (glob($dir . '*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

/* ----------------------------
   RESET STATE
---------------------------- */

db_clearDataProgress($config);
logger_rollLogFiles();

@file_put_contents(__DIR__ . '/process.starttime', '', LOCK_EX);
@file_put_contents(__DIR__ . '/process.stoptime', '', LOCK_EX);
@file_put_contents(__DIR__ . '/process.accumulated', '0', LOCK_EX);
@file_put_contents(__DIR__ . '/process.downloadpath', '', LOCK_EX);

/* ----------------------------
   VALIDATE INPUT
---------------------------- */

if (
    !isset($_FILES['uploaded_files']) ||
    !is_array($_FILES['uploaded_files']['tmp_name'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);

/* ----------------------------
   ZIP CLEANER (FILTER RULES)
---------------------------- */

function shouldKeepZipEntry(string $name): bool
{
    $base = basename($name);

    // macOS
    if ($base === '.DS_Store') return false;
    if (str_starts_with($name, '__MACOSX/')) return false;

    // Windows
    if ($base === 'Thumbs.db') return false;
    if (str_starts_with($base, '~$')) return false;
    if ($base === 'desktop.ini') return false;

    // directories
    if (str_ends_with($name, '/')) return false;

    // only txt files allowed
    return strtolower(pathinfo($base, PATHINFO_EXTENSION)) === 'txt';
}

/* ----------------------------
   UPLOAD LOOP
---------------------------- */

foreach ($_FILES['uploaded_files']['tmp_name'] as $i => $tmpName) {

    if ($_FILES['uploaded_files']['error'][$i] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Erreur upload.']);
        exit;
    }

    if ($_FILES['uploaded_files']['size'][$i] > $maxFileSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux.']);
        exit;
    }

    if (!is_uploaded_file($tmpName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Fichier invalide.']);
        exit;
    }

    $originalName = $_FILES['uploaded_files']['name'][$i];
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($originalName));
    $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Seuls .txt ou .zip autorisés.']);
        exit;
    }

    /* MIME check only for TXT */
    if ($ext === 'txt') {
        $mime = $finfo->file($tmpName);
        if (!in_array($mime, $allowedMimeTypes, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'MIME invalide.']);
            exit;
        }
    }

    $destination = $uploadDir . $safeName;

    if (file_exists($destination)) {
        $safeName = pathinfo($safeName, PATHINFO_FILENAME)
            . '_' . uniqid('', true)
            . '.' . $ext;

        $destination = $uploadDir . $safeName;
    }

    if (!move_uploaded_file($tmpName, $destination)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Impossible de sauvegarder.']);
        exit;
    }

    chmod($destination, 0644);

    /* ----------------------------
       ZIP EXTRACTION (FILTERED)
    ---------------------------- */

    if ($ext === 'zip') {

        $zip = new ZipArchive();

        if ($zip->open($destination) === TRUE) {

            for ($i = 0; $i < $zip->numFiles; $i++) {

                $entry = $zip->getNameIndex($i);

                if (!shouldKeepZipEntry($entry)) {
                    continue;
                }

                $zip->extractTo($uploadDir, $entry);
            }

            $zip->close();
            unlink($destination);

        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ZIP invalide.']);
            exit;
        }
    }
}

/* ----------------------------
   PROCESS TXT FILES (ONCE)
---------------------------- */

$files = glob($uploadDir . '*.txt') ?: [];

foreach ($files as $file) {

    $safeName = basename($file);

    $lines = 0;
    $fh = fopen($file, 'r');

    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            if (trim($line) !== '') {
                $lines++;
            }
        }
        fclose($fh);
    }

    db_updateDataProgress($config, $safeName, $lines, 0);
}

/* ----------------------------
   SUCCESS
---------------------------- */

echo json_encode(['success' => true]);
exit;
?>
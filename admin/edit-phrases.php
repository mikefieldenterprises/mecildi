<?php
require_once '../bootstrap.php';

// Map the 'file' parameter to actual file paths
$fileMap = [
    'construction' => __DIR__ . '/../data/under_construction_phrases.txt',
    'hosting'      => __DIR__ . '/../data/hosting_provider_phrases.txt'
];

$fileKey = $_GET['file'] ?? '';

// Validation: Ensure the requested file is one of our allowed keys
if (!array_key_exists($fileKey, $fileMap)) {
    die("Invalid file requested. Return to <a href='index.php'>Dashboard</a>.");
}

$filePath = $fileMap[$fileKey];
$niceName = ($fileKey === 'construction') ? 'Under Construction Phrases' : 'Hosting Provider Phrases';

$message = '';
$messageClass = '';

// Handle the Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phrase_content'])) {
    if (is_writable($filePath)) {
        // We allow empty content here in case you want to clear the list
        file_put_contents($filePath, $_POST['phrase_content'], LOCK_EX);
        $message = "File updated successfully!";
        $messageClass = "alert-success";
    } else {
        $message = "Error: File is not writable. Check permissions for " . basename($filePath);
        $messageClass = "alert-danger";
    }
}

// Read the current content
$currentContent = file_exists($filePath) ? file_get_contents($filePath) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit <?php echo $niceName; ?> | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .phrase-editor {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            height: 65vh;
            white-space: pre;
            overflow-wrap: normal;
            overflow-x: auto;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Edit Phrases</li>
                        </ol>
                    </nav>
                    <h2>📝 <?php echo $niceName; ?></h2>
                </div>
                <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $messageClass; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-white text-muted small">
                    Path: <code>data/<?php echo basename($filePath); ?></code>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <textarea 
                                name="phrase_content" 
                                id="phrase_content" 
                                class="form-control phrase-editor"
                                spellcheck="false"><?php echo htmlspecialchars($currentContent); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Enter one phrase per line.</span>
                            <div>
                                <button type="reset" class="btn btn-light me-2">Reset</button>
                                <button type="submit" class="btn btn-primary px-5">Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
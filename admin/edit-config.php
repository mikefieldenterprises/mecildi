<?php
require_once '../bootstrap.php';

// Path to the actual config file
$configFile = __DIR__ . '/../config/app.ini';
$message = '';
$messageClass = '';

// Handle the Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['config_content'])) {
    // Basic security: don't allow saving if the string is empty (prevents accidental wipes)
    if (!empty(trim($_POST['config_content']))) {
        if (is_writable($configFile)) {
            file_put_contents($configFile, $_POST['config_content'], LOCK_EX);
            $message = "Configuration updated successfully!";
            $messageClass = "alert-success";
        } else {
            $message = "Error: The file is not writable. Check server permissions.";
            $messageClass = "alert-danger";
        }
    } else {
        $message = "Error: Content cannot be empty.";
        $messageClass = "alert-danger";
    }
}

// Read the current content
$currentContent = file_exists($configFile) ? file_get_contents($configFile) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit app.ini | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .config-editor {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            background-color: #ffffff;
            color: #212529;
            height: 70vh;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>⚙️ Edit Configuration (app.ini)</h2>
                <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $messageClass; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="config_content" class="form-label text-muted">Raw INI Content</label>
                            <textarea 
                                name="config_content" 
                                id="config_content" 
                                class="form-control config-editor"
                                spellcheck="false"><?php echo htmlspecialchars($currentContent); ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-light me-md-2">Reset Changes</button>
                            <button type="submit" class="btn btn-primary px-5">Save Configuration</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-3 text-muted small">
                <strong>Note:</strong> Be careful with syntax. Ensure section headers like <code>[database]</code> remain intact. 
                Invalid INI syntax may cause the application to crash.
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
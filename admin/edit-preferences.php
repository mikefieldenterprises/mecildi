<?php
require_once '../bootstrap.php';

// Path to the actual preferences file
$prefFile = __DIR__ . '/../config/preferences.json';
$message = '';
$messageClass = '';

// Handle the Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['json_content'])) {
    $updatedContent = $_POST['json_content'];
    
    // Basic security: don't allow saving if empty
    if (!empty(trim($updatedContent))) {
        // Validate JSON before saving
        $decoded = json_decode($updatedContent, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_writable($prefFile)) {
                // Save with pretty print to keep it readable
                file_put_contents($prefFile, json_encode($decoded, JSON_PRETTY_PRINT), LOCK_EX);
                $message = "Preferences updated successfully!";
                $messageClass = "alert-success";
            } else {
                $message = "Error: The file is not writable. Check server permissions.";
                $messageClass = "alert-danger";
            }
        } else {
            $message = "Error: Invalid JSON syntax (" . json_last_error_msg() . "). Changes not saved.";
            $messageClass = "alert-danger";
        }
    } else {
        $message = "Error: Content cannot be empty.";
        $messageClass = "alert-danger";
    }
}

// Read the current content
$currentContent = file_exists($prefFile) ? file_get_contents($prefFile) : '{"simplified_mode": false}';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit preferences.json | Admin</title>
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
                <h2>🛠️ Edit Preferences (preferences.json)</h2>
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
                            <label for="json_content" class="form-label text-muted">Raw JSON Content</label>
                            <textarea 
                                name="json_content" 
                                id="json_content" 
                                class="form-control config-editor"
                                spellcheck="false"><?php echo htmlspecialchars($currentContent); ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-light me-md-2">Reset Changes</button>
                            <button type="submit" class="btn btn-primary px-5">Save Preferences</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-3 text-muted small">
                <strong>Note:</strong> JSON requires double quotes for all keys and string values. 
                Ensure <code>simplified_mode</code> is set to <code>true</code> or <code>false</code> (no quotes for booleans).
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
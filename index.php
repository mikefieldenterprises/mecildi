<?php

/**
 * Homepage for MECILDI Language Detector app
 * Allows user to upload up to 100 .txt files with one domain per line
 * Posts to process-all-files.php
 */

require_once __DIR__ . '/bootstrap.php';

// Determine toggle state from the constant defined in bootstrap
$checkedAttribute = (defined('SIMPLIFIED_MODE') && SIMPLIFIED_MODE) ? 'checked' : '';
$logLangValuesCheckedAttribute = (defined('LOG_ALL_LANG_VALUES') && LOG_ALL_LANG_VALUES) ? 'checked' : '';

?>
<html>
<head>
    <style>
        /* ------------------------
           General
           ------------------------ */
        body {
            font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; }
        
        /* Smooth subtle animation */
        * {
            transition: all 0.15s ease;
        }
        
        /* ------------------------
           Header
           ------------------------ */
        .header {
            width: 90%;
            max-width: 1200px;
            margin: 25px auto;
            padding: 30px 30px;
            background: #2563eb; /* Material blue */
            color: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .header h1 {
            margin: 0;
            font-size: 1.9em;
            font-weight: 600;
            letter-spacing: .3px;
        }
        
        /* ------------------------
           Panels
           ------------------------ */
        .panel {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        
        .panel h2 {
            margin-top: 0;
            color: #111827;
            font-size: 1.45em;
            font-weight: 600;
            padding-bottom: 8px;
            border-bottom: 2px solid #2563eb;
        }
        
        /* Panel subtle hover lift */
        .panel:hover {
            box-shadow: 0 4px 18px rgba(0,0,0,0.10);
        }
        
        /* ------------------------
           Buttons (Material style)
           ------------------------ */
        .button-58 {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 12px 22px;
            font-size: 1em;
            border-radius: 6px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        
        .button-58:hover {
            background: #1e4fc3;
            box-shadow: 0 4px 10px rgba(0,0,0,0.18);
        }
        
        .button-59 {
            background: #16a34a; /* green */
            color: #fff;
            border: none;
            padding: 12px 22px;
            font-size: 1em;
            border-radius: 6px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        
        .button-59:hover {
            background: #12803a; /* darker green */
            box-shadow: 0 4px 10px rgba(0,0,0,0.18);
        }
        
        /* ------------------------
           File List
           ------------------------ */
        #file-list ul {
            padding-left: 20px;
        }
        #file-list li {
            margin-bottom: 6px;
        }
        
        /* ------------------------
           Status List
           ------------------------ */
        .status-list {
            max-height: 450px;
            overflow-y: auto;
            padding-left: 0;
            list-style: none;
        }
        
        .status-list li {
            margin-bottom: 12px;
            padding: 14px 16px;
            border-radius: 8px;
            border: none;
            background: #f9fafb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        .status-list li strong {
            color: #111827;
        }
        
        /* Better colors for done / in-progress rows */
        .status-list li[style*="c6f5c6"] {
            background: #d1fae5 !important; /* green-200 */
        }
        .status-list li[style*="fff7c2"] {
            background: #fef3c7 !important; /* amber-200 */
        }
        
        /* ------------------------
           Alerts
           ------------------------ */
        .alert-amber {
            padding: 20px;
            background: #fef3c7;
            border-left: 6px solid #fccf19;
            border-radius: 10px;
            color: #14532d;
            font-weight: 600;
            margin-bottom: 20px;
            width: 96%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .alert-pink {
            padding: 20px;
            background: #ffe4e6;
            border-left: 6px solid #e11d48;
            border-radius: 10px;
            color: #9f1239;
            font-weight: 600;
            margin-bottom: 20px;
            width: 96%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .alert-green {
            padding: 20px;
            background: #dcfce7;            /* green-200 */
            border-left: 6px solid #16a34a;  /* green-600 */
            border-radius: 10px;
            color: #14532d;
            font-weight: 600;
            margin-bottom: 20px;
            width: 96%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        
        /* ------------------------
           Footer
           ------------------------ */
        footer {
            width: 100%;
            background: #111827;
            color: #e5e7eb;
            text-align: center;
            padding: 18px 0;
            margin-top: 40px;
            font-size: 0.92em;
            letter-spacing: .2px;
        }
        
        /* ---------------------------------------------------------
           Updated Google Analytics Style: CHOOSE FILES button
           --------------------------------------------------------- */
        
        .file-upload-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .file-upload-label {
            background: #3b82f6;       /* lighter blue than upload button */
            color: white;
            padding: 10px 18px;        /* slightly smaller */
            font-size: 0.85rem;        /* smaller than Upload button */
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.12);
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.5px;     /* ALL CAPS look nicer */
            text-transform: uppercase; /* requested */
        }
        
        .file-upload-label:hover {
            background: #2563eb;       /* matches the main color when hovered */
        }
        
        .file-upload-input {
            position: absolute;
            top: 0; left: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        /* File list spacing */
        #file-list {
            margin-top: 12px;
            font-size: 0.95rem;
        }

        /* ---------------------------------------------------------
           Fake Per-File Upload Progress Bars (Material style)
           --------------------------------------------------------- */
        
        .file-progress {
            width: 100%;
            background: #e5e7eb;
            border-radius: 6px;
            height: 8px;
            margin-top: 4px;
            overflow: hidden;
        }
        
        .file-progress-bar {
            height: 100%;
            width: 0%;
            background: #3b82f6;
            border-radius: 6px;
            transition: width 1s ease;
        }

        /* Grid layout for file list */
        .file-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        
        .file-grid-column {
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            padding: 10px;
        }
        
        .file-grid-column h3 {
            margin: 0 0 8px 0;
            padding: 6px;
            font-size: 15px;
            background: #f5f6fa;
            border-radius: 4px;
            text-align: center;
        }
        
        .file-grid-column ul {
            list-style: none;
            padding-left: 10px;
        }
        
        .file-grid-column li {
            border-bottom: 1px solid #eee;
            padding: 3px 0;
            margin-left: -17px;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        /* ---  Toggle Switch --- */
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
        }
        
        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; /* OFF Color */
            transition: .3s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        /* ON State */
        input:checked + .slider {
            background-color: #16a34a; /* Green when ON */
        }
        
        input:checked + .slider:before {
            transform: translateX(18px);
        }

        /* --- Info Tooltip Dot --- */
        .info-tooltip {
            position: relative;
            cursor: help;
            color: #2563eb;
            font-weight: bold;
            border: 1.5px solid #2563eb;
            border-radius: 50%;
            width: 20px;  /* Fixed width */
            height: 20px; /* Fixed height */
            display: inline-flex; /* Use flex to center the '?' */
            align-items: center;
            justify-content: center;
            font-size: 13px;
            background-color: #f0f7ff; /* Light blue background to make it easier to hit */
            flex-shrink: 0; /* Prevents it from squishing on small screens */
            user-select: none; /* Prevents selecting the '?' text */
        }
        
        .info-tooltip:hover {
            background-color: #2563eb;
            color: #fff;
        }
        
        /* ------------------------
           Settings Modal
           ------------------------ */
        
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 9999;
            inset: 0;
            background: rgba(0,0,0,0.45);
            align-items: center;
            justify-content: center;
        }
        
        .modal {
            background: #fff;
            width: 90%;
            max-width: 800px;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            animation: modalFade .15s ease;
        }
        
        @keyframes modalFade {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        .modal-close:hover {
            color: #111827;
        }
        
        .settings-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .settings-row:last-child {
            border-bottom: none;
        }
        
        .settings-label {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .settings-label small {
            color: #6b7280;
            font-size: 0.85rem;
        }        
        
    </style>

<script language="javascript"><!--
    function validateForm() {
        const filename = document.getElementById("uploaded_files").value;
        const ext = filename.split('.').pop().toLowerCase();
        if (!filename) {
            alert("L'erreur suivante s'est produite :\n\n- Aucun fichier sélectionné.");
            return false;
        }
        if (!filename || (ext != "txt" && ext != "zip")) {
            alert("L'erreur suivante s'est produite :\n\n- Le fichier doit être de type .txt ou .zip");
            return false;
        }
        return true;
    }

    function confirmUpload() {
        if (!validateForm()) return false;
        return confirm("Veuillez confirmer :\n\n- Le téléversement peut prendre jusqu'à une minute.\n- Vous êtes sur le point d'effacer toutes les données de session précédentes.\n\nSouhaitez-vous continuer ?");
    }    
//-->
</script>
</head>

<body>

    <div class="header">
        <h1>MECILDI - Outil de mesure de sites web multilingues</h1>
    </div>
    <form name="main" id="main" enctype="multipart/form-data" onsubmit="return false;">
        <div class="panel">
            <h2>Téléverser un nouveau jeu de fichiers</h2>
            <div id="upload-alert"></div>
            <p>Téléversez un ou plusieurs fichiers texte ci-dessous, avec un seul domaine par ligne. L’extension du fichier doit être .txt ou .zip.</p>
            <p>REMARQUE : Cela effacera toutes les données de traitement précédentes.</p>
                <div class="file-upload-wrapper">
                    <label for="uploaded_files" class="file-upload-label">Choisir des fichiers</label>
                    <input type="file" name="uploaded_files[]" id="uploaded_files" class="file-upload-input" multiple>
                </div>
                <div id="file-list"></div>
                <button class="button-58" id="upload-btn" type="button">TÉLÉVERSER LES FICHIERS</button>
        </div>
    </form>

    <div class="panel">
        <h2>Statut du traitement</h2>
        <div id="process-status" style="font-weight:600;">
            Chargement du statut…
        </div>
    </div>
    
    <div class="panel">
        <h2>Démarrer et arrêter le traitement</h2>
        <div id="action-buttons" style="font-weight:600;">
            Chargement des actions…
        </div>
    </div>
    
    <div class="panel">
        <h2>Visionneuse de journaux</h2>
        <div id="log-viewer" style="font-weight:600;">
            Chargement de la visionneuse…
        </div>
    </div>
    
    <div class="panel">
        <h2>Fichiers à traiter</h2>
        <!-- SCROLL CONTAINER -->
        <div class="status-list" id="file-status" style="font-weight:600;">
            Chargement des fichiers…
        </div>
    </div>
   
    <div class="panel">
        <h2>Arrêt forcé</h2>
        <div id="force-stop" style="font-weight:600;">
            <a href="./ajax-stop-processing.php" target="_new">
                <button class="button-58" id="submit" type="submit" style="background:#dc2626;">ARR&Ecirc;T FORCÉ</button>
            </a>
        </div>
    </div>


    <div class="modal-overlay" id="settings-modal">
        <div class="modal">
            
            <div class="modal-header">
                <h3>Paramètres</h3>
                <button class="modal-close" id="close-settings">&times;</button>
            </div>
    
            <div class="settings-row">
                <div class="settings-label">
                    <strong>Mode Simplifié</strong>
                    <small>Ne génère pas le fichier MATRIX_SUMMARY.xlsx.<br/>
                    Masque les 10 premières lignes de résumé dans les fichiers Excel individuels.</small>
                </div>
    
                <label class="switch">
                    <input type="checkbox" id="toggle-simplified-mode" <?php echo $checkedAttribute; ?>>
                    <span class="slider"></span>
                </label>
            </div>
            
            <!-- Log Hreflang / Lang Values -->
            <div class="settings-row">
                <div class="settings-label">
                    <strong>Journaliser HrefLang &amp; Lang=</strong>
                    <small>
                        Enregistrer toutes les valeurs d’attribut hreflang et lang= détectées pendant le traitement.
                    </small>
                </div>
    
                <label class="switch">
                    <input type="checkbox" id="toggle-log-lang-values" <?php echo $logLangValuesCheckedAttribute; ?>>
                    <span class="slider"></span>
                </label>
            </div>            
    
        </div>
    </div>

<script>
    const fileInput = document.getElementById('uploaded_files');
    const fileList = document.getElementById('file-list');

    fileInput.addEventListener('change', () => {
        const files = Array.from(fileInput.files);
        if (files.length > 100) {
            alert("L'erreur suivante s'est produite :\n\n- Vous pouvez sélectionner un maximum de 100 fichiers.");
            fileInput.value = '';
            fileList.innerHTML = '';
            return;
        }
        // Build multi-column file list (25 per column)
        const maxPerColumn = 25;
        let columns = [];
        for (let i = 0; i < files.length; i += maxPerColumn) {
            columns.push(files.slice(i, i + maxPerColumn));
        }
        
        fileList.innerHTML = `
            <div class="file-grid-container">
                ${columns.map((col, i) => `
                    <div class="file-grid-column">
                        <h3>Fichiers ${i + 1}</h3>
                        <ul>
                            ${col.map(f => `
                                <li>
                                    ${f.name}
                                    <div class="file-progress">
                                        <div class="file-progress-bar"></div>
                                    </div>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `).join('')}
            </div>
        `;

    });
    
    function showError(message) {
        const box = document.getElementById('upload-alert');
        box.className = 'alert-pink';
        box.textContent = message;
        box.style.display = 'block';
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function showWarning(message) {
        const box = document.getElementById('upload-alert');
        box.className = 'alert-amber';
        box.textContent = message;
        box.style.display = 'block';
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }    
    
    function showSuccess(message) {
        const box = document.getElementById('upload-alert');
        box.className = 'alert-green';
        box.textContent = message;
        box.style.display = 'block';
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function clearAlert() {
        const box = document.getElementById('upload-alert');
        box.style.display = 'none';
        box.textContent = '';
    }    
    
    function clearUploadUIAfterDelay(delayMs) {
        setTimeout(() => {
            fileInput.value = '';
            fileList.innerHTML = '';
            clearAlert();
        }, delayMs);
    }
    
    function startUpload() {
        clearAlert();
    
        const filesInput = document.getElementById('uploaded_files');
        const files = Array.from(filesInput.files);
    
        if (!files.length) {
            showError("Aucun fichier sélectionné.");
            return;
        }
    
        if (files.length > 100) {
            showError("Vous pouvez sélectionner un maximum de 100 fichiers.");
            return;
        }
    
        for (const file of files) {
            if (!file.name.toLowerCase().endsWith('.txt') && !file.name.toLowerCase().endsWith('.zip')) {
                showError("Tous les fichiers doivent être de type .txt ou .zip.");
                return;
            }
        }
    
        showWarning("Téléversement en cours…");
    
        const formData = new FormData();
        files.forEach(file => formData.append('uploaded_files[]', file));
    
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax-upload.php', true);
    
        xhr.upload.addEventListener('progress', (e) => {
            if (!e.lengthComputable) return;
    
            const percent = Math.round((e.loaded / e.total) * 100);
    
            document.querySelectorAll('.file-progress-bar').forEach(bar => {
                bar.style.width = percent + '%';
            });
        });
    
        xhr.onload = () => {
            if (xhr.status === 200) {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch {
                    showError("Réponse serveur invalide.");
                    return;
                }
    
                if (response.success) {
                    showSuccess("Téléversement terminé avec succès.");
                    updateAppStatus();
                    clearUploadUIAfterDelay(3000);
                } else {
                    showError(response.error || "Erreur lors du téléversement.");
                }
            } else {
                showError("Erreur serveur lors du téléversement.");
            }
        };
    
        xhr.onerror = () => {
            showError("Erreur réseau lors du téléversement.");
        };
    
        xhr.send(formData);
    }


    // Bind submit manually
    document.getElementById('upload-btn').addEventListener('click', function (e) {
        e.preventDefault();
        startUpload();
    });

    
    /* -------------------------------------------------------
       Unified App Status Polling
    ------------------------------------------------------- */
    
    const APP_STATUS_POLL_INTERVAL = 3000;
    let appStatusTimer = null;
    
    function updateAppStatus() {
        fetch('ajax-app-status.php', {
            cache: 'no-store',
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
    
            /* ---------------- Process Status ---------------- */
            const statusEl = document.getElementById('process-status');
            if (statusEl && data.process) {
                if (data.process.running) {
                    statusEl.innerHTML =
                        '<span style="color:#15803d;">🟢 Traitement en cours</span>';
                } else {
                    statusEl.innerHTML =
                        '<span style="color:#6b7280;">⚪ Aucun traitement en cours</span>';
                }
            }
    
            /* ---------------- Action Buttons ---------------- */
            if (data.actionButtonsHtml) {
                const actionsEl = document.getElementById('action-buttons');
                if (actionsEl) {
                    actionsEl.innerHTML = data.actionButtonsHtml;
                }
            }
    
            /* ---------------- Log Viewer ---------------- */
            if (data.logViewerHtml) {
                const logEl = document.getElementById('log-viewer');
                if (logEl) {
                    logEl.innerHTML = data.logViewerHtml;
                }
            }
    
            /* ---------------- File Status ---------------- */
            if (data.fileStatusHtml) {
                const fileEl = document.getElementById('file-status');
                if (fileEl) {
                    fileEl.innerHTML = data.fileStatusHtml;
                }
            }
    
        })
        .catch(() => {
            const statusEl = document.getElementById('process-status');
            if (statusEl) {
                statusEl.innerHTML =
                    '<span style="color:#9f1239;">🔴 Impossible de récupérer le statut</span>';
            }
        });
    }
    
    /* Initial load */
    updateAppStatus();
    
    /* Poll every few seconds */
    appStatusTimer = setInterval(updateAppStatus, APP_STATUS_POLL_INTERVAL);
    
    
    /* -------------------------------------------------------
       Start / Stop Processing (unchanged behavior)
    ------------------------------------------------------- */
    
    document.addEventListener('click', function (e) {
    
        if (e.target.id === 'start-processing' ||
            e.target.id === 'continue-processing') {
    
            fetch('ajax-start-processing.php', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(() => {
                updateAppStatus();
            });
        }
    
        if (e.target.id === 'stop-processing') {
            if (!confirm('Voulez-vous vraiment arrêter le traitement en cours ?')) {
                return;
            }
    
            fetch('ajax-stop-processing.php', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(() => {
                updateAppStatus();
            });
        }
    
    });
    
    /* -------------------------------------------------------
        Preferences Toggle Listener
    ------------------------------------------------------- */
    document.addEventListener('change', function (e) {
        if (e.target.id === 'toggle-simplified-mode') {
            const isEnabled = e.target.checked;
            
            // Create form data to match the expectation of ajax-set-preferences.php
            const formData = new FormData();
            formData.append('key', 'simplified_mode');
            formData.append('value', isEnabled);

            fetch('ajax-set-preferences.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Erreur: ' + data.message);
                    // Revert UI if save failed
                    e.target.checked = !isEnabled;
                }
            })
            .catch(() => {
                alert('Erreur réseau lors de la mise à jour des préférences.');
                e.target.checked = !isEnabled;
            });
        }
    });
    
    /* -------------------------------------------------------
        Preferences Toggle Listener
    ------------------------------------------------------- */
    document.addEventListener('change', function (e) {
        if (e.target.id === 'toggle-log-lang-values') {
            const isEnabled = e.target.checked;
            
            // Create form data to match the expectation of ajax-set-preferences.php
            const formData = new FormData();
            formData.append('key', 'log_all_lang_values');
            formData.append('value', isEnabled);

            fetch('ajax-set-preferences.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Erreur: ' + data.message);
                    // Revert UI if save failed
                    e.target.checked = !isEnabled;
                }
            })
            .catch(() => {
                alert('Erreur réseau lors de la mise à jour des préférences.');
                e.target.checked = !isEnabled;
            });
        }
    });    
    
    /* -------------------------------------------------------
       Settings Modal
    ------------------------------------------------------- */
    
    const settingsModal = document.getElementById('settings-modal');
    
    document.addEventListener('click', function(e) {
    
        if (e.target.id === 'open-settings') {
            e.preventDefault();
            settingsModal.style.display = 'flex';
        }
    
        if (e.target.id === 'close-settings') {
            settingsModal.style.display = 'none';
        }
    
        if (e.target === settingsModal) {
            settingsModal.style.display = 'none';
        }
    });
</script>

<footer>
    &copy; <?=date('Y')?> OBDILCI - MECILDI Outil de mesure de sites web multilingues. Tous droits réservés.<br/>
    Version <?php echo APP_VERSION; ?>
</footer>

</body>
</html>

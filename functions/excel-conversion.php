<?php

// Load libraries
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;



/**
 * HELPER FUNCTIONS FOR CONVERSION TO EXCEL
 */
 
/**
 * Opens all .txt files in /temp-output-raw/ and converts them to 
 * Excel files, saved in /temp-output-final/
 * Creates MATRIX_SUMMARY file and MATRIX_BUNDLE.zip file
 * returns $downloadPath
 */
function convertAllJsonFilesToExcel( AppConfig $config, $rawDir, $finalDir ) {
    
    // Ensure trailing slashes (and remove duplicate ones)
    $rawDir   = rtrim($rawDir, '/\\') . DIRECTORY_SEPARATOR;
    $finalDir = rtrim($finalDir, '/\\') . DIRECTORY_SEPARATOR;
    
    $downloadPath = null;
    if(!file_exists($finalDir)) mkdir($finalDir, 0755, true);
    
    $rawFiles = glob($rawDir . '*.txt');
    $langResult = db_getAllLangCodes($config);
    $all_lang_codes = [];

    if (is_array($langResult)) {
        $all_lang_codes = $langResult;
    } elseif ($langResult instanceof mysqli_result) {
        while ($row = $langResult->fetch_assoc()) {
            $all_lang_codes[] = $row;
        }
    } elseif ($langResult instanceof PDOStatement) {
        $all_lang_codes = $langResult->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $allFilesSummaryData = [];  // Initialize array to store summary data in memory to avoid re-opening files
    $generatedFiles = [];
    
    foreach ($rawFiles as $rawFile) {
        
        // Get the filename without the .txt extension, remove an extra hyphen if present
        $baseNameOnly = pathinfo($rawFile, PATHINFO_FILENAME);
        $baseNameOnly = rtrim($baseNameOnly, '-');
        $filename = $baseNameOnly . '-FINAL.xlsx';    
        $path = $finalDir . $filename;

        logger_logCrawler($baseNameOnly . " | Début de la conversion vers .xlsx" . (SIMPLIFIED_MODE ? " (MODE SIMPLIFIÉ)" : ""));

        // Create speadsheet and set name of tab/worksheet to the filename
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("S");
    
        if (!SIMPLIFIED_MODE) {
            // Create column headers
            applyExcelHeaders($sheet, $all_lang_codes);
        }
    
        // Open the json file and convert it to Excel rows
        $rowNum = SIMPLIFIED_MODE ? 1 : 11; // Start writing on row 1 in simplified mode, or row 11 in normal mode (to allow for the column headers and formulas)
        $handle = fopen($rawFile, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $json = json_decode(trim($line), true);
                if (!$json) continue;
                
                $data = evaluateJsonDataAndConvertToArray($json, $all_lang_codes);
                $sheet->fromArray($data, NULL, 'A' . $rowNum);
                $rowNum++;
            }
            fclose($handle);
        }
    
        if (!SIMPLIFIED_MODE) {
    
            // Create summary values
            applySummaryFormulas($sheet, $rowNum - 1, $all_lang_codes);
            $sheet->setSelectedCell('A1');
    
            // Collect summary data to be used in matrix file
            $allFilesSummaryData[] = getDataForMatrixSummaryAsArray( $baseNameOnly, $sheet );
        
        }
        
        // Save spreadsheet
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $generatedFiles[] = $path;
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        logger_logCrawler($baseNameOnly . " | Conversion du fichier vers .xlsx terminée");

    }
    
    
    if (count($generatedFiles) === 1 && !LOG_ALL_LANG_VALUES) {
        
        // Single file case (.xlsx already saved earlier)
        $downloadPath = basename($generatedFiles[0]);

    } elseif (LOG_ALL_LANG_VALUES || count($generatedFiles) > 1) {
        
        if (!SIMPLIFIED_MODE && count($generatedFiles) > 1) {
            
            // Multi-file output: Summary + Zip
            logger_logCrawler("MATRIX_SUMMARY.xlsx | Début de la création du fichier");
            $summaryFile = createMatrixSummaryExcel($finalDir, $allFilesSummaryData, $all_lang_codes);
            if ($summaryFile) $generatedFiles[] = $summaryFile;
            logger_logCrawler("MATRIX_SUMMARY.xlsx | Création du fichier terminée");
            
        } elseif (SIMPLIFIED_MODE) {
            
            logger_logCrawler("Mode simplifié - création de MATRIX_SUMMARY ignorée.");
            
        }
        
        if (LOG_ALL_LANG_VALUES) {
            logger_logCrawler("Ajout des journaux de HrefLang et Lang= au fichier zip.");
            $hrefLangLog = $finalDir . "log-hreflang.txt";
            $langEqualsLog = $finalDir . "log-lang-equals.txt";
        
            if (file_exists($hrefLangLog)) {
                $generatedFiles[] = $hrefLangLog;
            }
            if (file_exists($langEqualsLog)) {
                $generatedFiles[] = $langEqualsLog;
            }
        }
    
        logger_logCrawler("MATRIX_BUNDLE.zip | Début de la création du fichier");

        $timestamp = date("Ymd_Hi");
        $zipFilename = $finalDir . "MATRIX_BUNDLE-$timestamp.zip";
        $zip = new ZipArchive();
        
        if ($zip->open($zipFilename, ZipArchive::CREATE) === TRUE) {
            foreach ($generatedFiles as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
    
            $downloadPath = basename($zipFilename);

        }

        logger_logCrawler("MATRIX_BUNDLE.zip | Création du fichier terminée");

    }
    
    return $downloadPath;
    
} 
 
/**
 * Creates the complex 10-row header structure required for MECILDI reports
 * Used for the individual Excel files, not the MATRIX_SUMMARY file
 */
function applyExcelHeaders($sheet, $all_lang_codes) {
    // Row 1-6 are placeholders for summary calculations

    $redirectsColumnLetter = "F";
    $hrefLangColumnLetter = "O";
    $numberOfColumnsBeforeLanguages = 18; // Languages start after metadata, start counting at 0 not 1
    $idnColumnLetter = "A";

     
    // Set Metadata Headers (Row 6)
    $metaHeaders1 = [
        1 => 'English', 2 => 'MultiR', 3 => '%Multi', 4 => 'AvgL', 5 => "Number of sites / Valid"
    ];
    
    foreach ($metaHeaders1 as $col => $val) {
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValue($colLetter . '7', $val);
    }
    
    $sheet->setCellValue("{$idnColumnLetter}9",'IDN');
    
    // Set Metadata Headers (Row 10)
    $metaHeaders2 = [
        5 => 'DOMAIN', 6 => 'REDIRECT SITE', 7 => 'ERR', 8 => 'ErrType',
        9 => 'MonoL', 10 => 'NumHrefLangs', 11 => 'GooT',
        12 => 'LikelihoodOfMultiL', 13 => 'Lang=', 14 => 'Lang=Err',
        15 => 'Hreflang=', 16 => 'ModeDetect', 17 => 'OTHERL', 18 => 'NON-ID'
    ];
    
    foreach ($metaHeaders2 as $col => $val) {
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValue($colLetter . '10', $val);
    }
    
    $sheet->setCellValue("{$redirectsColumnLetter}1",'% ERR Group1');
    $sheet->setCellValue("{$redirectsColumnLetter}2",'% ERR Group2');
    $sheet->setCellValue("{$redirectsColumnLetter}3",'% ERR Group3');
    $sheet->setCellValue("{$redirectsColumnLetter}4",'% ERR Group4');
    $sheet->setCellValue("{$redirectsColumnLetter}6",'% ERR Group5');
    $sheet->setCellValue("{$redirectsColumnLetter}7",'% ERR Group6');
    $sheet->setCellValue("{$redirectsColumnLetter}8",'% ERR Group7');
    $sheet->setCellValue("{$redirectsColumnLetter}9",'Total ERR G1>G7');
    
    
    $sheet->setCellValue("{$hrefLangColumnLetter}1",'SUM-MO');
    $sheet->setCellValue("{$hrefLangColumnLetter}2",'SUM-ML');
    $sheet->setCellValue("{$hrefLangColumnLetter}3",'SUM-C');
    $sheet->setCellValue("{$hrefLangColumnLetter}4",'%PAGES');
    $sheet->setCellValue("{$hrefLangColumnLetter}5",'%SITES');

    // Language Headers
    $startCol = $numberOfColumnsBeforeLanguages+1; 
    foreach ($all_lang_codes as $index => $lang) {
        $col = $startCol + $index;
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValue($colLetter . '8', $lang["CODE_LANG_NAME"]);
        $sheet->setCellValue($colLetter . '9', $lang["CODE_LANG_ISO3"]);
        $sheet->setCellValue($colLetter . '10', $lang["CODE_LANG_GOOT"]);
    }

    // Styling
    $sheet->getStyle('A6:ZZ10')->getFont()->setBold(true);
}

/**
 * Processes a single raw JSON line and maps it to Excel columns
 * Used for individual excel files, not for MATRIX_SUMMARY
 */
function evaluateJsonDataAndConvertToArray($json, $all_lang_codes) {

    $multi = $json["multilingual_detected"] ? 1 : 0;
    $err_yes = ($json["response_code"] != 200) ? 1 : 0;
    
    $err_yes = ($json["response_code"]!=200) ? "1" : "";
    $err_type = ($err_yes ? translateErrorCodeForOutput($json) : "");
    $multi = ($json["multilingual_detected"]?"1":"");
    $mono  = ($json["response_code"]==200 && !$multi ? "1" : "");
    $numHrefLangs = getNumberOfHrefLangs($json);
    $gooT = ($json["google_translate_widget"]=="1"?"1":"");
    $lik = ( $json["response_code"] === 200 && isset($json["likelihood_of_multilingualism"]) ) ? $json["likelihood_of_multilingualism"] : "";
    $lang_eq = (!empty($json['first_lang_2'])) ? "1" : "";
    $lang_eq_err = getLangEqualsError($json);
    $href_eq = count($json["hreflangs"])>1?"1":"";
    $href_eq_err = count($json["hreflangs"])==1?"1":"";
    $mode = ($json["response_code"]==200?"Heuristics":"");

    $info=getLangInfoArray($json,$all_lang_codes);
    $other=$info["OTHER_L"];
    $nonid=$info["NON_ID"];
    $langs=$info["ALL_LANGS"];
    
    // Special case: if $nonid=1 and $lang_eq=1, set $nonid to empty string and $lang_eq_err to "X" (this avoids duplicate language counts in final data calculations)
    if ((int)$nonid === 1 && (int)$lang_eq === 1) {
        $nonid = "";
        $lang_eq_err = "X";
    }
    
    $isIDN = (isset($json['url']) && strpos($json['url'], 'xn--') === 0 && isset($err_yes) && $err_yes === "");
    $idnStatus = $isIDN ? 1 : '';
    
    $padding = SIMPLIFIED_MODE ? [] : [$idnStatus, null, null, null]; // $idnStatus plus three empty columns at the front if not in SIMPLIFIED_MODE
    
    $data = [
        $json['url'],
        $json['redirecturl'],
        $err_yes,
        $err_type,
        $mono,
        $numHrefLangs,
        $gooT,
        $lik,
        $lang_eq,
        $lang_eq_err,
        $href_eq,
        $mode,
        $other,
        $nonid
    ];

    $row = array_merge($padding, $data);    // Merge the padding columns, if any
    $row = array_merge($row, $langs); // Merge the lang columns

    return $row;
}

// Outputs an array of numbers formatted exactly like this: {"404","403"}
// eg echo formatArray([404, 403]);
function formatArray($arr) {
    return '{"' . implode('","', array_map('intval', $arr)) . '"}';
}

/**
 * Adds summary formulas at the top of the sheet
 * Used for individual excel files, not for MATRIX_SUMMARY
 */
function applySummaryFormulas($sheet, $highestRow, $all_lang_codes) {
    if ($highestRow < 11) return;
    
    $englishColumnLetter = "BL";
    $numSitesColumnLetter = "E";
    $ERRColumnLetter = "G";
    $monoLColumnLetter = "I";
    $numHrefLangColumnLetter = "J";
    $likelihoodColumnLetter = "L";
    $hrefLangColumnLetter = "O";
    $sumColumnLetter = "P";
    $firstSumProductColumnLetter = "Q";
    $idnColumnLetter = "A";
    
    $errTypeColumnLetter = "H";
    $ERRCountCell = "G9";
    $numSitesCell = $numSitesColumnLetter."9";

    $numberOfColumnsBeforeLanguages = 17; // Start at 0 not 1
    $firstSumProductColumnNumber = 16; // Start at 0 not 1
    $langErrColumnNumber = 14; // Start at 0 not 1
    $modeDetectColumnNumber = 16; // Start at 0 not 1
    
    $constantValueCell = "A1";


    // Initial totals
    $sheet->setCellValue($constantValueCell,'2.5');

    // Row 7: Totals
    $sheet->setCellValue("{$numSitesColumnLetter}9", "=COUNTA({$numSitesColumnLetter}11:{$numSitesColumnLetter}{$highestRow})"); // Total domains
    $sheet->setCellValue("{$ERRColumnLetter}9", "=SUM({$ERRColumnLetter}11:{$numSitesColumnLetter}{$highestRow})");    // Total errors
    $validSitesRef = "{$numSitesColumnLetter}8";
    $sheet->setCellValue($validSitesRef, "={$numSitesColumnLetter}9-{$ERRColumnLetter}9"); // Number of valid sites
    
    // IDN percentage
    $sheet->setCellValue("{$idnColumnLetter}10", "=SUM({$idnColumnLetter}11:{$idnColumnLetter}{$highestRow})/{$validSitesRef}"); // IDN percentage
    $sheet->getStyle("{$idnColumnLetter}10")->getNumberFormat()->setFormatCode('0.00%');
    
    $lastCol = $numberOfColumnsBeforeLanguages + count($all_lang_codes) + 1;
    for ($col = 9; $col <= $lastCol; $col++) {
        if ($col == $modeDetectColumnNumber) continue; // The "ModeDetect" column, which doesn't have totals
        
        // Write main count/sum & percentage rows
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $rowPercentageValue = 6;
        $rowSumValue = 7;
        
        if ($col < $numberOfColumnsBeforeLanguages) {
            $rowPercentageValue = 8;
            $rowSumValue = 9;           
        }
        
        // Write Total Formula to Row 6
        if ($col == $langErrColumnNumber) {
            // Lang=Err is text, use COUNTA
            $sheet->setCellValue($colLetter . $rowSumValue, "=COUNTA({$colLetter}11:{$colLetter}{$highestRow})");
        } else {
            // Others are numeric flags, use SUM
            $sheet->setCellValue($colLetter . $rowSumValue, "=SUM({$colLetter}11:{$colLetter}{$highestRow})");
        }
        
        // Write Percentage Formula to Row 5
        // Valid sites = (Total Domains - Total Errors) = ($F$6 - $G$6)
        $sheet->setCellValue($colLetter . $rowPercentageValue, "=IF($validSitesRef>0, {$colLetter}{$rowSumValue}/$validSitesRef, 0)");
        $sheet->getStyle($colLetter . $rowPercentageValue)->getNumberFormat()->setFormatCode('0.00%');
        
        
        
        
        // Write sumproduct rows and SUM-C row
        if ($col >= $firstSumProductColumnNumber) {
            $sheet->setCellValue($colLetter . '1', "=SUMPRODUCT({$monoLColumnLetter}11:{$monoLColumnLetter}{$highestRow},{$colLetter}11:{$colLetter}{$highestRow})"); // SUMPRODUCT($I11:$I110,R11:R110)  
            $sheet->setCellValue($colLetter . '2', "=SUMPRODUCT({$hrefLangColumnLetter}11:{$hrefLangColumnLetter}{$highestRow},{$colLetter}11:{$colLetter}{$highestRow})"); // SUMPRODUCT($I11:$I110,R11:R110)
            $sheet->setCellValue($colLetter . '3', "=({$colLetter}1*IF({$monoLColumnLetter}8=0,0,{$monoLColumnLetter}7/{$monoLColumnLetter}8))+({$colLetter}2*IF({$hrefLangColumnLetter}8=0,0,{$hrefLangColumnLetter}7/{$hrefLangColumnLetter}8))"); // =(S1*IF(I8=0, 0, I7/I8))+(S2*IF(P8=0, 0, P7/P8))
            $sheet->getStyle($colLetter . '3')->getNumberFormat()->setFormatCode('0.00');
        }
    }
    
    // Set error group values
    // We used to use SUMPRODUCT with array values, but PHPSpreadsheet has a bug and 
    // doesn't handle array values properly, so we add the values manually using COUNTIF instead
    $sheet->setCellValue(
        "{$ERRColumnLetter}1",
        "=(
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},0) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},5) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},10) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},26) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},27) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},99)
        ) / {$numSitesCell}"
    );    
    $sheet->setCellValue(
        "{$ERRColumnLetter}2",
        "=(
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},20)
        ) / {$numSitesCell}"
    );
    $sheet->setCellValue(
        "{$ERRColumnLetter}3",
        "=(
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},21) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},22) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},23) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},24) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},25)
        ) / {$numSitesCell}"
    );
    $sheet->setCellValue(
        "{$ERRColumnLetter}4",
        "=(
            COUNTIFS(
                {$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow}, \">200\",
                {$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow}, \"<>403\",
                {$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow}, \"<>405\"
                ) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},28)
        ) / {$numSitesCell}"
    );
    $sheet->setCellValue(
        "{$ERRColumnLetter}6",
        "=(
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},15)
        ) / {$numSitesCell}"
    );
    $sheet->setCellValue(
        "{$ERRColumnLetter}7",
        "=(
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},403) +
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},405)
        ) / {$numSitesCell}"
    );
    $sheet->setCellValue(
        "{$ERRColumnLetter}8",
        "=(
            COUNTIF({$errTypeColumnLetter}11:{$errTypeColumnLetter}{$highestRow},98)
        ) / {$numSitesCell}"
    );

    $sheet->getStyle("{$ERRColumnLetter}1")->getNumberFormat()->setFormatCode('0.00%');
    $sheet->getStyle("{$ERRColumnLetter}2")->getNumberFormat()->setFormatCode('0.00%');
    $sheet->getStyle("{$ERRColumnLetter}3")->getNumberFormat()->setFormatCode('0.00%');
    $sheet->getStyle("{$ERRColumnLetter}4")->getNumberFormat()->setFormatCode('0.00%');
    $sheet->getStyle("{$ERRColumnLetter}6")->getNumberFormat()->setFormatCode('0.00%');
    $sheet->getStyle("{$ERRColumnLetter}7")->getNumberFormat()->setFormatCode('0.00%');
    $sheet->getStyle("{$ERRColumnLetter}8")->getNumberFormat()->setFormatCode('0.00%');
    
    // Set special cell summary values
    $sheet->setCellValue("{$numHrefLangColumnLetter}8", "=IF({$hrefLangColumnLetter}9>0,{$numHrefLangColumnLetter}9/{$hrefLangColumnLetter}9,0)");
    $sheet->getStyle("{$numHrefLangColumnLetter}8")->getNumberFormat()->setFormatCode('0.00');
    $sheet->setCellValue("{$likelihoodColumnLetter}8", "={$likelihoodColumnLetter}9");
    $sheet->getStyle("{$likelihoodColumnLetter}8")->getNumberFormat()->setFormatCode('0.00');
    $sheet->setCellValue('C8', "={$hrefLangColumnLetter}8*A1");
    $sheet->getStyle('C8')->getNumberFormat()->setFormatCode('0.00%');
    $sheet->setCellValue("{$monoLColumnLetter}7", '=1-C8');
    $sheet->getStyle("{$monoLColumnLetter}7")->getNumberFormat()->setFormatCode('0.00%');
    $sheet->setCellValue("{$hrefLangColumnLetter}7", "=1-{$monoLColumnLetter}7");
    $sheet->getStyle("{$hrefLangColumnLetter}7")->getNumberFormat()->setFormatCode('0.00%');

    $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);
    $sheet->setCellValue("{$sumColumnLetter}1", "=SUM({$firstSumProductColumnLetter}1:{$lastColLetter}1)");
    $sheet->setCellValue("{$sumColumnLetter}2", "=SUM({$firstSumProductColumnLetter}2:{$lastColLetter}2)");
    $sheet->setCellValue("{$sumColumnLetter}3", "=SUM({$firstSumProductColumnLetter}3:{$lastColLetter}3)");
    $sheet->getStyle("{$sumColumnLetter}3")->getNumberFormat()->setFormatCode('0.00');

    // Set individual %pages and %sites values from Q onwards
    for ($col = $firstSumProductColumnNumber; $col <= $lastCol; $col++) {
        $colLetter = Coordinate::stringFromColumnIndex($col);

        // Add individual %pages values from Q onwards
        $sheet->setCellValue($colLetter . '4', "=IF({$sumColumnLetter}3=0,0,{$colLetter}3/{$sumColumnLetter}3)"); // =R3/Q3
        $sheet->getStyle($colLetter . '4')->getNumberFormat()->setFormatCode('0.00%');
        
        // Add individual %sites values from Q onwards
        $sheet->setCellValue($colLetter . '5', "=IF({$colLetter}3=0,0,{$colLetter}3/{$numSitesColumnLetter}8)"); // =R3/$E$8
        $sheet->getStyle($colLetter . '5')->getNumberFormat()->setFormatCode('0.00%');

    }

    // Set %pages sum value in sumColumn
    $sheet->setCellValue("{$sumColumnLetter}4", "=SUM({$firstSumProductColumnLetter}4:{$lastColLetter}4)");
    $sheet->getStyle("{$sumColumnLetter}4")->getNumberFormat()->setFormatCode('0.00%');
    
    // Set %sites sum value in sumColumn
    $sheet->setCellValue("{$sumColumnLetter}5", "=SUM({$firstSumProductColumnLetter}5:{$lastColLetter}5)");
    $sheet->getStyle("{$sumColumnLetter}5")->getNumberFormat()->setFormatCode('0.00%');


    // Set values in columns A, B and C
    $sheet->setCellValue('A8', "={$englishColumnLetter}4");
    $sheet->getStyle('A8')->getNumberFormat()->setFormatCode('0.00%');
    $sheet->setCellValue('B8', "=IF({$numSitesColumnLetter}8=0,0,{$sumColumnLetter}3/{$numSitesColumnLetter}8)");
    $sheet->getStyle('B8')->getNumberFormat()->setFormatCode('0.00');
    $sheet->setCellValue('D8', "=IF({$hrefLangColumnLetter}9=0,0,{$numHrefLangColumnLetter}9/{$hrefLangColumnLetter}9)");
    $sheet->getStyle('D8')->getNumberFormat()->setFormatCode('0.00');
    
    // Set summary values in columns K, L, M, N
    $sheet->setCellValue('K3', "AlphaH");
    $sheet->setCellValue('L3', "={$constantValueCell}*L4");
    $sheet->getStyle('L3')->getNumberFormat()->setFormatCode('0.00');
    
    $sheet->setCellValue('K4', "H/Extrap");
    $sheet->setCellValue('L4', "=L6/L8");
    $sheet->getStyle('L4')->getNumberFormat()->setFormatCode('0.00');

    $sheet->setCellValue('K6', "Extrap");
    $sheet->setCellValue('L6', "={$hrefLangColumnLetter}9*{$constantValueCell}");
    $sheet->getStyle('L6')->getNumberFormat()->setFormatCode('0.00');
  
    $sheet->setCellValue('M3', "CTRL");
    $sheet->setCellValue('N3', "=P1+({$constantValueCell}*P2)-(O9*({$constantValueCell}-1))");
    $sheet->getStyle('N3')->getNumberFormat()->setFormatCode('0.00');
    

    // Bold the summary area
    $sheet->getStyle('A6:ZZ6')->getFont()->setBold(true);
}

/**
 * Creates the MATRIX_SUMMARY.xlsx file
 */
function createMatrixSummaryExcel(string $finalDir, $allFilesSummaryData, $all_lang_codes): string {
    $summaryFile = rtrim($finalDir, '/').'/MATRIX_SUMMARY.xlsx';
    $spreadsheet = new Spreadsheet();
    
    createFirstSummaryTab($spreadsheet, $allFilesSummaryData, $all_lang_codes);
    createSummary2Tab($spreadsheet, $allFilesSummaryData, $all_lang_codes);
    
    $spreadsheet->setActiveSheetIndex(0);
    
    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save($summaryFile);
    return $summaryFile;    
}

/**
 * Handles creation and processing data for the primary 'Matrix Summary' worksheet tab.
 */
function createFirstSummaryTab(Spreadsheet $spreadsheet, $allFilesSummaryData, $all_lang_codes): void {
    $summarySheet = $spreadsheet->getActiveSheet();
    $summarySheet->setTitle('Matrix Summary');

    // Set column headers manually
    $summarySheet->setCellValue('A5', 'CONFIDENCE INTERVAL');
    $summarySheet->setCellValue('A6', 'STANDARD DEV');
    $summarySheet->setCellValue('A7', 'AVERAGE');
    $summarySheet->setCellValue('A9', 'Sampling Number');
    $summarySheet->setCellValue('B8', 'English1');
    $summarySheet->setCellValue('C8', 'English2');
    $summarySheet->setCellValue('D8', 'MultiR');
    $summarySheet->setCellValue('E8', '%Multi');
    $summarySheet->setCellValue('F8', 'AvgL');
    $summarySheet->setCellValue('G8', 'NLV');
    $summarySheet->setCellValue('H8', 'ERR');

    $summarySheet->setCellValue('I8', 'ErrG1');
    $summarySheet->setCellValue('J8', 'ErrG2-5-6');
    $summarySheet->setCellValue('K8', 'ErrG3');
    $summarySheet->setCellValue('L8', 'ErrG4');
    $summarySheet->setCellValue('M8', 'ErrG7');

    $summarySheet->setCellValue('N8', 'MonoL');
    $summarySheet->setCellValue('O8', 'GooT');
    
    $summarySheet->setCellValue('P8', 'LikelihoodOfMultiL');
    
    $summarySheet->setCellValue('Q8', 'Lang=');
    $summarySheet->setCellValue('R8', 'HrefLang=');
    $summarySheet->setCellValue('S8', 'IDN');
    $summarySheet->setCellValue('T8', 'OTHERL');
    $summarySheet->setCellValue('U8', 'NON-ID');
    
    $firstColumnWithLanguages = 21; // Start counting at 0 not 1
    $firstRowWithData = 10;
    
    // Add all language name column headers
    foreach ($all_lang_codes as $index => $lang) {
        $col = $firstColumnWithLanguages + $index + 1;
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $summarySheet->setCellValue($colLetter . '8', $lang["CODE_LANG_NAME"]);
    }

    $colsWithDecimalFormat = [ 'D', 'F', 'G', 'P' ];
    // Set row data
    $rowIdx = $firstRowWithData;
    foreach ($allFilesSummaryData as $fileData) {
    
        // Set column A to the filename and put it in red if it has a control error
        $summarySheet->setCellValue('A'.$rowIdx, $fileData['filename']);
        if (!empty($fileData['hasControlError'])) {
            $summarySheet->getStyle('A'.$rowIdx)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFC7CE'); // Soft red
        }
        $summarySheet->getStyle('A'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');
        
        
        $summarySheet->setCellValue('B'.$rowIdx, $fileData['english1']);
        $summarySheet->getStyle('B'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');
        $summarySheet->setCellValue('G'.$rowIdx, $fileData['nlv']);
        $summarySheet->getStyle('G'.$rowIdx)->getNumberFormat()->setFormatCode('0.00');
        $summarySheet->setCellValue('H'.$rowIdx, $fileData['err_percentage']);
        $summarySheet->getStyle('H'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');

        $summarySheet->setCellValue('I'.$rowIdx, $fileData['err_group_1']);
        $summarySheet->getStyle('I'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');
        $summarySheet->setCellValue('J'.$rowIdx, $fileData['err_group_2_5_6']);
        $summarySheet->getStyle('J'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');
        $summarySheet->setCellValue('K'.$rowIdx, $fileData['err_group_3']);
        $summarySheet->getStyle('K'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');
        $summarySheet->setCellValue('L'.$rowIdx, $fileData['err_group_4']);
        $summarySheet->getStyle('L'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');
        $summarySheet->setCellValue('M'.$rowIdx, $fileData['err_group_7']);
        $summarySheet->getStyle('M'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');

        foreach ($fileData['mapped'] as $targetCol => $value) {
            $summarySheet->setCellValue($targetCol . $rowIdx, $value);
            if ( in_array( $targetCol, $colsWithDecimalFormat ) ) {
                $summarySheet->getStyle($targetCol . $rowIdx)->getNumberFormat()->setFormatCode('0.00');
            } else {
                $summarySheet->getStyle($targetCol . $rowIdx)->getNumberFormat()->setFormatCode('0.00%');
            } 
        }
        $colIdx = $firstColumnWithLanguages;
        foreach ($fileData['languages'] as $val) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $summarySheet->setCellValue($colLetter . $rowIdx, $val);
            $summarySheet->getStyle($colLetter . $rowIdx)->getNumberFormat()->setFormatCode('0.00%');
            $colIdx++;
        }

        $rowIdx++;
    }   

    $summarySheet->getStyle('A1:ZZ9')->getFont()->setBold(true);
    $summarySheet->setSelectedCell('A1');

}
 
/**
 * Creates and appends a second tab titled "SUMMARY2" with specified static cells.
 */
function createSummary2Tab(Spreadsheet $spreadsheet, $allFilesSummaryData, $all_lang_codes): void {
    // Instantiate a new distinct sheet component framework row tab
    $summary2Sheet = $spreadsheet->createSheet();
    $summary2Sheet->setTitle('Summary2');
    
    // Set explicit static labels across designated grid coordinates
    $summary2Sheet->setCellValue('B6', '% of sites with language after extrapolation');
    
    $summary2Sheet->setCellValue('A7', 'Sampling Number');
    $summary2Sheet->setCellValue('A8', 'Sampling Number');
    $summary2Sheet->setCellValue('A9', 'Sampling Number');
    
    $summary2Sheet->setCellValue('B7', 'OTHERL');
    $summary2Sheet->setCellValue('B8', 'OTHERL');
    $summary2Sheet->setCellValue('B9', 'OTHERL');
    $summary2Sheet->setCellValue('C7', 'NON-ID');
    $summary2Sheet->setCellValue('C8', 'NON-ID');
    $summary2Sheet->setCellValue('C9', 'NON-ID');
    
    $firstColumnWithLanguages = 3; // Start counting at 0 not 1
    $firstRowWithData = 10;
    
    // Add all language name column headers
    foreach ($all_lang_codes as $index => $lang) {
        $col = $firstColumnWithLanguages + $index + 1;
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $summary2Sheet->setCellValue($colLetter . '7', $lang["CODE_LANG_NAME"]);
        $summary2Sheet->setCellValue($colLetter . '8', $lang["CODE_LANG_ISO3"]);
        $summary2Sheet->setCellValue($colLetter . '9', $lang["CODE_LANG_GOOT"]);
    }    
    
    $summary2Sheet->getStyle('A1:ZZ9')->getFont()->setBold(true);
    
    // Adds Sampling Number, OTHERL, NON-ID and then all the language totals (all from row 5 in Ti-FINAL, ie. before extrapolation)
    $rowIdx = $firstRowWithData;
    foreach ($allFilesSummaryData as $fileData) {
        $summary2Sheet->setCellValue('A'.$rowIdx, $fileData['filename']);
        $summary2Sheet->setCellValue('B'.$rowIdx, $fileData['otherl_before_extrapolation']);
        $summary2Sheet->setCellValue('C'.$rowIdx, $fileData['nonid_before_extrapolation']);
        $summary2Sheet->getStyle('B'.$rowIdx)->getNumberFormat()->setFormatCode('0.00%');

        $colIdx = $firstColumnWithLanguages;
        foreach ($fileData['language_percentages'] as $val) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $summary2Sheet->setCellValue($colLetter . $rowIdx, $val);
            $summary2Sheet->getStyle($colLetter . $rowIdx)->getNumberFormat()->setFormatCode('0.00%');
            $colIdx++;
        }
        
        $rowIdx++;
    }
    
}



/**
 * Builds an array of summary data to be stored in memory
 * rather than having to re-open each individual excel file
 * This is used when creating individual excel files,
 * and then the outputted value (an array of arrays) is
 * read when creating MATRIX_SUMMARY.
 * Saving these values in memory prevents us from having to re-open
 * every individual Excel file when creating MATRIX_SUMMARY, thus
 * saving a lot of server overhead.
 */
function getDataForMatrixSummaryAsArray( $baseNameOnly, $sheet ) {

    // Column letter references in SiFINAL
    $englishColumnLetter = "BL";
    $numSitesColumnLetter = "E";
    $ERRColumnLetter = "G";
    $monoLColumnLetter = "I";
    $numHrefLangColumnLetter = "J";
    $likelihoodColumnLetter = "L";
    $hrefLangColumnLetter = "O";
    $sumColumnLetter = "P";
    $firstSumProductColumnLetter = "Q";

    $englishTallyColumnLetter = "A";
    $idnColumnLetter = "A";
    $multiRColumnLetter = "B";
    $percentMultiColumnLetter = "C";
    $avgLColumnLetter = "D";
    $gooTColumnLetter = "K";
    $nonIdColumnLetter = "R";
    $otherLColumnLetter = $firstSumProductColumnLetter;
    $langEqualsColumnLetter = "M";
    $langERRColumnLetter = "N";
    $hrefLangColumnLetter = "O";

    $sourceCtrlColumnReference = "N3";
    $sourceSumCColumnReference = "P3";

    $firstLanguageColumnNumber = 18; // Begin counting at 0, not 1


    $fileSummaryData = [];
    $fileSummaryData['filename'] = $baseNameOnly;
    $fileSummaryData['nlv'] = $sheet->getCell("{$sumColumnLetter}3")->getCalculatedValue();
    $fileSummaryData['english1'] = $sheet->getCell("{$englishColumnLetter}6")->getCalculatedValue();

    $fileSummaryData['err_group_1'] = $sheet->getCell("{$ERRColumnLetter}1")->getCalculatedValue();
    $fileSummaryData['err_group_2'] = $sheet->getCell("{$ERRColumnLetter}2")->getCalculatedValue();
    $fileSummaryData['err_group_3'] = $sheet->getCell("{$ERRColumnLetter}3")->getCalculatedValue();
    $fileSummaryData['err_group_4'] = $sheet->getCell("{$ERRColumnLetter}4")->getCalculatedValue();
    $fileSummaryData['err_group_5'] = $sheet->getCell("{$ERRColumnLetter}6")->getCalculatedValue();
    $fileSummaryData['err_group_6'] = $sheet->getCell("{$ERRColumnLetter}7")->getCalculatedValue();
    $fileSummaryData['err_group_7'] = $sheet->getCell("{$ERRColumnLetter}8")->getCalculatedValue();
    $fileSummaryData['err_group_2_5_6'] = 
        (float)($fileSummaryData['err_group_2'] ?? 0) +
        (float)($fileSummaryData['err_group_5'] ?? 0) +
        (float)($fileSummaryData['err_group_6'] ?? 0);
        
    $fileSummaryData['otherl_before_extrapolation'] = $sheet->getCell("{$otherLColumnLetter}5")->getCalculatedValue();
    $fileSummaryData['nonid_before_extrapolation'] = $sheet->getCell("{$nonIdColumnLetter}5")->getCalculatedValue();
        
    $fileSummaryData['err_percentage'] = $sheet->getCell("{$ERRColumnLetter}9")->getCalculatedValue() / $sheet->getCell("{$numSitesColumnLetter}9")->getCalculatedValue();
    
    // Create map of targetColumn -> sourceColumn
    $colMap = [
        'C'=>$englishTallyColumnLetter,
        'D'=>$multiRColumnLetter,
        'E'=>$percentMultiColumnLetter,
        'F'=>$avgLColumnLetter,
        'N'=>$monoLColumnLetter,
        'O'=>$gooTColumnLetter,
        'P'=>$likelihoodColumnLetter,
        'Q'=>$langEqualsColumnLetter,
        'R'=>$hrefLangColumnLetter,
        'T'=>$otherLColumnLetter,
        'U'=>$nonIdColumnLetter
    ];
    
    // Create second map of targetColumn -> sourceColumn for data with overlapping column names with $colMap above
    // Currently, only the IDN column overlaps with EnglishTally (English2) column, which is A
    $colMapDuplicateSourceColumns = [
        'S'=>$idnColumnLetter
    ];
    
    // Add values from $colMap
    foreach ($colMap as $targetCol => $sourceCol) {
        if ( $sourceCol == $monoLColumnLetter) {
            $fileSummaryData['mapped'][$targetCol] = $sheet->getCell($sourceCol . '7')->getCalculatedValue();
        } elseif ( $sourceCol == $likelihoodColumnLetter ) {
            $fileSummaryData['mapped'][$targetCol] = $sheet->getCell($sourceCol . '3')->getCalculatedValue();
        } elseif ( $sourceCol == $otherLColumnLetter || $sourceCol == $nonIdColumnLetter ) {
            $fileSummaryData['mapped'][$targetCol] = $sheet->getCell($sourceCol . '4')->getCalculatedValue();
        } else {
            $fileSummaryData['mapped'][$targetCol] = $sheet->getCell($sourceCol . '8')->getCalculatedValue();
        }
    }
    // Add values from $colMapDuplicateSourceColumns
    foreach ($colMapDuplicateSourceColumns as $targetCol => $sourceCol) {
        $fileSummaryData['mapped'][$targetCol] = $sheet->getCell($sourceCol . '10')->getCalculatedValue();
    }
    
    // Verify data integrity by comparing N3 and P3
    $ctrlValue = $sheet->getCell("{$sourceCtrlColumnReference}")->getCalculatedValue();
    $sumCValue = $sheet->getCell("{$sourceSumCColumnReference}")->getCalculatedValue();
    $fileSummaryData['hasControlError'] = ((float)$ctrlValue !== (float)$sumCValue); // Sets to true or false

    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = Coordinate::columnIndexFromString($highestCol);
    $fileSummaryData['languages'] = [];
    $fileSummaryData['language_percentages'] = [];
    for ($i = $firstLanguageColumnNumber; $i <= $highestColIndex; $i++) {
        $colLetter = Coordinate::stringFromColumnIndex($i);
        $fileSummaryData['languages'][] =
            $sheet->getCell($colLetter . '4')->getCalculatedValue();
        $fileSummaryData['language_percentages'][] =
            $sheet->getCell($colLetter . '5')->getCalculatedValue();
    }
    return $fileSummaryData;
}



?>
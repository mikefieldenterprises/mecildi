<?php

// Primary function to handle batch processing of curls for each domain
function processBatch(AppConfig $config, array $domains, array $lineNumbers, string $baseName, int $totalLines, $out)
{
    // DB CONNECTION (single PDO instance)
    global $statusFile;
    $pdo = db_connect($config, $statusFile);
    
    $CRAWLER_USER_AGENT = $config->app['crawler_user_agent'];
    $ACCEPT_LANGUAGE_HEADER = [ $config->app['accept_language_header'] ];

    if (shouldStop()) {
        return false;
    }
    
    // Get current DB progress at batch start
    $row = db_getDataProgress($config, $baseName);
    $lastProcessedLine = $row && isset($row['LAST_LINE_PROCESSED'])
        ? (int)$row['LAST_LINE_PROCESSED']
        : 0;

    $batchResults = [];     // index => json string (To preserve order)
    $completedLines = [];   // Track finished lines for DB progress

    $totalDomains = count($domains);

    /* ============================================================
     * STEP 1 — Resolve unique root domains in this batch
     * ============================================================ */

    $domainMap = [];        // index => root domain
    $uniqueHosts = [];      // rootDomain => true

    foreach ($domains as $i => $domain) {
        $host = strtolower($domain);
        $domainMap[$i] = $host;
        $uniqueHosts[$host] = true;
    }

    $uniqueHosts = array_keys($uniqueHosts);

    /* ============================================================
     * STEP 2 — Check DB cache first (2 day freshness preserved)
     * ============================================================ */

    $robotsCache = []; // domain => ['allowed'=>bool, 'delay'=>float|null, 'domain_prefix'=>string]
    $domainsToFetch = [];

    $stmt = $pdo->prepare("
        SELECT DOMAIN, IS_ALLOWED, CRAWL_DELAY, DOMAIN_PREFIX
        FROM CACHE_ROBOTS
        WHERE DOMAIN = :domain
          AND LAST_CHECKED >= NOW() - INTERVAL 7 DAY
        LIMIT 1
    ");

    foreach ($uniqueHosts as $host) {
        $stmt->execute([':domain' => $host]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $robotsCache[$host] = [
                'allowed' => (bool)$row['IS_ALLOWED'],
                'delay'   => $row['CRAWL_DELAY'],
                'domain_prefix' => $row['DOMAIN_PREFIX']
            ];
            logger_logCrawler("$baseName | ROBOTS | $host | robots.txt trouvé dans la cache");
        } else {
            $domainsToFetch[] = $host;
            logger_logCrawler("$baseName | ROBOTS | $host | robots.txt pas dans la cache");
        }
    }

    /* ============================================================
     * STEP 3 — Resolve prefix + fetch robots.txt in full parallel
     * ============================================================ */
    
    if (!empty($domainsToFetch)) {
    
        $prefixes = [
            "https://www.",
            "https://",
            "http://www.",
            "http://"
        ];
    
        $multi = curl_multi_init();
        $handles = [];
    
        // Track resolution state per host
        $resolved = [];       // host => bool
        $winnerHandle = [];   // host => resource
        $robotsContent = [];  // host => content
        $winningPrefix = [];  // host => prefix
    
        foreach ($domainsToFetch as $host) {
    
            $resolved[$host] = false;
    
            foreach ($prefixes as $prefix) {
    
                $robotsUrl = $prefix . $host . "/robots.txt";
    
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $robotsUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_USERAGENT => $CRAWLER_USER_AGENT,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_HTTPHEADER => $ACCEPT_LANGUAGE_HEADER
                ]);
    
                curl_multi_add_handle($multi, $ch);
    
                $handles[(int)$ch] = [
                    'handle' => $ch,
                    'host'   => $host,
                    'prefix' => $prefix
                ];
            }
        }
    
        $running = null;
    
        do {
            curl_multi_exec($multi, $running);
    
            while ($info = curl_multi_info_read($multi)) {
    
                $ch = $info['handle'];
                $key = (int)$ch;
    
                if (!isset($handles[$key])) continue;
    
                $data = $handles[$key];
                $host = $data['host'];
                $prefix = $data['prefix'];
    
                $content = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
                // If this host already resolved, just cleanup
                if ($resolved[$host]) {
                    curl_multi_remove_handle($multi, $ch);
                    curl_close($ch);
                    unset($handles[$key]);
                    continue;
                }
    
                // Successful response (server exists)
                if ($content !== false && $httpCode > 0) {
    
                    $resolved[$host] = true;
                    $winnerHandle[$host] = $ch;
                    $robotsContent[$host] = $content;
                    $winningPrefix[$host] = $prefix;
    
                    logger_logCrawler("$baseName | ROBOTS | $host | Préfixe gagnant: $prefix ($httpCode)");
    
                    // Cancel all other handles for this host
                    foreach ($handles as $hKey => $hData) {
                        if ($hData['host'] === $host && $hKey !== $key) {
                            curl_multi_remove_handle($multi, $hData['handle']);
                            curl_close($hData['handle']);
                            unset($handles[$hKey]);
                        }
                    }
                }
    
                // Cleanup current handle if it's not the winner
                if (!$resolved[$host] || $winnerHandle[$host] !== $ch) {
                    curl_multi_remove_handle($multi, $ch);
                    curl_close($ch);
                }
    
                unset($handles[$key]);
            }
    
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
    
        } while ($running > 0);
    
        curl_multi_close($multi);
    
        /* -----------------------------
         * Parse robots.txt + Save cache
         * ----------------------------- */
    
        foreach ($domainsToFetch as $host) {
    
            $prefix = $winningPrefix[$host] ?? "https://www.";
            $content = $robotsContent[$host] ?? null;
    
            $isAllowed = true;
            $crawlDelay = null;
    
            if ($content && strlen($content) > 0) {
    
                $lines = preg_split('/\r\n|\r|\n/', $content);
                $uaMatched = false;
    
                foreach ($lines as $line) {
    
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
    
                    if (stripos($line, 'user-agent:') === 0) {
                        $ua = trim(substr($line, 11));
                        $uaMatched = ($ua === '*' || stripos($CRAWLER_USER_AGENT, $ua) !== false);
                        continue;
                    }
    
                    if ($uaMatched && stripos($line, 'disallow:') === 0) {
                        $rule = trim(substr($line, 9));
                        if ($rule === '/') {
                            $isAllowed = false;
                            break;
                        }
                    }
    
                    if ($uaMatched && stripos($line, 'crawl-delay:') === 0) {
                        $delay = trim(substr($line, 12));
                        if (is_numeric($delay)) {
                            $crawlDelay = (int)$delay;
                        }
                    }
                }
            }
    
            $robotsCache[$host] = [
                'allowed' => $isAllowed,
                'delay'   => $crawlDelay,
                'domain_prefix' => $prefix
            ];
    
            // Store in DB
            $stmtInsert = $pdo->prepare("
                INSERT INTO CACHE_ROBOTS (DOMAIN, IS_ALLOWED, CRAWL_DELAY, LAST_CHECKED, DOMAIN_PREFIX)
                VALUES (:domain, :is_allowed, :crawl_delay, NOW(), :domain_prefix)
                ON DUPLICATE KEY UPDATE
                    IS_ALLOWED = VALUES(IS_ALLOWED),
                    CRAWL_DELAY = VALUES(CRAWL_DELAY),
                    LAST_CHECKED = NOW(),
                    DOMAIN_PREFIX = VALUES(DOMAIN_PREFIX)
            ");
    
            $stmtInsert->execute([
                ':domain' => $host,
                ':is_allowed' => $isAllowed ? 1 : 0,
                ':crawl_delay' => $crawlDelay,
                ':domain_prefix' => $prefix
            ]);
        }
    }



    /* ============================================================
     * STEP 4 — Process HTML in parallel
     * ============================================================ */

    $multi = curl_multi_init();
    $activeHandles = [];
    $allFilesSummaryData = [];  // Initialize array to store summary data in memory to avoid re-opening files

    foreach ($domains as $i => $domain) {

        $host = $domainMap[$i];
        $lineNumber = $lineNumbers[$i];

        logger_logCrawler("$baseName | $lineNumber/$totalLines | $domain | Traitement débuté");

        if (!$robotsCache[$host]['allowed']) {

            $json = makeErrorJSON($domain, "Blocked by robots.txt", 15);
            $batchResults[$i] = json_encode($json, JSON_UNESCAPED_UNICODE); // Store at original index
            $completedLines[] = $lineNumber;
            logger_logCrawler("$baseName | $lineNumber/$totalLines | $domain | bloqué par robots.txt");

            continue;
        }

        $prefix = $robotsCache[$host]['domain_prefix'] ?? "https://";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $prefix . $domain,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT => $CRAWLER_USER_AGENT,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $ACCEPT_LANGUAGE_HEADER
        ]);

        curl_multi_add_handle($multi, $ch);
        $activeHandles[(int)$ch] = [
            'index'  => $i,        // Critical - Keep track of original position
            'handle'=>$ch,
            'domain'=>$domain,
            'line'=>$lineNumber
        ];
    }

    $running = null;

    do {
        curl_multi_exec($multi, $running);

        while ($info = curl_multi_info_read($multi)) {

            $ch = $info['handle'];
            $key = (int)$ch;

            if (!isset($activeHandles[$key])) continue;

            $data = $activeHandles[$key];
            $idx  = $data['index']; // keep track of index
    
            $domain = $data['domain'];
            $lineNumber = $data['line'];

            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirect = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);

            if ($response === false || $httpCode >= 400) {

                $json = makeErrorJSON(
                    $domain,
                    getHttpStatusMessage($httpCode),
                    $httpCode
                );
                logger_logCrawler("$baseName | $lineNumber/$totalLines | $domain | Erreur $httpCode");

            } else {

                $crawlerOut = [
                    "error" => false,
                    "response_code" => $httpCode,
                    "error_message" => "",
                    "url" => $domain,
                    "redirecturl" => $redirect,
                    "requestheaders" => "",
                    "text" => $response
                ];

                $json = crawler_processHeuristics($config, $crawlerOut);
                logger_logCrawler("$baseName | $lineNumber/$totalLines | $domain | Traitement réussi");
            }

            // Buffer the result at the original index
            $batchResults[$idx] = json_encode($json, JSON_UNESCAPED_UNICODE);
            $completedLines[] = $lineNumber;
            unset($activeHandles[$key]);

        }

        if ($running) {
            curl_multi_select($multi, 1.0);
        }

    } while ($running > 0);

    curl_multi_close($multi);
    
    /* ============================================================
     * STEP 5 — Output results in order & Update Progress
     * ============================================================ */
    
    // Sort by the original input index and write to file
    ksort($batchResults);
    foreach ($batchResults as $idx => $jsonString) {
        fwrite($out, $jsonString . PHP_EOL);
    }

    // Progress update fix:
    // If we have completed lines, check if we filled the gap from lastProcessedLine
    if (!empty($completedLines)) {
        sort($completedLines);
        $maxInThisBatch = max($completedLines);
        $minInThisBatch = min($completedLines);

        // If the batch starts exactly where the previous left off (or overlap)
        if ($minInThisBatch <= $lastProcessedLine + 1) {
            // New progress is the highest line in this batch, or stays the same if batch was somehow old
            $newProgress = max($lastProcessedLine, $maxInThisBatch);
            db_updateDataProgress($config, $baseName, $totalLines, $newProgress);
            logger_logInfo("$baseName | PROGRESS | Base de données mise à jour à la ligne $newProgress");
        } else {
            logger_logError("$baseName | PROGRESS | ÉCART DÉTECTÉ: " . ($lastProcessedLine + 1) . " attendu, mais le lot a commencé à $minInThisBatch");
        }
    }        

    return true;
}




?>
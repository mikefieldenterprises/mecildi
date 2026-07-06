<?php

/**
 * Start by getting hreflang values
 * If there's at least one hreflang value, then stop processing everything else
 * Otherwise, if hreflang isn't used, calculate the rest of the heuristics
 */
function crawler_processHeuristics( AppConfig $config, $crawlerOut ) {
    $url = $crawlerOut["url"];
    $html = isset($crawlerOut["text"]) && is_string($crawlerOut["text"]) ? $crawlerOut["text"] : "";
    
    if ($html === "") {
        // Check if the server returned a 200 OK but provided absolutely zero HTML text
        if (isset($crawlerOut['response_code']) && $crawlerOut['response_code'] == 200) {
            return makeErrorJSON(
                $url ?? null, 
                'Empty or invalid HTML content.', 
                28 // Custom internal error code for empty HTML overrides
            );
        }
        
        // General fallback for other connection/server error states
        return makeErrorJSON( 
            $url ?? null, 
            $crawlerOut['error_message'] ?? 'Empty or invalid HTML content with non-200 error code.', 
            $crawlerOut['response_code'] ?? null 
        );
    }
    
    // Check for common signature of sites not accessible or not built
    if ($wafError = detectWafBlockAndHandle($crawlerOut, $html)) { return $wafError; }              // WAF error pages
    if ($parkedError = detectDomainParkingAndHandle($crawlerOut, $html)) { return $parkedError; }   // Domain parking/for sale
    if ($ucError = detectUnderConstructionAndHandle($crawlerOut, $html)) { return $ucError; }       // Under Construction / Coming Soon
    if ($defaultError = detectDefaultServerPageAndHandle($crawlerOut, $html)) { return $defaultError; }    // Default hosting pages
    if ($dnsError = detectDnsErrorPageAndHandle($crawlerOut, $html)) { return $dnsError; }          // DNS not configured
    if ($dnsMonetized = detectDnsMonetizationPageAndHandle($crawlerOut, $html)) { return $dnsMonetized; }   // DNS Monetization / Hijacking pages

    $redirecturl = isSameUrl($url, $crawlerOut["redirecturl"]) ? null : $crawlerOut["redirecturl"];
    
    $result = [
        'url' => $url,
        'redirecturl' => $redirecturl,
        'response_code' => $crawlerOut["response_code"],
        'error' => $crawlerOut["error"],
        'error_message' => $crawlerOut["error_message"],
        'likelihood_of_multilingualism' => null,
        'multilingual_detected' => false,
        'first_lang_2' => null,
        'all_langs' => [],
        'all_langs_2' => [],
        'hreflangs_all' => [],
        'hreflangs' => [],
        'url_languages' => [],
        'language_selector' => false,
        'language_selector_matches' => [],
        'js_indicators' => [],
        'google_translate_widget' => false,
        'detected_lang' => null,
        'lang_detection_error' => false,
        'lang_detection_error_message' => null,
        'lang_detection_error_number' => null

    ];

    // ------------------------------------------------------
    // 1. Get hreflang values
    // <link rel="alternate" hreflang="">
    // ------------------------------------------------------
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $result['hreflangs_all'] = [];
    $result['hreflangs'] = [];
    $rawLangValues = [];
    $links = $dom->getElementsByTagName('link');
    foreach ($links as $link) {
        if (strtolower($link->getAttribute('rel')) === 'alternate') {
            $hreflang = $link->getAttribute('hreflang');
            $href     = $link->getAttribute('href');
            if ($hreflang && $href) {
                $rawLangValues[] = $hreflang;
                $result['hreflangs_all'][] = [
                    'lang' => strtolower($hreflang),
                    'href' => $href
                ];
            }
        }
    }
    
    // Loop through all hreflangs_all values
    // Get first two characters only, unique values only, ignore x-default
    // Add these to hreflangs array
    if (!empty($result['hreflangs_all']) && is_array($result['hreflangs_all'])) {
        
        if (LOG_ALL_LANG_VALUES) {
            logger_logHrefLang( $url, implode(', ', $rawLangValues) );
        }
        
        foreach ($result['hreflangs_all'] as $langEntry) {
            // Ensure 'lang' key exists and is a non-empty string
            if (!isset($langEntry['lang']) || !is_string($langEntry['lang']) || trim($langEntry['lang']) === '') {
                continue;
            }
    
            $langCode = $langEntry['lang'];
    
            // Skip "x-default" (case-insensitive)
            if (strcasecmp($langCode, 'x-default') === 0) {
                continue;
            }
    
            // Get the first two characters (lowercase)
            $code = strtolower(substr($langCode, 0, 2));
    
            // Add only if not already in the array
            if ($code && !in_array($code, $result['hreflangs'])) {
                $result['hreflangs'][] = $code;
            }
        }
    }   

    // Add a message to the logger to note that heuristics are being skipped
    if (!empty($result['hreflangs'])) {
        logger_logInfo("Found hreflang in $url, so skipping rest of heuristics and language detection");
    }
    
    // ------------------------------------------------------
    // 2. Get all lang= values
    // [
    //   'first_lang_2' => 'en',
    //   'all_langs' => ['en-us', 'fr', 'en', 'pt-br'],
    //   'all_langs_2' => ['en', 'fr', 'pt']
    // ]
    // ------------------------------------------------------
    if (empty($result['hreflangs']) || LOG_ALL_LANG_VALUES) {

        $langMatches = [];

        foreach ($dom->getElementsByTagName('*') as $node) {
            if ($node->hasAttribute('lang')) {
                $langValue = trim($node->getAttribute('lang'));
                if ($langValue !== '') {
                    $langMatches[] = $langValue;
                }
            }
        }
        
        if (!empty($langMatches)) {
        
            if (LOG_ALL_LANG_VALUES) {
                logger_logLangEquals($url, implode(", ", $langMatches));
            }
        
            if (empty($result['hreflangs'])) {
        
                // Normalize all lang values (lowercase)
                $allLangs = array_map('strtolower', $langMatches);
        
                // 1. First two characters of the VERY FIRST lang attribute
                $result['first_lang_2'] = substr($allLangs[0], 0, 2);
        
                // 2. All unique full lang values
                $result['all_langs'] = array_values(array_unique($allLangs));
        
                // 3. First two characters of all langs, unique
                $result['all_langs_2'] = array_values(array_unique(
                    array_map(function ($lang) {
                        return substr($lang, 0, 2);
                    }, $result['all_langs'])
                ));
            }
        }
    }
    

    
    // ------------------------------------------------------
    // ️3. URL language patterns
    // ------------------------------------------------------
    //ace|ach|af|ak|alz|am|ar|as|awa|ay|az|ba|bm|ban|bbc|be|bem|bn|bew|bho|bik|bts|bg|ca|ceb|cs|cgg|cv|da|de|din|doi|el|en|et|ee|fa|fi|fr|ff|gl|gn|gu|ht|ha|bs|hr|sr|he|iw|hil|hi|hmn|hrx|hu|hy|ig|ilo|it|jw|jv|ja|kn|ka|kk|km|rw|ky|gom|ko|ktu|ckb|ku|lo|lv|li|ln|lt|lmo|lg|luo|mai|mak|ml|mr|mk|ktu|mg|mni-Mtei|mn|id|ms|my|nr|nr|ne|nl|no|nso|nus |ny|or|om|pag|pam|pa|pl|pt|ps|qu|rom|ro|rn|ru|scn|shn|si|sk|sl|sn|sd|so|es|sq|ss|su|sw|sv|ta|tt|te|tg|fil|tl|th|ti|tn|ts|tk|tr|ug|uk|ur|uz|vi|xh|yo|zh|yue|zu|oc
    if (empty($result['hreflangs'])) {

        preg_match_all('/https?:\/\/[^"\']*(?:\/|\?|=)(ace|ach|af|ak|alz|am|ar|as|awa|ay|az|ba|bm|ban|bbc|be|bem|bn|bew|bho|bik|bts|bg|ca|ceb|cs|cgg|cv|da|de|din|doi|el|en|et|ee|fa|fi|fr|ff|gl|gn|gu|ht|ha|bs|hr|sr|he|iw|hil|hi|hmn|hrx|hu|hy|ig|ilo|it|jw|jv|ja|kn|ka|kk|km|rw|ky|gom|ko|ktu|ckb|ku|lo|lv|li|ln|lt|lmo|lg|luo|mai|mak|ml|mr|mk|ktu|mg|mni-Mtei|mn|id|ms|my|nr|nr|ne|nl|no|nso|nus |ny|or|om|pag|pam|pa|pl|pt|ps|qu|rom|ro|rn|ru|scn|shn|si|sk|sl|sn|sd|so|es|sq|ss|su|sw|sv|ta|tt|te|tg|fil|tl|th|ti|tn|ts|tk|tr|ug|uk|ur|uz|vi|xh|yo|zh|yue|zu|oc)(?:[\/?=&"\'#]|$)/i', $html, $urlLangs);
        $result['url_languages'] = array_values(array_unique(array_map('strtolower', $urlLangs[1])));

    }
    
    // ------------------------------------------------------
    // 4. Language selector in HTML
    // ------------------------------------------------------
    if (empty($result['hreflangs'])) {

        $languageMenu = detectLanguageMenuDOM($html);
        $result['language_selector'] = $languageMenu['has_any_language_ui'];
        $result['language_selector_matches'] = $languageMenu['languages'];

    }
    
    // ------------------------------------------------------
    // 5. Inline JavaScript i18n indicators
    // ------------------------------------------------------
    if (empty($result['hreflangs'])) {

        preg_match_all('/i18n|gettext|translations|locale|Intl|setLanguage|changeLanguage/i', $html, $jsMatches);
        $result['js_indicators'] = array_values(array_unique($jsMatches[0]));

    }
    
    // ------------------------------------------------------
    // 6. Google Translate widget detection
    // ------------------------------------------------------
    if (empty($result['hreflangs'])) {

        if (
            preg_match('/google_translate_element/i', $html) ||
            preg_match('/translate_a\/element\.js/i', $html) ||
            preg_match('/GoogleTranslateElementInit/i', $html)
        ) {
            $result['google_translate_widget'] = true;
        }

    }
    
    // ------------------------------------------------------
    // 7. Multilingual flag (heuristic)
    // ------------------------------------------------------
    if (!empty($result['hreflangs']) && count($result['hreflangs']) >= 2) {
        $result['multilingual_detected'] = true;
    }
    
    // ------------------------------------------------------
    // 8. Likelihood of multilingualism
    // ------------------------------------------------------
    if (empty($result['hreflangs'])) {
        $result['likelihood_of_multilingualism'] = calculateMultilingualLikelihood($result);
    }
    
    // ------------------------------------------------------
    // 9. Use cloud service (TOMEDES) to detect
    //    language if the site doesn't use hreflang
    // ------------------------------------------------------
    if (empty($result['hreflangs'])) {
    
        $p = getDetectedSourceLanguage($config, $crawlerOut);
    
        // Ensure we got a structured response
        if (!is_array($p)) {
            $result['lang_detection_error'] = true;
            $result['lang_detection_error_number'] = 0;
            $result['lang_detection_error_message'] = 'Invalid response from language detection';
        }
        // Explicit error from language detection method
        elseif (!empty($p['error'])) {
            $result['lang_detection_error'] = true;
            $result['lang_detection_error_number'] = isset($p['error_number']) && $p['error_number'] !== '' ? $p['error_number'] : 98;
            $result['lang_detection_error_message'] = isset($p['error_message']) && $p['error_message'] !== '' ? $p['error_message'] : 'Unknown language detection error';
        }
        // Successful detection with a non-empty language code
        elseif (isset($p['lang']) && is_string($p['lang']) && trim($p['lang']) !== '') {
            $result['detected_lang'] = strtolower($p['lang']);
        }
        // Catch-all: no error flag, but no usable language either
        else {
            $result['lang_detection_error'] = true;
            $result['lang_detection_error_number'] = 90;
            $result['lang_detection_error_message'] = 'Language detection returned no result';
        }
        
        if ( in_array((int)$result['lang_detection_error_number'], [2, 4], true) ) {
            return makeErrorJSON(
                $crawlerOut['url'] ?? null,
                "Language detection error: no HTML/text provided or not enough characters (NON-TH)",
                98
            );
        }
        
    }

    
    return $result;
    
}

/**
 * Scan HTML for common WAF blocking signature, like "Attention Required! | Cloudflare"
 * so we don't evaluate the language of an error page
 */
function detectWafBlockAndHandle($crawlerOut, $html) {

    $lowerHtml = strtolower($html);

    // Extract <title>
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = strtolower(trim($m[1]));
    }

    $isWafBlock = false;
    $wafReason  = null;

    // ---- Cloudflare ----
    if (
        (strpos($title, 'attention required') !== false && strpos($title, 'cloudflare') !== false) ||
        (strpos($title, 'just a moment') !== false && strpos($lowerHtml, 'cloudflare') !== false) ||
        strpos($lowerHtml, 'cf-browser-verification') !== false ||
        strpos($lowerHtml, 'cf-chl-') !== false
    ) {
        $isWafBlock = true;
        $wafReason  = 'Blocked by Cloudflare WAF';
    }

    // ---- Akamai ----
    elseif (
        strpos($title, 'access denied') !== false && strpos($lowerHtml, 'reference #') !== false
    ) {
        $isWafBlock = true;
        $wafReason  = 'Blocked by Akamai';
    }

    // ---- Sucuri ----
    elseif (
        strpos($lowerHtml, 'sucuri website firewall') !== false
    ) {
        $isWafBlock = true;
        $wafReason  = 'Blocked by Sucuri WAF';
    }

    // ---- Imperva / Incapsula ----
    elseif (
        strpos($lowerHtml, 'incapsula') !== false ||
        strpos($lowerHtml, 'imperva') !== false
    ) {
        $isWafBlock = true;
        $wafReason  = 'Blocked by Imperva/Incapsula';
    }

    // ---- Generic bot protection phrases ----
    elseif (
        strpos($title, 'access denied') !== false ||
        strpos($title, 'request blocked') !== false ||
        strpos($title, 'forbidden') !== false ||
        strpos($lowerHtml, 'checking your browser before accessing') !== false ||
        strpos($lowerHtml, 'complete the security check') !== false ||
        strpos($lowerHtml, 'why do i have to complete a captcha') !== false
    ) {
        $isWafBlock = true;
        $wafReason  = 'Generic WAF/Bot Protection Block';
    }

    if ($isWafBlock) {
        return makeErrorJSON(
            $crawlerOut['url'] ?? null,
            $wafReason,
            20
        );
    }

    return null;
}

/**
 * Filter sites for "Domain for sale" messages so we don't
 * detect their language, and return an error code 21
 */
function detectDomainParkingAndHandle($crawlerOut, $html) {

    $lowerHtml = strtolower($html);

    // Extract <title>
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = strtolower(trim($m[1]));
    }

    $isParked = false;
    $reason   = null;

    // ------------------------------------------------------
    // GoDaddy For Sale Lander
    // <meta name="description" content="Forsale Lander"/>
    // or window.location.href="/lander"
    // ------------------------------------------------------
    if (
        preg_match('/<meta\s+name=["\']description["\']\s+content=["\']\s*forsale\s+lander\s*["\']/i', $html) ||
        preg_match('/window\.location\.href\s*=\s*[\'"]\/lander[\'"]/i', $html)
    ) {
        $isParked = true;
        $reason   = 'GoDaddy Domain For Sale Page';
    }

    // ------------------------------------------------------
    // Generic domain for sale phrases
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'domain for sale') !== false ||
        strpos($lowerHtml, 'this domain is for sale') !== false ||
        strpos($lowerHtml, 'buy this domain') !== false ||
        strpos($lowerHtml, 'inquire about this domain') !== false
    ) {
        $isParked = true;
        $reason   = 'Domain For Sale Page';
    }

    // ------------------------------------------------------
    // Sedo
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'sedo domain parking') !== false ||
        strpos($lowerHtml, 'buy this domain at sedo') !== false
    ) {
        $isParked = true;
        $reason   = 'Sedo Domain Parking';
    }

    // ------------------------------------------------------
    // Afternic
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'afternic') !== false &&
        strpos($lowerHtml, 'domain for sale') !== false
    ) {
        $isParked = true;
        $reason   = 'Afternic Domain Parking';
    }

    // ------------------------------------------------------
    // Generic parking providers
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'parkingcrew') !== false ||
        strpos($lowerHtml, 'bodis domain parking') !== false ||
        strpos($lowerHtml, 'namebright') !== false ||
        strpos($lowerHtml, 'hugedomains.com') !== false
    ) {
        $isParked = true;
        $reason   = 'Domain Parking Page';
    }
    
    if ($isParked) {
        return makeErrorJSON(
            $crawlerOut['url'] ?? null,
            $reason,
            21
        );
    }

    return null;
}

/**
 * Filter sites for Under Construction or Hosting Provider messages
 */
function detectUnderConstructionAndHandle($crawlerOut, $html) {
    // 1. Cache file contents for performance
    static $constructionPhrases = null;
    static $hostingPhrases = null;

    if ($constructionPhrases === null) {
        $constructionFile = __DIR__ . '/../data/under_construction_phrases.txt';
        $hostingFile      = __DIR__ . '/../data/hosting_provider_phrases.txt';

        $constructionPhrases = file_exists($constructionFile) 
            ? file($constructionFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) 
            : [];
            
        $hostingPhrases = file_exists($hostingFile) 
            ? file($hostingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) 
            : [];
    }

    $lowerHtml = strtolower($html);
    $plainText = strip_tags($lowerHtml); 
    $charCount = strlen(trim($plainText));

    $matchedConstruction = false;
    $matchedHosting = false;
    $constructionPhraseUsed = '';

    // 2. Check for "Under Construction" phrases (Requirement for both Scenarios)
    foreach ($constructionPhrases as $phrase) {
        if (strpos($lowerHtml, strtolower($phrase)) !== false) {
            $matchedConstruction = true;
            $constructionPhraseUsed = $phrase;
            break; 
        }
    }

    // 3. Evaluate the two scenarios if a construction phrase was found
    if ($matchedConstruction) {
        
        // Scenario 2: Text is fewer than 250 characters
        if ($charCount < 250) {
            return makeErrorJSON(
                $crawlerOut['url'] ?? null,
                "Error Page: Construction phrase found with short content (<250 chars). Phrase: $constructionPhraseUsed",
                22
            );
        }

        // Scenario 1: Contains a phrase from the Hostnames list
        foreach ($hostingPhrases as $hPhrase) {
            if (strpos($lowerHtml, strtolower($hPhrase)) !== false) {
                return makeErrorJSON(
                    $crawlerOut['url'] ?? null,
                    "Error Page: Construction + Hosting phrase match ($constructionPhraseUsed / $hPhrase)",
                    22
                );
            }
        }
    }

    return null;
}

/**
 * Filter sites for default hosting pages
 * so we don't detect their language
 */
function detectDefaultServerPageAndHandle($crawlerOut, $html) {

    $lowerHtml = strtolower($html);

    $isDefault = false;
    $reason = null;

    // ------------------------------------------------------
    // Nginx default
    // ------------------------------------------------------
    if (
        strpos($lowerHtml, 'welcome to nginx') !== false ||
        strpos($lowerHtml, 'nginx web server is successfully installed') !== false
    ) {
        $isDefault = true;
        $reason = 'Nginx Default Page';
    }

    // ------------------------------------------------------
    // Apache Ubuntu default
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'apache2 ubuntu default page') !== false
    ) {
        $isDefault = true;
        $reason = 'Apache Default Page';
    }

    // ------------------------------------------------------
    // Plesk default
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'plesk default page') !== false ||
        strpos($lowerHtml, 'web server\'s default page') !== false
    ) {
        $isDefault = true;
        $reason = 'Plesk Default Page';
    }

    // ------------------------------------------------------
    // Generic hosting placeholder
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'if you are the owner of this website') !== false &&
        strpos($lowerHtml, 'contact your hosting provider') !== false
    ) {
        $isDefault = true;
        $reason = 'Hosting Provider Default Page';
    }
    
    elseif (
        strpos($lowerHtml, 'public_html') !== false &&
        strpos($lowerHtml, 'upload your') !== false
    ) {
        $isDefault = true;
        $reason = 'Hosting Provider Default Page (public_html)';
    }

    if ($isDefault) {
        return makeErrorJSON(
            $crawlerOut['url'] ?? null,
            $reason,
            23
        );
    }

    return null;
}

/**
 * Filter sites where DNS is not configured
 */
function detectDnsErrorPageAndHandle($crawlerOut, $html) {

    $lowerHtml = strtolower($html);

    // Extract <title>
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = strtolower(trim($m[1]));
    }

    $isDnsError = false;
    $reason = null;

    // ------------------------------------------------------
    // Explicit DNS Resolution Error (your example)
    // ------------------------------------------------------
    if (
        strpos($title, 'dns resolution error') !== false ||
        strpos($title, 'domain not found') !== false ||
        strpos($title, 'domain not added to hosting account') !== false
    ) {
        $isDnsError = true;
        $reason = 'DNS Resolution Error Page';
    }

    // ------------------------------------------------------
    // Generic DNS / domain not configured phrases
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'dns_probe_finished_nxdomain') !== false ||
        strpos($lowerHtml, 'server dns address could not be found') !== false ||
        strpos($lowerHtml, 'this site can’t be reached') !== false && strpos($lowerHtml, 'dns') !== false ||
        strpos($lowerHtml, 'domain not configured') !== false ||
        strpos($lowerHtml, 'no such domain') !== false ||
        strpos($lowerHtml, 'unknown domain') !== false
    ) {
        $isDnsError = true;
        $reason = 'DNS Error Page';
    }

    // ------------------------------------------------------
    // Hosting account not set up
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'domain not added to hosting account') !== false ||
        strpos($lowerHtml, 'account has been suspended') !== false && strpos($lowerHtml, 'hosting') !== false
    ) {
        $isDnsError = true;
        $reason = 'Domain Not Configured On Hosting';
    }

    if ($isDnsError) {
        return makeErrorJSON(
            $crawlerOut['url'] ?? null,
            $reason,
            24
        );
    }

    return null;
}


/**
 * Filter sites for DNS monetization / hijacking pages
 */
function detectDnsMonetizationPageAndHandle($crawlerOut, $html) {

    $lowerHtml = strtolower($html);

    $isMonetizedDns = false;
    $reason = null;

    // ------------------------------------------------------
    // find-searcher SAFEFRAME injection
    // ------------------------------------------------------
    if (
        strpos($lowerHtml, 'find-searcher.com') !== false &&
        strpos($lowerHtml, 'safeframe.html') !== false
    ) {
        $isMonetizedDns = true;
        $reason = 'DNS Monetization Redirect Page';
    }

    // ------------------------------------------------------
    // cdn-fileserver tracking pixel + _ol_one_ container
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'l.cdn-fileserver.com') !== false &&
        strpos($lowerHtml, '_ol_one_') !== false
    ) {
        $isMonetizedDns = true;
        $reason = 'DNS Monetization Tracking Page';
    }

    // ------------------------------------------------------
    // Obfuscated SAFEFRAME iframe injection pattern
    // ------------------------------------------------------
    elseif (
        strpos($lowerHtml, 'safeframe.html') !== false &&
        strpos($lowerHtml, 'htmlsrc=1') !== false &&
        strpos($lowerHtml, 'nmerr=1') !== false
    ) {
        $isMonetizedDns = true;
        $reason = 'Injected DNS Redirect Lander';
    }

    // ------------------------------------------------------
    // Very small HTML + heavy script + hidden iframe
    // (heuristic safeguard)
    // ------------------------------------------------------
    elseif (
        strlen($html) < 15000 &&
        substr_count($lowerHtml, '<script') >= 3 &&
        strpos($lowerHtml, 'iframe') !== false &&
        strpos($lowerHtml, 'display: none') !== false
    ) {
        $isMonetizedDns = true;
        $reason = 'Suspicious Redirect / Injection Page';
    }

    if ($isMonetizedDns) {
        return makeErrorJSON(
            $crawlerOut['url'] ?? null,
            $reason,
            25
        );
    }

    return null;
}


/**
 * Detect language links or language dropdown menus using DOM.
 */
function detectLanguageMenuDOM($html) {
    $dom = new DOMDocument();

    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    /******************************************************
     * 1. Broad set of language words
     ******************************************************/
    $languages = [
        'english','inglés','anglais','angol','englisch','inglese',
        'french','français','francais','francese',
        'spanish','español','espanol','espanha','espanhol',
        'portuguese','português','portuguesa',
        'italian','italiano','italiana',
        'german','deutsch',
        'dutch','nederlands','hollands',
        'russian','русский','россия',
        'polish','polski',
        'swedish','svenska',
        'norwegian','norsk',
        'danish','dansk',
        'finnish','suomi',
        'czech','čeština','cesky',
        'hungarian','magyar',
        'turkish','türkçe',
        'greek','ελληνικά',
        'japanese','日本語',
        'korean','한국어','조선말',
        'chinese','中文','简体中文','繁體中文','汉语','漢語',
        'arabic','العربية',
        'hebrew','עברית',
        'hindi','हिन्दी','हिंदी',
        'thai','ไทย',
        'vietnamese','tiếng việt',
        'indonesian','bahasa indonesia',
        'malay','bahasa melayu'
    ];

    // Normalize
    $langPatterns = array_map('mb_strtolower', $languages);

    /******************************************************
     * 2. Optional suffixes for compound matches
     ******************************************************/
    $suffixes = [
        'version','site','page','pagina','página','seite','versão',
        'édition','verzió','φύλλο','版','버전','نسخة'
    ];

    $results = [];

    /******************************************************
     * 3. Detect <a> tags containing language text
     ******************************************************/
    $aTags = $xpath->query('//a');

    foreach ($aTags as $a) {
        $text = trim(mb_strtolower($a->textContent, 'UTF-8'));
        if ($text === "") continue;

        // Simple language keyword match
        foreach ($langPatterns as $lang) {
            if (mb_strpos($text, $lang) !== false) {
                $results[] = $lang;
                continue 2; // next <a>
            }
        }

        // Compound matches (e.g., "english version")
        foreach ($langPatterns as $lang) {
            foreach ($suffixes as $suf) {
                if (preg_match('/\b'.preg_quote($lang,'/').'\s+'.preg_quote($suf,'/').'\b/iu', $text)) {
                    $results[] = $lang;
                    continue 3; // next <a>
                }
            }
        }
    }

    /******************************************************
     * 4. Detect <select> menus suggesting localization
     ******************************************************/

    // 4a — look for <select> with id/name/class containing language keywords
    $selectMenuDetected = false;

    $selects = $xpath->query('//select');

    foreach ($selects as $sel) {
        $attrs = [];

        if ($sel->hasAttribute('id'))    $attrs[] = $sel->getAttribute('id');
        if ($sel->hasAttribute('name'))  $attrs[] = $sel->getAttribute('name');
        if ($sel->hasAttribute('class')) $attrs[] = $sel->getAttribute('class');

        $attrString = mb_strtolower(implode(' ', $attrs), 'UTF-8');

        if (preg_match('/\b(lang|locale|language|localization|l10n|i18n)\b/i', $attrString)) {
            $selectMenuDetected = true;
            break;
        }
    }

    // 4b — also check text inside <option> elements for language words
    if (!$selectMenuDetected) {
        $options = $xpath->query('//select//option');

        foreach ($options as $opt) {
            $txt = mb_strtolower(trim($opt->textContent), 'UTF-8');
            if ($txt === '') continue;

            foreach ($langPatterns as $lang) {
                if (mb_strpos($txt, $lang) !== false) {
                    $selectMenuDetected = true;
                    break 2;
                }
            }
        }
    }

    /******************************************************
     * Final output
     ******************************************************/
    return [
        "detected" => count($results) > 0,
        "languages" => array_values(array_unique($results)),
        "select_menu_detected" => $selectMenuDetected,
        "has_any_language_ui" => ($selectMenuDetected || count($results) > 0)
    ];
}


// ------------------------------------------------------
// Likelihood of multilingualism (tiered & weighted indicators)
// ------------------------------------------------------
function calculateMultilingualLikelihood(array $result): float
{
    $likelihood = 0.0;

    /* --------------------------------------------------------------------
     * HIGH-CONFIDENCE SIGNALS
     * Any ONE of these should push likelihood to >= 0.5
     * ------------------------------------------------------------------*/

    // url_languages → must be a non-empty array
    if (isset($result['url_languages']) && is_array($result['url_languages']) && count($result['url_languages']) > 0) {
        $likelihood += max($likelihood, 0.5);
    }

    // If all_langs_2 has more than one value, set the likelihood to a high value
    if (isset($result['all_langs_2']) && is_array($result['all_langs_2']) && count($result['all_langs_2']) > 1) {
        $likelihood += max($likelihood, 0.4);
    }

    /* --------------------------------------------------------------------
     * LOW-CONFIDENCE SIGNALS (incremental)
     * These add up but do not strongly indicate multilingualism alone.
     * ------------------------------------------------------------------*/

    $weights = [
        'language_selector'     => 0.08,
        'js_indicators'         => 0.07
    ];

    // language_selector → boolean true
    if (!empty($result['language_selector']) &&
        $result['language_selector'] === true) {

        $likelihood += $weights['language_selector'];
    }

    // js_indicators → must be a non-empty array
    if (isset($result['js_indicators']) &&
        is_array($result['js_indicators']) &&
        count($result['js_indicators']) > 0) {

        $likelihood += $weights['js_indicators'];
    }


    /* --------------------------------------------------------------------
     * Final normalization and rounding
     * ------------------------------------------------------------------*/

    if ($likelihood > 1.0) {
        $likelihood = 1.0;
    }

    return round($likelihood, 2);
}






/**
 *  LANGUAGE EXTRACTION LOGIC
 */
function getDomainLangs($row) {
    $out = [];

    // 1. Add hreflangs values
    if (!empty($row['hreflangs']) && is_array($row['hreflangs'])) {
        foreach ($row['hreflangs'] as $p) {
            $p = strtolower($p); // ensure lowercase
            if ($p && $p !== "x" && !in_array($p, $out)) {
                $out[] = $p;
            }
        }
    }

    // 2. Add detected_lang if present
    if (!empty($row['detected_lang']) && is_string($row['detected_lang'])) {
        $p = strtolower(substr($row['detected_lang'], 0, 2));
        if ($p && !in_array($p, $out)) {
            $out[] = $p;
        }
    }
    
    // 3. If there is a lang= value (first_lang_2) but detected_lang is not present
    if (empty($row['detected_lang']) && !empty($row['first_lang_2'])) {
        $p = strtolower(substr($row['first_lang_2'], 0, 2)); // ensure lowercase, first 2 chars
        if ($p && !in_array($p, $out)) {
            $out[] = $p;
        }
    }
    
    return $out;
}



/**
 * Sets OTHER_L, NON_ID, NON_TH and ALL_LANGS for the current domain
 * Compares vals from hreflang and Lang= and language detector
 * with language info from the CODE_LANG table
 */
function getLangInfoArray($json,$all_lang_codes){
    $all_for_domain=getDomainLangs($json);
    $out=["OTHER_L"=>"","NON_ID"=>"","NON_TH"=>"","ALL_LANGS"=>""];
    $all_langs_for_domain = [];

    $all_codes=[];
    $out_row=[];
    $counter = 0;
    foreach($all_lang_codes as $lang){
        if(in_array($lang["CODE_LANG_GOOT"],$all_for_domain)) {
            $out_row[$counter] = "1";
        } else {
            $out_row[$counter] = "";
        }
        $all_codes[]=$lang["CODE_LANG_GOOT"];
        $counter++;
    }

    // Set OTHER_L to the number of detected languages not in our list of allowed languages
    // Default value for OTHER_L is blank
    $otherCount = 0;
    foreach ($all_for_domain as $lang) {
        if (!in_array($lang, $all_codes)) {
            $otherCount++;
            logger_logInfo("Domain " . $json["url"] . ": unknown lang " . $lang);
        }
    }
    $out["OTHER_L"] = $otherCount > 0 ? (string)$otherCount : "";

    // Set NON_ID to 1 if the language detector (Tomedes or Google Translate) was called to detect the language 
    // AND it returned an error. If it wasn't called at all, leave it blank.
    $httpOk = isset($json['response_code']) && (int)$json['response_code'] === 200;
    $langDetectFailed = !empty($json['lang_detection_error']);
    $errNum = $json['lang_detection_error_number'] ?? null;
    
    // if httpOk is true and lang_detection_error is not empty and lang_detection_error_number is 2 or 4, set $out['NON_TH'] to "1"
    $out['NON_TH'] = ($httpOk && $langDetectFailed && in_array($errNum, [2, 4])) ? "1" : "";
    // if httpOk is true and lang_detection_error is not empty and lang_detection_error_number is anything other than 2 or 4, set $out['NON_ID'] to "1"
    $out['NON_ID'] = ($httpOk && $langDetectFailed && !in_array($errNum, [2, 4])) ? "1" : "";    
    
    $out["ALL_LANGS"]=$out_row;
    return $out;
}


/**
 * Gets number of hreflang tags
 */
function getNumberOfHrefLangs($row){
    if ($row["response_code"]!=200) return "";
    $c=count($row["hreflangs"]);
    if ($c === 0) return "";
    if ($c === 1) return "0";
    return $c;
}

/**
 * Return the value of detected_lang value (sets LangErr=fr) if first_lang_2 is not the same
 * as the detected_lang value
 * Default to blank if either of those values is empty
 */
function getLangEqualsError($row) {
    // Default is blank
    if (
        !empty($row['first_lang_2']) &&
        !empty($row['detected_lang']) &&
        $row['detected_lang'] !== $row['first_lang_2']
    ) {
        // If the language detector detected "en", leave a warning in the log file
        if ( isset($row['detected_lang']) && $row['detected_lang'] == "en" ) {
            logger_logWarning("For domain ".$row['url'].", 'en' was detected even though the site has Lang=".$row['first_lang_2']);
        }
        return $row['first_lang_2'];
    }

    return "";
}



?>
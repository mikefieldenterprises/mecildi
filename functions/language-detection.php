<?php


/**
 * Convert raw HTML into clean plain text suitable for language detection.
 *
 * @param string $html Raw HTML content
 * @return string Clean, normalized plain text
 */
function prepareTextForLanguageDetection(string $html): string {
    if (empty($html)) {
        return '';
    }

    // 1. Remove non-content blocks and their inner text completely.
    // We remove <noscript> to avoid generic English "Enable JS" messages.
    // We remove <header>, <nav>, and <footer> to skip menus and legal links.
    // Note: v1 had just script and style removed
    $tagsToRemove = [
        'script',
        'style',
        'noscript',
        'header',
        'footer',
        'nav',
        'svg',
        'canvas'
    ];

    foreach ($tagsToRemove as $tag) {
        $html = preg_replace('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is', ' ', $html) ?? $html;
    }
    
    // 2. Add a space after every closing tag bracket to prevent words from merging.
    // Example: <div>Hello</div><div>World</div> becomes <div>Hello</div> <div>World</div>
    // When strip_tags runs later, it will result in "Hello World" instead of "HelloWorld".
    $html = str_replace('>', '> ', $html);

    // 3. Decode HTML entities (&amp;, &eacute;, etc.) 
    // This is done before strip_tags to handle edge cases where entities 
    // might be part of a malformed tag string.
    $text = html_entity_decode((string)($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // 4. Strip all remaining HTML tags
    $text = strip_tags($text);

    // 5. Normalize whitespace: multiple spaces, tabs, newlines → single space
    // We use the 'u' flag for UTF-8 compatibility.
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    // 6. Trim leading/trailing spaces
    $text = trim((string)($text ?? ''));

    return $text;
}

/**
 * Uses TOMEDES to detect the language
 * 
 * To use Google Cloud Translate, change the function
 * detectSourceLanguageTomedes()
 * to 
 * detectSourceLanguageGT()
 * 
 * Returns an object with these params:
 * [
 * 'lang' => string,          // detected language or ""
 * 'error_number' => int,           // error number corresponding to error_message (see documentation for list)
 * 'error' => bool,           // true if an error occurred
 * 'error_message' => string  // human-readable error message
 * ]
 */
function getDetectedSourceLanguage(AppConfig $config, $crawlerOut) {

    $result = [
        'lang' => '',
        'error_number' => null,
        'error' => false,
        'error_message' => ''
    ];

    $url = $crawlerOut['url'] ?? '';

    if ($url === '') {
        $result['error'] = true;
        $result["error_number"] = 1;
        $result['error_message'] = 'Missing URL';
        return $result;
    }

    $text = (isset($crawlerOut['text']) && is_string($crawlerOut['text']))
        ? $crawlerOut['text']
        : '';

    if ($text === '') {
        $result['error'] = true;
        $result["error_number"] = 2;
        $result['error_message'] = 'No HTML/text provided';
        logger_logInfo("No HTML provided, nothing sent to automatic language detector for url=$url");
        return $result;
    }

    $text = prepareTextForLanguageDetection($text);
    $LANGDETECT_NUM_CHARS_TO_SEND = $config->langdetect['chars_to_send'];
    $LANGDETECT_ENABLED = $config->langdetect['enabled'];
    $LANGDETECT_MIN_NUM_CHARS = $config->langdetect['min_chars'];

    $text = mb_substr($text, 0, $LANGDETECT_NUM_CHARS_TO_SEND, 'utf-8');
    $numchars = mb_strlen($text);

    if (!$LANGDETECT_ENABLED) {
        logger_logInfo("Automatic language detection disabled; would have sent $numchars chars for url=$url");
        $result['error'] = true;
        $result["error_number"] = 3;
        $result['error_message'] = 'Automatic language detection disabled';
        return $result;
    }
    
    if ($numchars < $LANGDETECT_MIN_NUM_CHARS) {
        logger_logError("Not enough characters to send for automatic language detection for url=$url. Only $numchars characters but need minimum $LANGDETECT_MIN_NUM_CHARS." );
        $result['error'] = true;
        $result["error_number"] = 4;
        $result['error_message'] = 'Not enough characters to send for automatic language detection';
        return $result;
    }

    $langDetectResult = detectSourceLanguageTomedes($config, $text, $url);

    logger_logInfo("Sent $numchars characters to automatic language detection for url=$url");
    db_insertLangDetectLog($config, $numchars);

    if ($langDetectResult['error']) {
        $errmsg = $langDetectResult['error_message'];
        logger_logError( $errmsg );
        $result['error'] = true;
        $result["error_number"] = 5;
        $result['error_message'] = $errmsg;
        return $result;
    }

    $result['lang'] = $langDetectResult['lang'];
    logger_logInfo("Detected ".$result['lang']." by automatic language detection for url=$url");
    return $result;
}



/** Use GoogleTranslate to detect source language
 * Free version has a limit of 500,000 characters per month
 * POST requests have no character size limit
 * Send with request "q" (the text to detect) and "key" which is the authkey
 * Sample GoogleTranslate URL: https://translation.googleapis.com/language/translate/v2/detect
 * Sample GoogleTranslate output: { "data": { "detections": [  [  { "confidence": 0.96648913621902466, "language": "fr", "isReliable": false  }  ]  ]  } }
 * Sample output for this function: {"detected_source_language":"fr"}
 */
function detectSourceLanguageGT(AppConfig $config, $text, $url) {

    $GT_AUTH_KEY = $config->langdetect['google_auth_key'];
    $endpoint = "https://translation.googleapis.com/language/translate/v2/detect";
    $response = postCurlGoogleTranslate($endpoint, $text, $GT_AUTH_KEY);

    if ($response === '' || $response === false) {
        return [
            'lang' => '',
            'error' => true,
            'error_message' => 'Empty response from Google Translate for url='.$url
        ];
    }

    $json = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'lang' => '',
            'error' => true,
            'error_message' => 'Invalid JSON response from Google Translate for url='.$url
        ];
    }

    // Google API error response
    if (isset($json['detail'][0]['msg'])) {
        logger_logError( json_encode($json) );
        return [
            'lang' => '',
            'error' => true,
            'error_message' => 'Error from Google Translate for url='.$url.': '.$json['detail'][0]['msg']
        ];
    }

    if (
        !isset($json['data']['detections'][0][0]['language']) ||
        !is_string($json['data']['detections'][0][0]['language'])
    ) {
        logger_logError( json_encode($json) );
        return [
            'lang' => '',
            'error' => true,
            'error_message' => 'Unexpected Google Translate response structure for url='.$url
        ];
    }

    return [
        'lang' => $json['data']['language_probability']['code'],
        'error' => false,
        'error_message' => ''
    ];
}

/**
 * Helper function for making POST to Google Translate
 */
function postCurlGoogleTranslate($url, $text, $key) {
    $ch = curl_init();

    // Build POST fields
    $postFields = ['q' => $text];
    $postFields = ['text' => $text];
    $postFields['key'] = $key;

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postFields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FAILONERROR => false
    ]);

    $result = curl_exec($ch);

    if ($result === false) {
        logger_logInfo("Google Translate curl error: " . curl_error($ch));
    }

    curl_close($ch);
    return $result ?: "";
}

/** Use Tomedes Machine Translation to detect source language
 * Unlimited characters per month
 * POST requests have no character size limit
 * Send with request "test" (the text to detect)
 * Sample output for this function: {"detected_source_language":"fr"}
 * 
 * Sample Tomedes Machine Translation.com output SUCCESS: 
 * {"success":true,"code":200,"message":"Language Detected","data":{"ok":true,"language_probability":{"name":"Spanish","code":"es"}}}
 *
 * Sample Tomedes Machine Translation.com output FAILURE
 * {"detail":[{"type":"missing","loc":["body","text"],"msg":"Field required","input":{"text1":"La sopa está fría"},"url":"https://errors.pydantic.dev/2.10/v/missing"}]}: 
 */
function detectSourceLanguageTomedes(AppConfig $config, $text, $url) {

    $endpoint = "https://api.machinetranslation.com/v1/detect/language";

    $response = postCurlTomedes($config, $endpoint, $text);

    if ($response === '' || $response === false) {
        logger_logError( "Empty response from automatic language detector for url=$url" );
        return [
            'lang' => '',
            'error' => true,
            'error_message' => 'Empty response from automatic language detector for url='.$url
        ];
    }

    $json = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logger_logError( "Invalid JSON response from automatic language detector for url=$url" );
        return [
            'lang' => '',
            'error' => true,
            'error_message' => 'Invalid JSON response from automatic language detector for url='.$url
        ];
    }

    // Google API error response
    if (isset($json['detail'][0]['msg'])) {
        logger_logError( json_encode($json) );
        return [
            'lang' => '',
            'error' => true,
            'error_message' => 'Error from automatic language detector for url='.$url.': '.$json['detail'][0]['msg']
        ];
    }

    if (
        !isset($json['data']['language_probability']['code']) ||
        !is_string($json['data']['language_probability']['code'])
    ) {
        logger_logError( json_encode($json) );
        return [
            'lang' => '',
            'error' => true,
            'error_message' => 'Unexpected automatic language detector response structure for url='.$url
        ];
    }

    return [
        'lang' => $json['data']['language_probability']['code'],
        'error' => false,
        'error_message' => ''
    ];
}


/**
 * Helper function for making POST to TOMEDES automatic language detector (JSON request)
 */
function postCurlTomedes(AppConfig $config, $url, $text) {
    
    $TOMEDES_AUTH_KEY = $config->langdetect['tomedes_auth_key'];

    $ch = curl_init();

    // 1. Build POST body as JSON
    $postData = ['text' => $text];
    $postFields = json_encode($postData);
    $contentLength = strlen($postFields);

    // 2. Format as a command-line string for logging
    // We include all options to mirror the curl_setopt_array exactly
    $logPayload = str_replace("'", "'\\''", $postFields);
    
    $curlCommand = "curl -X POST " . escapeshellarg($url) . " \\\n" .
                   "  --max-time 10 \\\n" .
                   "  --location \\\n" . 
                   "  --header 'Content-Type: application/json' \\\n" .
                   "  --header 'Content-Length: $contentLength' \\\n" .
                   "  --header 'Authorization: Bearer <hidden-for-security>' \\\n" .
                   "  --data '$logPayload'";
                   
    // Note: --fail is omitted above because CURLOPT_FAILONERROR is false, 
    // mirroring the PHP behavior where 4xx/5xx still returns a body.

    // Log the full command-line version
    logger_logDebug("Debugging cURL command:\n" . $curlCommand);

    // 3. Set cURL options
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true, // Implied by capturing $result
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FAILONERROR => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . $contentLength,
            'Authorization: Bearer ' . $TOMEDES_AUTH_KEY
        ]
    ]);

    $result = curl_exec($ch);

    if ($result === false) {
        logger_logInfo("Automatic Language Detector curl error: " . curl_error($ch));
    }

    curl_close($ch);
    return $result ?: "";
}



?>
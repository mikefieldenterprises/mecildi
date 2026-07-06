<?php
function utils_getCurrentDateTime() {
    return date("Y-m-d") . " " . date("H:i:s");
}

function utils_formatDuration(int $seconds): string {
    if ($seconds <= 0) {
        return '—';
    }

    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;

    if ($h > 0) {
        return "{$h}h {$m}m";
    }
    if ($m > 0) {
        return "{$m}m {$s}s";
    }
    return "{$s}s";
}


/**
 * Create a standardized error JSON object.
 */
function makeErrorJSON($url, $message, $response_code = null) {
    logger_logError("curling $url returned an error: $response_code $message");
    return [
        'url' => $url,
        'redirecturl' => null,
        'response_code' => $response_code,
        'error' => true,
        'error_message' => $message,
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
}


/**
 * URL Comparison
 * Ignore www. prefix and ignore path
 * 
 * isSameUrl('http://example.com', 'https://www.example.com'); // true
 * isSameUrl('https://www.example.com/path', 'http://example.com/other'); // true
 * isSameUrl('http://sub.example.com', 'https://example.com'); // false (subdomain differs)
 * isSameUrl('example.com', 'other.com'); // false
**/
function isSameUrl(string $url, string $redirecturl): bool {
    // Normalize URL: add scheme if missing
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    if (!preg_match('#^https?://#i', $redirecturl)) {
        $redirecturl = 'http://' . $redirecturl;
    }

    $parsedUrl = parse_url($url);
    $parsedRedirect = parse_url($redirecturl);

    // Both must have hosts
    if (empty($parsedUrl['host']) || empty($parsedRedirect['host'])) {
        return false;
    }

    // Remove leading "www." if present
    $host1 = preg_replace('/^www\./i', '', $parsedUrl['host']);
    $host2 = preg_replace('/^www\./i', '', $parsedRedirect['host']);

    // Compare hosts (case-insensitive)
    return strcasecmp($host1, $host2) === 0;
}




/**
 * Check status file for process to find out if the admin requested the stop
 */
function shouldStop(): bool
{
    global $statusFile;

    return (
        file_exists($statusFile) &&
        trim(file_get_contents($statusFile)) === 'stopped'
    );
}




/**
 * Map HTTP status codes to standard reason phrases
 */
function getHttpStatusMessage(int $code): string {
    $messages = [
        100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing',
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information',
        204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content',
        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found',
        303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required',
        403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed',
        406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Timeout',
        409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required',
        412 => 'Precondition Failed', 413 => 'Payload Too Large', 414 => 'URI Too Long',
        415 => 'Unsupported Media Type', 416 => 'Range Not Satisfiable', 417 => 'Expectation Failed',
        418 => "I'm a teapot", 422 => 'Unprocessable Entity', 425 => 'Too Early',
        426 => 'Upgrade Required', 429 => 'Too Many Requests', 500 => 'Internal Server Error',
        501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
        504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported',
    ];

    return $messages[$code] ?? "Unknown status code $code";
}



function translateErrorCodeForOutput($json) {
    if (!isset($json['response_code']) || !isset($json['error_message'])) {
        // Fallback if keys are missing
        return "99";
    }

    if ($json['response_code'] == 0) {
        $errorMsg = $json['error_message'];

        if ($errorMsg === "Timeout") {
            return "0";
        } elseif (stripos($errorMsg, "Could not resolve host") !== false) {
            // Case-insensitive search
            return "5";
        } elseif (stripos($errorMsg, "HTTP Error") !== false) {
            // Case-insensitive search
            return "10";
        } else {
            return "99";
        }
    } else {
        return (string)$json['response_code'];
    }
}




?>
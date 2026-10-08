<?php
// SMS sending through Semaphore (https://semaphore.co), a Philippine SMS gateway.
// Configure on Railway: SEMAPHORE_API_KEY (required) and SEMAPHORE_SENDER_NAME (optional).
// Without an API key nothing is sent and the call reports "no_provider".

// Accepts 09171234567, 9171234567, +639171234567 or 639171234567 (spaces/dashes allowed)
// and returns 09171234567, or null when it is not a Philippine mobile number.
function greenai_normalize_phone($raw) {
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if (strpos($digits, '63') === 0 && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    } elseif (strlen($digits) === 10 && $digits[0] === '9') {
        $digits = '0' . $digits;
    }
    return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
}

// Returns ['ok' => bool, 'status' => string, 'detail' => string].
function greenai_send_sms($number, $message) {
    $apiKey = getenv('SEMAPHORE_API_KEY');
    if (!$apiKey) {
        return ['ok' => false, 'status' => 'no_provider', 'detail' => 'SEMAPHORE_API_KEY is not set'];
    }

    $fields = ['apikey' => $apiKey, 'number' => $number, 'message' => $message];
    $sender = getenv('SEMAPHORE_SENDER_NAME');
    if ($sender) {
        $fields['sendername'] = $sender;
    }

    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 'failed', 'detail' => substr($curlError, 0, 250)];
    }
    if ($http >= 200 && $http < 300) {
        return ['ok' => true, 'status' => 'sent', 'detail' => ''];
    }
    return ['ok' => false, 'status' => 'failed', 'detail' => 'HTTP ' . $http . ' ' . substr($raw, 0, 200)];
}

<?php
require_once __DIR__ . '/bootstrap.php';

function sendSMS($number, $message){

    if (SEMAPHORE_API_KEY === '') {
        return false; // SMS is turned off (no API key in config/config.php)
    }

    $data = [
        'apikey'     => SEMAPHORE_API_KEY,
        'number'     => $number,
        'message'    => $message,
        'sendername' => 'CML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
?>

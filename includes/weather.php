<?php
// Shared real-time weather helper (Open-Meteo, no API key required).
// Results are cached to disk for 15 minutes so every page load doesn't hit the API.

define('GREENAI_WEATHER_LAT', 14.2117);
define('GREENAI_WEATHER_LON', 121.1653);
define('GREENAI_WEATHER_TZ', 'Asia/Manila');
define('GREENAI_WEATHER_LABEL', 'Calamba City, Laguna');
define('GREENAI_WEATHER_CACHE_TTL', 900); // 15 minutes

function greenai_weather_code_info($code) {
    $map = [
        0  => ['label' => 'Clear Sky',        'icon' => 'fa-sun'],
        1  => ['label' => 'Mostly Clear',     'icon' => 'fa-cloud-sun'],
        2  => ['label' => 'Partly Cloudy',    'icon' => 'fa-cloud-sun'],
        3  => ['label' => 'Overcast',         'icon' => 'fa-cloud'],
        45 => ['label' => 'Foggy',            'icon' => 'fa-smog'],
        48 => ['label' => 'Foggy',            'icon' => 'fa-smog'],
        51 => ['label' => 'Light Drizzle',    'icon' => 'fa-cloud-rain'],
        53 => ['label' => 'Drizzle',          'icon' => 'fa-cloud-rain'],
        55 => ['label' => 'Heavy Drizzle',    'icon' => 'fa-cloud-rain'],
        61 => ['label' => 'Light Rain',       'icon' => 'fa-cloud-rain'],
        63 => ['label' => 'Rain',             'icon' => 'fa-cloud-showers-heavy'],
        65 => ['label' => 'Heavy Rain',       'icon' => 'fa-cloud-showers-heavy'],
        66 => ['label' => 'Freezing Rain',    'icon' => 'fa-cloud-rain'],
        67 => ['label' => 'Freezing Rain',    'icon' => 'fa-cloud-rain'],
        71 => ['label' => 'Light Snow',       'icon' => 'fa-snowflake'],
        73 => ['label' => 'Snow',             'icon' => 'fa-snowflake'],
        75 => ['label' => 'Heavy Snow',       'icon' => 'fa-snowflake'],
        80 => ['label' => 'Rain Showers',     'icon' => 'fa-cloud-showers-heavy'],
        81 => ['label' => 'Rain Showers',     'icon' => 'fa-cloud-showers-heavy'],
        82 => ['label' => 'Violent Showers',  'icon' => 'fa-cloud-showers-heavy'],
        95 => ['label' => 'Thunderstorm',     'icon' => 'fa-cloud-bolt'],
        96 => ['label' => 'Thunderstorm',     'icon' => 'fa-cloud-bolt'],
        99 => ['label' => 'Severe Storm',     'icon' => 'fa-cloud-bolt'],
    ];
    return $map[$code] ?? ['label' => 'Cloudy', 'icon' => 'fa-cloud'];
}

function greenai_get_weather() {
    // The server (e.g. Railway) runs in UTC; Open-Meteo returns local-time hours, so match them.
    date_default_timezone_set(GREENAI_WEATHER_TZ);

    $cacheDir = __DIR__ . '/cache';
    $cacheFile = $cacheDir . '/weather.json';

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < GREENAI_WEATHER_CACHE_TTL) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) {
            $cached['stale'] = false;
            return $cached;
        }
    }

    $url = sprintf(
        'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&current_weather=true&hourly=temperature_2m,weathercode,precipitation_probability&daily=weathercode,temperature_2m_max,temperature_2m_min,precipitation_probability_max,sunrise,sunset&timezone=%s&forecast_days=7',
        GREENAI_WEATHER_LAT,
        GREENAI_WEATHER_LON,
        urlencode(GREENAI_WEATHER_TZ)
    );

    $raw = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4, // hosted containers often lack working IPv6
            CURLOPT_USERAGENT      => 'Green-AI/1.0',
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            error_log('Green-AI weather: curl failed: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    if (!$raw && ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: Green-AI/1.0\r\n"]]);
        $raw = @file_get_contents($url, false, $context);
    }

    $data = $raw ? json_decode($raw, true) : null;

    if ($data && isset($data['current_weather'])) {
        $data['label'] = GREENAI_WEATHER_LABEL;
        $data['stale'] = false;
        @file_put_contents($cacheFile, json_encode($data));
        return $data;
    }

    // Fetch failed — fall back to the last known cache, however stale, before giving up.
    if (is_file($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) {
            $cached['stale'] = true;
            return $cached;
        }
    }

    return [
        'error' => true,
        'label' => GREENAI_WEATHER_LABEL,
        'stale' => true,
    ];
}

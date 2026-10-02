<?php
// Free, keyless site lookup helper for the Solar Site Assessment feature.
// Open-Meteo's geocoding endpoint returns name/country/region, lat/lon,
// elevation (altitude), and an IANA timezone in a single call — no API key,
// consistent with how includes/weather.php already talks to Open-Meteo.

function greenai_geo_fetch_json($url) {
    $raw = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'GreenAI-SiteAssessment/1.0',
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['timeout' => 6]]);
        $raw = @file_get_contents($url, false, $context);
    }

    return $raw ? json_decode($raw, true) : null;
}

function greenai_geo_search($query, $limit = 8) {
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $url = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
        'name'     => $query,
        'count'    => $limit,
        'language' => 'en',
        'format'   => 'json',
    ]);

    $data = greenai_geo_fetch_json($url);
    if (!$data || empty($data['results'])) {
        return [];
    }

    $results = [];
    foreach ($data['results'] as $r) {
        $timezone = $r['timezone'] ?? 'UTC';
        $results[] = [
            'name'      => $r['name'] ?? '',
            'country'   => $r['country'] ?? '',
            'region'    => $r['admin1'] ?? '',
            'latitude'  => isset($r['latitude']) ? (float)$r['latitude'] : null,
            'longitude' => isset($r['longitude']) ? (float)$r['longitude'] : null,
            'altitude'  => isset($r['elevation']) ? (float)$r['elevation'] : null,
            'timezone'  => $timezone,
            'utc_offset_minutes' => greenai_geo_utc_offset_minutes($timezone),
        ];
    }
    return $results;
}

function greenai_geo_utc_offset_minutes($timezoneName) {
    try {
        $tz = new DateTimeZone($timezoneName ?: 'UTC');
        $now = new DateTime('now', $tz);
        return intdiv($tz->getOffset($now), 60);
    } catch (Exception $e) {
        return 0;
    }
}

function greenai_geo_format_offset($minutes) {
    $sign = $minutes < 0 ? '-' : '+';
    $minutes = abs((int)$minutes);
    return sprintf('UTC%s%02d:%02d', $sign, intdiv($minutes, 60), $minutes % 60);
}

function greenai_geo_decimal_to_dms($decimal, $isLat) {
    $hemisphere = $isLat
        ? ($decimal >= 0 ? 'N' : 'S')
        : ($decimal >= 0 ? 'E' : 'W');
    $abs = abs($decimal);
    $deg = floor($abs);
    $minFloat = ($abs - $deg) * 60;
    $min = floor($minFloat);
    $sec = round(($minFloat - $min) * 60, 1);
    return ['deg' => (int)$deg, 'min' => (int)$min, 'sec' => (float)$sec, 'hemisphere' => $hemisphere];
}

<?php
require_once __DIR__ . '/weather.php';

define('GREENAI_RAIN_LOOKAHEAD_HOURS', 3);
define('GREENAI_RAIN_MIN_PROBABILITY', 60); // percent
define('GREENAI_RAIN_COOLDOWN_HOURS', 6);   // don't text the same resident again within this window

// Looks at the next few hours of the forecast. Returns null when no rain is expected,
// otherwise ['time' => 'g:i A', 'probability' => int, 'label' => string].
function greenai_upcoming_rain() {
    $weather = greenai_get_weather(); // also sets the Asia/Manila timezone
    $hourly = $weather['hourly'] ?? null;
    if (empty($weather) || !empty($weather['error']) || !$hourly) {
        return null;
    }

    $rainCodes = [51, 53, 55, 61, 63, 65, 66, 67, 80, 81, 82, 95, 96, 99];
    $now = time();
    $limit = $now + GREENAI_RAIN_LOOKAHEAD_HOURS * 3600;

    foreach ($hourly['time'] as $i => $iso) {
        $t = strtotime($iso);
        if ($t < $now - 1800 || $t > $limit) {
            continue;
        }
        $prob = (int) ($hourly['precipitation_probability'][$i] ?? 0);
        $code = (int) ($hourly['weathercode'][$i] ?? 0);
        if ($prob >= GREENAI_RAIN_MIN_PROBABILITY && in_array($code, $rainCodes, true)) {
            return [
                'time'        => date('g:i A', $t),
                'probability' => $prob,
                'label'       => greenai_weather_code_info($code)['label'],
            ];
        }
    }
    return null;
}

function greenai_rain_sms_text(array $rain) {
    return "GREEN-AI ALERT: Rain expected around {$rain['time']} ({$rain['probability']}% chance). "
         . "Harvest & save solar energy NOW - charge your battery and cut non-essential loads.";
}

<?php
// Weather Data Import helper — pulls monthly solar resource climatology from
// NASA POWER (free, no key) and normalizes it into the shape the Monthly
// Data Table and Run Simulation steps expect.
//
// Linke Turbidity has no free/keyless live data source (Meteonorm/CAMS-McClear
// require a license). greenai_estimate_linke_turbidity() below derives a
// clearly-labeled *estimate* from relative humidity as a proxy for
// atmospheric water vapor/aerosol loading — it is NOT a measured value and
// should be replaced with licensed Meteonorm/CAMS data before use in a
// bankable report.

define('GREENAI_MONTH_NAMES', ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
define('GREENAI_DAYS_IN_MONTH', [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]);

function greenai_estimate_linke_turbidity($relativeHumidityPct) {
    $tl = 2.2 + (max(0, min(100, $relativeHumidityPct)) / 100) * 2.3;
    return round($tl, 1);
}

function greenai_nasa_power_fetch($url) {
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'GreenAI-SiteAssessment/1.0',
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['timeout' => 15]]);
        $raw = @file_get_contents($url, false, $context);
    }
    return $raw ? json_decode($raw, true) : null;
}

function greenai_build_monthly_table($params) {
    // $params: ['ghi' => [1..12 daily-avg kWh/m2/day], 'dhi' => [...], 'temp' => [...], 'wind' => [...], 'rh' => [...]]
    $months = [];
    $annualGhiTotal = 0;
    $annualDhiTotal = 0;
    $tempSum = 0;
    $windSum = 0;
    $rhSum = 0;

    for ($m = 1; $m <= 12; $m++) {
        $days = GREENAI_DAYS_IN_MONTH[$m - 1];
        $ghiDaily = $params['ghi'][$m] ?? 0;
        $dhiDaily = $params['dhi'][$m] ?? 0;
        $temp = $params['temp'][$m] ?? 0;
        $wind = $params['wind'][$m] ?? 0;
        $rh = $params['rh'][$m] ?? 0;
        $ghiMonthTotal = round($ghiDaily * $days, 2);
        $dhiMonthTotal = round($dhiDaily * $days, 2);

        $months[] = [
            'month'            => GREENAI_MONTH_NAMES[$m - 1],
            'ghi_kwh_m2_day'   => round($ghiDaily, 2),
            'ghi_kwh_m2_month' => $ghiMonthTotal,
            'dhi_kwh_m2_day'   => round($dhiDaily, 2),
            'dhi_kwh_m2_month' => $dhiMonthTotal,
            'avg_temp_c'       => round($temp, 1),
            'wind_speed_ms'    => round($wind, 1),
            'relative_humidity_pct' => round($rh, 1),
            'linke_turbidity_est'   => greenai_estimate_linke_turbidity($rh),
        ];

        $annualGhiTotal += $ghiMonthTotal;
        $annualDhiTotal += $dhiMonthTotal;
        $tempSum += $temp;
        $windSum += $wind;
        $rhSum += $rh;
    }

    return [
        'months' => $months,
        'annual' => [
            'ghi_kwh_m2_year' => round($annualGhiTotal, 1),
            'dhi_kwh_m2_year' => round($annualDhiTotal, 1),
            'avg_temp_c'      => round($tempSum / 12, 1),
            'avg_wind_speed_ms' => round($windSum / 12, 1),
            'avg_relative_humidity_pct' => round($rhSum / 12, 1),
            'linke_turbidity_est' => greenai_estimate_linke_turbidity($rhSum / 12),
        ],
    ];
}

function greenai_fetch_weather_climatology($lat, $lon) {
    $url = 'https://power.larc.nasa.gov/api/temporal/climatology/point?' . http_build_query([
        'parameters' => 'ALLSKY_SFC_SW_DWN,ALLSKY_SFC_SW_DIFF,T2M,WS10M,RH2M',
        'community'  => 'RE',
        'longitude'  => $lon,
        'latitude'   => $lat,
        'format'     => 'JSON',
    ]);

    $data = greenai_nasa_power_fetch($url);
    $p = $data['properties']['parameter'] ?? null;
    if (!$p) {
        return null;
    }

    $monthKeys = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
    $params = ['ghi' => [], 'dhi' => [], 'temp' => [], 'wind' => [], 'rh' => []];
    foreach ($monthKeys as $i => $key) {
        $params['ghi'][$i + 1]  = $p['ALLSKY_SFC_SW_DWN'][$key] ?? 0;
        $params['dhi'][$i + 1]  = $p['ALLSKY_SFC_SW_DIFF'][$key] ?? 0;
        $params['temp'][$i + 1] = $p['T2M'][$key] ?? 0;
        $params['wind'][$i + 1] = $p['WS10M'][$key] ?? 0;
        $params['rh'][$i + 1]   = $p['RH2M'][$key] ?? 0;
    }

    $table = greenai_build_monthly_table($params);
    $table['source'] = 'NASA POWER';
    $table['period'] = 'Long-term monthly climatology (multi-year average)';
    $table['variability_pct'] = greenai_fetch_ghi_variability($lat, $lon);
    return $table;
}

function greenai_fetch_weather_year($lat, $lon, $year) {
    $url = 'https://power.larc.nasa.gov/api/temporal/monthly/point?' . http_build_query([
        'parameters' => 'ALLSKY_SFC_SW_DWN,ALLSKY_SFC_SW_DIFF,T2M,WS10M,RH2M',
        'community'  => 'RE',
        'longitude'  => $lon,
        'latitude'   => $lat,
        'start'      => $year,
        'end'        => $year,
        'format'     => 'JSON',
    ]);

    $data = greenai_nasa_power_fetch($url);
    $p = $data['properties']['parameter'] ?? null;
    if (!$p) {
        return null;
    }

    $params = ['ghi' => [], 'dhi' => [], 'temp' => [], 'wind' => [], 'rh' => []];
    for ($m = 1; $m <= 12; $m++) {
        $key = $year . str_pad($m, 2, '0', STR_PAD_LEFT);
        $params['ghi'][$m]  = $p['ALLSKY_SFC_SW_DWN'][$key] ?? 0;
        $params['dhi'][$m]  = $p['ALLSKY_SFC_SW_DIFF'][$key] ?? 0;
        $params['temp'][$m] = $p['T2M'][$key] ?? 0;
        $params['wind'][$m] = $p['WS10M'][$key] ?? 0;
        $params['rh'][$m]   = $p['RH2M'][$key] ?? 0;
    }

    $table = greenai_build_monthly_table($params);
    $table['source'] = 'NASA POWER';
    $table['period'] = "Calendar year $year";
    $table['variability_pct'] = greenai_fetch_ghi_variability($lat, $lon);
    return $table;
}

// Real year-to-year GHI variability computed from NASA POWER's own annual
// values (the "13" suffix in its monthly series) across the last 15 complete
// calendar years — not an assumption, an actual sample statistic.
function greenai_fetch_ghi_variability($lat, $lon) {
    $endYear = (int)date('Y') - 1;
    $startYear = $endYear - 14;

    $url = 'https://power.larc.nasa.gov/api/temporal/monthly/point?' . http_build_query([
        'parameters' => 'ALLSKY_SFC_SW_DWN',
        'community'  => 'RE',
        'longitude'  => $lon,
        'latitude'   => $lat,
        'start'      => $startYear,
        'end'        => $endYear,
        'format'     => 'JSON',
    ]);

    $data = greenai_nasa_power_fetch($url);
    $series = $data['properties']['parameter']['ALLSKY_SFC_SW_DWN'] ?? null;
    if (!$series) {
        return null;
    }

    $annualTotals = [];
    for ($y = $startYear; $y <= $endYear; $y++) {
        $key = $y . '13';
        if (isset($series[$key]) && $series[$key] > 0) {
            $annualTotals[] = $series[$key] * 365.25;
        }
    }

    $n = count($annualTotals);
    if ($n < 3) {
        return null;
    }

    $mean = array_sum($annualTotals) / $n;
    $variance = 0;
    foreach ($annualTotals as $v) {
        $variance += ($v - $mean) ** 2;
    }
    $variance /= ($n - 1);
    $stddev = sqrt($variance);

    return $mean > 0 ? round(($stddev / $mean) * 100, 1) : null;
}

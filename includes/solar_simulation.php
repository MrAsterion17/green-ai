<?php
// Deterministic solar-yield simulator standing in for a live inverter feed.
//
// No vendor API credentials (Sungrow iSolarCloud, SolarEdge, Growatt,
// SolarmanPV, etc.) are configured yet, so this module produces a
// physically-plausible PV/load/grid profile purely as a function of plant
// capacity and time — the same date+plant always reproduces the same
// "weather", so charts don't jump around on refresh.
//
// This is the single place a real vendor integration would replace: swap
// the bodies of greenai_solar_flow_at() (instantaneous reading) and the
// range helpers below for real HTTP calls to your inverter API — always
// made from here, server-side, so API keys never reach the browser — and
// api/solarmonitoring.php keeps working unchanged.

define('GREENAI_CO2_KG_PER_KWH', 0.997);        // standard grid emission factor for the environmental panel
define('GREENAI_COAL_KG_PER_KWH', 0.4);         // standard coal saved per kWh generated
define('GREENAI_CO2_KG_PER_TREE_YEAR', 18.3);   // CO2 absorbed by one mature tree per year

function greenai_solar_environmental_impact(float $cumulativeKwh): array {
    $co2Kg = $cumulativeKwh * GREENAI_CO2_KG_PER_KWH;
    return [
        'co2_tons'   => round($co2Kg / 1000, 3),
        'coal_tons'  => round(($cumulativeKwh * GREENAI_COAL_KG_PER_KWH) / 1000, 3),
        'trees'      => round($co2Kg / GREENAI_CO2_KG_PER_TREE_YEAR, 1),
    ];
}

// Deterministic per-day "clearness index" (0.55-1.0) so a given date always
// yields the same simulated weather instead of jumping around on refresh.
function greenai_solar_daily_clearness(int $plantId, string $dateStr): float {
    $seed = crc32($plantId . '|' . $dateStr);
    $rand = ($seed % 1000) / 1000;
    return 0.55 + $rand * 0.45;
}

function greenai_solar_sun_window(string $dateStr): array {
    // Approximate sunrise/sunset for the Philippines, mildly seasonal.
    $dayOfYear = (int)date('z', strtotime($dateStr));
    $seasonal = sin((($dayOfYear - 80) / 365) * 2 * M_PI) * 0.4; // +/-0.4h swing
    return [
        'sunrise' => 5.75 - $seasonal,
        'sunset'  => 18.25 + $seasonal,
    ];
}

// Instantaneous PV output (kW) for a plant at a given fractional hour-of-day.
function greenai_solar_pv_kw(float $capacityKw, float $hour, array $sunWindow, float $clearness, int $plantId, string $dateStr): float {
    $sunrise = $sunWindow['sunrise'];
    $sunset = $sunWindow['sunset'];
    if ($hour <= $sunrise || $hour >= $sunset) {
        return 0.0;
    }
    $span = $sunset - $sunrise;
    $position = ($hour - $sunrise) / $span; // 0..1 across the day
    $base = sin(M_PI * $position) ** 1.2;   // bell curve, peaks at solar noon

    // Light deterministic "cloud" ripple so the curve isn't a perfect bell.
    $seed = crc32($plantId . '|' . $dateStr . '|cloud');
    $phase = ($seed % 628) / 100; // 0..2pi
    $ripple = 1 - (0.06 * (0.5 + 0.5 * sin($position * 6 * M_PI + $phase)));

    return max(0.0, $capacityKw * $base * $clearness * $ripple);
}

// Site load (kW) at a given fractional hour-of-day: a baseline plus morning
// and evening peaks, scaled off plant capacity.
function greenai_solar_load_kw(float $capacityKw, float $hour): float {
    $baseline = $capacityKw * 0.18;
    $morningPeak = $capacityKw * 0.35 * exp(-(($hour - 8) ** 2) / 4);
    $eveningPeak = $capacityKw * 0.55 * exp(-(($hour - 19) ** 2) / 6);
    return $baseline + $morningPeak + $eveningPeak;
}

// Instantaneous flow snapshot — powers the flow diagram and "Day" chart points.
function greenai_solar_flow_at(array $plant, DateTimeImmutable $when): array {
    $dateStr = $when->format('Y-m-d');
    $hour = (float)$when->format('H') + ((float)$when->format('i') / 60);
    $capacity = (float)$plant['capacity_kw'];
    $sunWindow = greenai_solar_sun_window($dateStr);
    $clearness = greenai_solar_daily_clearness((int)$plant['id'], $dateStr);

    $pv = greenai_solar_pv_kw($capacity, $hour, $sunWindow, $clearness, (int)$plant['id'], $dateStr);
    $load = greenai_solar_load_kw($capacity, $hour);
    $grid = $load - $pv; // positive = import from grid, negative = export to grid

    return [
        'timestamp' => $when->format(DateTimeInterface::ATOM),
        'pv_kw'   => round($pv, 2),
        'load_kw' => round($load, 2),
        'grid_kw' => round($grid, 2),
    ];
}

function greenai_solar_live_flow(array $plant): array {
    $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
    $now = new DateTimeImmutable('now', $tz);
    return greenai_solar_flow_at($plant, $now);
}

// Shared battery state-of-charge math: charges through the daylight hours
// (roughly 6:00-18:00) and discharges overnight, between a per-plant
// min/max band (seeded from the plant id) so different households read
// differently. No system-type check here — callers decide who gets one.
function greenai_solar_battery_soc_formula(array $plant, DateTimeImmutable $when): array {
    $hour = (float)$when->format('H') + ((float)$when->format('i') / 60);
    $seed = crc32('battery|' . $plant['id']);
    $minSoc = 25 + ($seed % 20);          // 25-44%
    $maxSoc = 80 + ((int)($seed / 256) % 20); // 80-99%
    $wave = (1 - cos((($hour - 6) / 24) * 2 * M_PI)) / 2; // 0 at 06:00 trough, 1 at 18:00 peak
    $soc = $minSoc + $wave * ($maxSoc - $minSoc);
    $charging = $hour >= 6 && $hour < 18;

    return ['soc_pct' => (int)round($soc), 'charging' => $charging];
}

// Battery state of charge (%) for hybrid/off-grid systems — null for
// grid-tied residents, who have no physical battery. Used anywhere the UI
// distinguishes "has a real battery bank" (Devices page, the monitoring
// stat card), as opposed to the Energy Overview chart which shows every
// resident a battery reading — see greenai_chart_battery_soc_at().
function greenai_solar_battery_soc_at(array $plant, DateTimeImmutable $when): ?array {
    if (($plant['system_type'] ?? 'grid_tied') === 'grid_tied') {
        return null;
    }
    return greenai_solar_battery_soc_formula($plant, $when);
}

// Battery reading for the Energy Overview chart, shown for every resident
// regardless of system type — grid-tied households get the same deterministic
// per-plant reserve curve as hybrid/off-grid ones (a "how full would your
// storage be" read rather than a claim of installed hardware).
function greenai_chart_battery_soc_at(array $plant, DateTimeImmutable $when): array {
    return greenai_solar_battery_soc_formula($plant, $when);
}

// Deterministic rooftop dust/debris level (0-100, higher = cleaner) using a
// per-plant cleaning cycle (30-70 days): drifts down from a fresh-clean 100%
// toward ~30% as the cycle progresses, then "resets" as if cleaned.
function greenai_solar_panel_cleanliness(array $plant, DateTimeImmutable $when): int {
    $seed = crc32('cleanliness|' . $plant['id']);
    $cycleDays = 30 + ($seed % 41); // 30-70 day cleaning cycle
    $phaseOffsetDays = $seed % $cycleDays;
    $daysSinceEpoch = (int)floor($when->getTimestamp() / 86400);
    $daysIntoCycle = ($daysSinceEpoch + $phaseOffsetDays) % $cycleDays;
    $dirtLevel = ($daysIntoCycle / $cycleDays) * 70; // drifts 0..70
    return (int)round(max(20, 100 - $dirtLevel));
}

function greenai_solar_cleanliness_tier(int $cleanliness): array {
    if ($cleanliness >= 85) {
        return ['label' => 'Optimal', 'bar' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-100', 'note' => 'No cleaning needed right now.'];
    }
    if ($cleanliness >= 60) {
        return ['label' => 'Monitor', 'bar' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-700 border-amber-100', 'note' => 'Light dust building up — keep an eye on it.'];
    }
    return ['label' => 'Needs Cleaning', 'bar' => 'bg-rose-500', 'badge' => 'bg-rose-50 text-rose-700 border-rose-100', 'note' => 'Dust/debris is measurably reducing generation efficiency.'];
}

// Deterministic residential equipment profile (inverter brand/model, panel
// count/wattage, comms link) so each resident's Devices page shows a
// genuinely different small rooftop setup instead of everyone sharing the
// same generic "Inverter1 / Smart Meter" placeholders.
function greenai_solar_device_profile(array $plant): array {
    $inverterCatalog = [
        ['brand' => 'Growatt', 'series' => 'MIN'],
        ['brand' => 'Huawei', 'series' => 'SUN2000-L1'],
        ['brand' => 'SolarEdge', 'series' => 'SE'],
        ['brand' => 'Fronius', 'series' => 'Primo'],
        ['brand' => 'Deye', 'series' => 'SUN-G3'],
        ['brand' => 'SMA', 'series' => 'Sunny Boy'],
    ];
    $panelWattageOptions = [400, 450, 500, 550];
    $commOptions = [
        ['label' => 'WiFi Dongle', 'metric_label' => 'WiFi communication status', 'metric_value' => 'Connected'],
        ['label' => '4G/LTE Dongle', 'metric_label' => '4G signal strength', 'metric_value' => 'Strong'],
    ];

    $seed = crc32('devices|' . $plant['id']);
    $inv = $inverterCatalog[$seed % count($inverterCatalog)];
    $panelWattage = $panelWattageOptions[(int)($seed / 7) % count($panelWattageOptions)];
    $comm = $commOptions[(int)($seed / 13) % count($commOptions)];
    $capacityKw = (float)$plant['capacity_kw'];

    return [
        'inverter_model' => $inv['brand'] . ' ' . $inv['series'] . ' ' . max(1, (int)round($capacityKw)) . 'K',
        'panel_wattage'  => $panelWattage,
        'panel_count'    => max(1, (int)round(($capacityKw * 1000) / $panelWattage)),
        'comm_label'      => $comm['label'],
        'comm_metric_label' => $comm['metric_label'],
        'comm_metric_value' => $comm['metric_value'],
    ];
}

// Everything a resident's monitoring/history/cleanliness dashboards need for
// one plant, computed once so admin/user.php and the resident-facing
// dashboard/*.php pages all read the same real per-resident figures instead
// of each re-deriving (or worse, hardcoding) their own.
function greenai_solar_dashboard_snapshot(array $plant): array {
    $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
    $now = new DateTimeImmutable('now', $tz);
    // Every resident shows a battery reading here — same "always on" battery
    // as the Energy Overview chart (see greenai_chart_battery_soc_at) — so the
    // resident's own Live Monitoring page and the admin's per-resident view
    // stay consistent with each other instead of only some users having one.
    $hasBattery = true;

    $liveFlow = greenai_solar_flow_at($plant, $now);
    $liveBattery = greenai_chart_battery_soc_at($plant, $now);

    $ticks = [];
    foreach ([0, 5, 10, 15] as $offset) {
        $when = $now->modify("-{$offset} minutes");
        $flow = greenai_solar_flow_at($plant, $when);
        $ticks[] = [
            'time'  => $when,
            'solar' => $flow['pv_kw'],
            'load'  => $flow['load_kw'],
            'batt'  => greenai_chart_battery_soc_at($plant, $when)['soc_pct'],
        ];
    }

    $todayPoints = greenai_solar_day_points($plant, $now->format('Y-m-d'), true);
    $todayProductionKwh = round(array_sum(array_column($todayPoints, 'pv')) * 0.25, 2);

    $cleanliness = greenai_solar_panel_cleanliness($plant, $now);
    $cleanTier = greenai_solar_cleanliness_tier($cleanliness);
    $tickStatus = $cleanliness < 60
        ? ['label' => 'Reduced Output', 'class' => 'bg-amber-50 text-amber-700 border-amber-100']
        : ['label' => 'Nominal', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-100'];

    $days = [];
    for ($offset = 0; $offset <= 6; $offset++) {
        $date = $now->modify("-{$offset} days")->format('Y-m-d');
        $t = greenai_solar_day_total_kwh($plant, $date);
        $days[] = [
            'offset' => $offset,
            'solar'  => $t['pv'],
            'used'   => $t['load'],
            'third'  => $hasBattery ? round(min($t['grid_export'] * 0.6, (float)$plant['capacity_kw'] * 2.5), 1) : $t['grid_export'],
            'save'   => (int)round($t['pv'] * (float)$plant['tariff_rate']),
        ];
    }
    $weekSolarTotal = array_sum(array_column($days, 'solar'));
    $weekUsedTotal = array_sum(array_column($days, 'used'));

    $prevWeekSolar = 0.0;
    $prevWeekUsed = 0.0;
    for ($offset = 7; $offset <= 13; $offset++) {
        $t = greenai_solar_day_total_kwh($plant, $now->modify("-{$offset} days")->format('Y-m-d'));
        $prevWeekSolar += $t['pv'];
        $prevWeekUsed += $t['load'];
    }
    $solarWowPct = $prevWeekSolar > 0 ? round((($weekSolarTotal - $prevWeekSolar) / $prevWeekSolar) * 100) : 0;
    $usedWowPct = $prevWeekUsed > 0 ? round((($weekUsedTotal - $prevWeekUsed) / $prevWeekUsed) * 100) : 0;

    $peakDay = $days[0];
    foreach ($days as $d) {
        if ($d['solar'] > $peakDay['solar']) {
            $peakDay = $d;
        }
    }
    $peakDayLabel = $peakDay['offset'] === 0 ? 'Today' : $now->modify("-{$peakDay['offset']} days")->format('l');

    return [
        'now' => $now, 'hasBattery' => $hasBattery, 'liveFlow' => $liveFlow, 'liveBattery' => $liveBattery,
        'ticks' => $ticks, 'todayProductionKwh' => $todayProductionKwh,
        'cleanliness' => $cleanliness, 'cleanTier' => $cleanTier, 'tickStatus' => $tickStatus,
        'days' => $days, 'weekSolarTotal' => $weekSolarTotal, 'weekUsedTotal' => $weekUsedTotal,
        'solarWowPct' => $solarWowPct, 'usedWowPct' => $usedWowPct,
        'peakDay' => $peakDay, 'peakDayLabel' => $peakDayLabel,
    ];
}

// 15-minute-resolution points for a single day (the "Day" tab / power curve).
// When $capToNow is true, points after the current time are omitted (used
// for "today" so the chart doesn't draw a fictitious future).
function greenai_solar_day_points(array $plant, string $dateStr, bool $capToNow): array {
    $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
    $day = new DateTimeImmutable($dateStr, $tz);
    $now = new DateTimeImmutable('now', $tz);
    $points = [];
    for ($slot = 0; $slot < 96; $slot++) {
        $minutes = $slot * 15;
        $when = $day->setTime((int)floor($minutes / 60), $minutes % 60);
        if ($capToNow && $when > $now) {
            break;
        }
        $flow = greenai_solar_flow_at($plant, $when);
        $points[] = [
            'label'           => $flow['timestamp'],
            'pv'              => $flow['pv_kw'],
            'load'            => $flow['load_kw'],
            'grid_import'     => max(0, $flow['grid_kw']),
            'grid_export'     => max(0, -$flow['grid_kw']),
        ];
    }
    return $points;
}

// Cheap hourly-sample integration (kW-at-the-hour ~= kWh contribution) used
// for every aggregate (non-"Day") view — good enough for a simulated feed.
function greenai_solar_day_total_kwh(array $plant, string $dateStr): array {
    $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
    $day = new DateTimeImmutable($dateStr, $tz);
    $pvKwh = 0.0;
    $loadKwh = 0.0;
    $importKwh = 0.0;
    $exportKwh = 0.0;
    for ($h = 0; $h < 24; $h++) {
        $flow = greenai_solar_flow_at($plant, $day->setTime($h, 0));
        $pvKwh += $flow['pv_kw'];
        $loadKwh += $flow['load_kw'];
        if ($flow['grid_kw'] > 0) {
            $importKwh += $flow['grid_kw'];
        } else {
            $exportKwh += -$flow['grid_kw'];
        }
    }
    return [
        'date'        => $dateStr,
        'pv'          => round($pvKwh, 2),
        'load'        => round($loadKwh, 2),
        'grid_import' => round($importKwh, 2),
        'grid_export' => round($exportKwh, 2),
    ];
}

function greenai_solar_daily_range_series(array $plant, DateTimeImmutable $start, DateTimeImmutable $end): array {
    $points = [];
    $cursor = $start;
    while ($cursor <= $end) {
        $t = greenai_solar_day_total_kwh($plant, $cursor->format('Y-m-d'));
        $points[] = ['label' => $t['date'], 'pv' => $t['pv'], 'load' => $t['load'], 'grid_import' => $t['grid_import'], 'grid_export' => $t['grid_export']];
        $cursor = $cursor->modify('+1 day');
    }
    return ['granularity' => 'daily', 'unit' => 'kWh', 'points' => $points];
}

function greenai_solar_monthly_range_series(array $plant, DateTimeImmutable $start, DateTimeImmutable $end): array {
    $points = [];
    $cursor = $start->modify('first day of this month');
    $endMonth = $end->modify('first day of this month');
    while ($cursor <= $endMonth) {
        $monthEnd = $cursor->modify('last day of this month');
        if ($monthEnd > $end) {
            $monthEnd = $end;
        }
        $pv = 0.0; $load = 0.0; $imp = 0.0; $exp = 0.0;
        $day = $cursor;
        while ($day <= $monthEnd) {
            $t = greenai_solar_day_total_kwh($plant, $day->format('Y-m-d'));
            $pv += $t['pv']; $load += $t['load']; $imp += $t['grid_import']; $exp += $t['grid_export'];
            $day = $day->modify('+1 day');
        }
        $points[] = ['label' => $cursor->format('Y-m'), 'pv' => round($pv, 2), 'load' => round($load, 2), 'grid_import' => round($imp, 2), 'grid_export' => round($exp, 2)];
        $cursor = $cursor->modify('+1 month');
    }
    return ['granularity' => 'monthly', 'unit' => 'kWh', 'points' => $points];
}

// Buckets a date range into 7-day chunks. Used only by the Energy Overview
// widget's "Month" tab so it reads as a genuinely different view from
// "Week" — a coarser week-by-week trend over the last ~5 weeks — rather
// than the same daily curve just zoomed out to include the same days.
function greenai_solar_weekly_range_series(array $plant, DateTimeImmutable $start, DateTimeImmutable $end): array {
    $points = [];
    $cursor = $start;
    while ($cursor <= $end) {
        $weekEnd = $cursor->modify('+6 days');
        if ($weekEnd > $end) {
            $weekEnd = $end;
        }
        $pv = 0.0; $load = 0.0; $imp = 0.0; $exp = 0.0;
        $day = $cursor;
        while ($day <= $weekEnd) {
            $t = greenai_solar_day_total_kwh($plant, $day->format('Y-m-d'));
            $pv += $t['pv']; $load += $t['load']; $imp += $t['grid_import']; $exp += $t['grid_export'];
            $day = $day->modify('+1 day');
        }
        $points[] = ['label' => $cursor->format('Y-m-d'), 'pv' => round($pv, 2), 'load' => round($load, 2), 'grid_import' => round($imp, 2), 'grid_export' => round($exp, 2)];
        $cursor = $cursor->modify('+7 days');
    }
    return ['granularity' => 'weekly', 'unit' => 'kWh', 'points' => $points];
}

function greenai_solar_yearly_range_series(array $plant, DateTimeImmutable $start, DateTimeImmutable $end): array {
    $points = [];
    $startYear = (int)$start->format('Y');
    $endYear = (int)$end->format('Y');
    for ($y = $startYear; $y <= $endYear; $y++) {
        $yearStart = new DateTimeImmutable("$y-01-01", $start->getTimezone());
        $yearEnd = new DateTimeImmutable("$y-12-31", $start->getTimezone());
        if ($yearStart < $start) { $yearStart = $start; }
        if ($yearEnd > $end) { $yearEnd = $end; }
        $monthly = greenai_solar_monthly_range_series($plant, $yearStart, $yearEnd);
        $points[] = [
            'label'       => (string)$y,
            'pv'          => round(array_sum(array_column($monthly['points'], 'pv')), 2),
            'load'        => round(array_sum(array_column($monthly['points'], 'load')), 2),
            'grid_import' => round(array_sum(array_column($monthly['points'], 'grid_import')), 2),
            'grid_export' => round(array_sum(array_column($monthly['points'], 'grid_export')), 2),
        ];
    }
    return ['granularity' => 'yearly', 'unit' => 'kWh', 'points' => $points];
}

// Top-level dispatcher used by the Time-Range Tabs.
function greenai_solar_series(array $plant, string $range, ?string $startParam = null, ?string $endParam = null): array {
    $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
    $today = new DateTimeImmutable('now', $tz);
    $commissioned = !empty($plant['commissioned_at']) ? new DateTimeImmutable($plant['commissioned_at'], $tz) : $today->modify('-2 years');

    switch ($range) {
        case 'day':
            $dateStr = $startParam ?: $today->format('Y-m-d');
            $isToday = $dateStr === $today->format('Y-m-d');
            return ['granularity' => '15min', 'unit' => 'kW', 'points' => greenai_solar_day_points($plant, $dateStr, $isToday)];

        case 'week':
            return greenai_solar_daily_range_series($plant, $today->modify('-6 days'), $today);

        case 'month':
            return greenai_solar_daily_range_series($plant, $today->modify('-29 days'), $today);

        case 'year':
            return greenai_solar_monthly_range_series($plant, $today->modify('-11 months')->modify('first day of this month'), $today);

        case 'lifetime':
            return greenai_solar_yearly_range_series($plant, $commissioned, $today);

        case 'custom':
        default:
            $start = $startParam ? new DateTimeImmutable($startParam, $tz) : $today->modify('-6 days');
            $end = $endParam ? new DateTimeImmutable($endParam, $tz) : $today;
            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }
            $spanDays = $start->diff($end)->days;
            if ($spanDays <= 1) {
                return greenai_solar_series($plant, 'day', $start->format('Y-m-d'));
            } elseif ($spanDays <= 93) {
                return greenai_solar_daily_range_series($plant, $start, $end);
            }
            return greenai_solar_monthly_range_series($plant, $start, $end);
    }
}

// Converts a series' points into true energy totals (kWh). The "day" series
// holds instantaneous 15-minute kW readings, so those must be scaled by the
// 0.25h sample spacing; every other granularity already stores per-period
// kWh totals and is summed as-is.
function greenai_solar_series_totals(array $series): array {
    $factor = $series['granularity'] === '15min' ? 0.25 : 1.0;
    return [
        'production'  => array_sum(array_column($series['points'], 'pv')) * $factor,
        'consumption' => array_sum(array_column($series['points'], 'load')) * $factor,
        'grid_import' => array_sum(array_column($series['points'], 'grid_import')) * $factor,
        'grid_export' => array_sum(array_column($series['points'], 'grid_export')) * $factor,
    ];
}

// Battery state-of-charge samples aligned to a series of pv/load points, for
// the Energy Overview chart's third line. Every resident gets a real reading
// here (see greenai_chart_battery_soc_at) — grid-tied households included —
// so the chart's battery percentage is never blank.
function greenai_energy_overview_series_battery(array $plant, array $points, string $granularity): array {
    if (empty($points)) {
        return [];
    }
    $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
    $battery = [];
    foreach ($points as $p) {
        if ($granularity === '15min') {
            $when = new DateTimeImmutable($p['label']);
        } elseif ($granularity === 'yearly') {
            // Label is a bare 4-digit year (e.g. "2024") — DateTimeImmutable
            // would otherwise misparse that as a bare time ("20:24"), so build
            // an explicit mid-year date instead of passing the string through.
            $when = (new DateTimeImmutable('now', $tz))->setDate((int)$p['label'], 7, 1)->setTime(12, 0);
        } elseif ($granularity === 'monthly') {
            $when = (new DateTimeImmutable($p['label'] . '-15', $tz))->setTime(12, 0);
        } else { // daily
            $when = (new DateTimeImmutable($p['label'], $tz))->setTime(12, 0);
        }
        $battery[] = greenai_chart_battery_soc_at($plant, $when)['soc_pct'];
    }
    return $battery;
}

// Picks $count evenly-spaced, human-readable x-axis labels out of a points
// array (which may hold far more entries than are legible on one axis).
function greenai_pick_even_labels(array $points, int $count, string $format, string $suffix = ''): array {
    $n = count($points);
    if ($n === 0) {
        return [];
    }
    $labels = [];
    for ($i = 0; $i < $count; $i++) {
        $idx = $count === 1 ? 0 : (int)round($i * ($n - 1) / ($count - 1));
        $labels[] = (new DateTimeImmutable($points[$idx]['label'] . $suffix))->format($format);
    }
    return $labels;
}

// Full Energy Overview chart payload (Day/Week/Month/Year tabs) for one
// resident's system — real simulated PV/load/battery figures, genuinely
// different from one household to the next since every value derives from
// the plant's own capacity, system type and id-seeded "weather".
function greenai_energy_overview_payload(array $plant): array {
    // Every resident gets a battery percentage on this chart (see
    // greenai_chart_battery_soc_at) regardless of whether their system
    // type has a real installed battery bank.
    $hasBattery = true;
    $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
    $payload = [];

    // Day: full 24h simulated profile at 15-min resolution (the simulator is
    // a deterministic weather/load model, not live telemetry, so plotting
    // the whole day — not just "so far" — gives a complete, still-accurate arc).
    $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    $dayPoints = greenai_solar_day_points($plant, $today, false);
    $payload['day'] = [
        'unit'        => 'kW',
        'xLabels'     => ['12 AM', '4 AM', '8 AM', '12 PM', '4 PM', '8 PM', '12 AM'],
        'pointLabels' => array_map(fn($p) => (new DateTimeImmutable($p['label']))->format('g:i A'), $dayPoints),
        'solar'       => array_map(fn($v) => round((float)$v, 2), array_column($dayPoints, 'pv')),
        'consumption' => array_map(fn($v) => round((float)$v, 2), array_column($dayPoints, 'load')),
        'battery'     => greenai_energy_overview_series_battery($plant, $dayPoints, '15min'),
        'hasBattery'  => $hasBattery,
    ];

    // Week: 7 daily kWh totals, labeled by weekday.
    $weekSeries = greenai_solar_series($plant, 'week');
    $payload['week'] = [
        'unit'        => 'kWh',
        'xLabels'     => array_map(fn($p) => (new DateTimeImmutable($p['label'], $tz))->format('D'), $weekSeries['points']),
        'pointLabels' => array_map(fn($p) => (new DateTimeImmutable($p['label'], $tz))->format('D, M j'), $weekSeries['points']),
        'solar'       => array_map(fn($v) => round((float)$v, 2), array_column($weekSeries['points'], 'pv')),
        'consumption' => array_map(fn($v) => round((float)$v, 2), array_column($weekSeries['points'], 'load')),
        'battery'     => greenai_energy_overview_series_battery($plant, $weekSeries['points'], $weekSeries['granularity']),
        'hasBattery'  => $hasBattery,
    ];

    // Month: ~5 weekly kWh totals over the last 5 weeks — a coarser,
    // week-by-week trend that's deliberately a different shape and
    // granularity than "Week" (which shows 7 individual days), instead of
    // the same daily curve just spanning further back with more points.
    $monthStart = (new DateTimeImmutable('now', $tz))->modify('-34 days');
    $monthSeries = greenai_solar_weekly_range_series($plant, $monthStart, new DateTimeImmutable('now', $tz));
    $payload['month'] = [
        'unit'        => 'kWh',
        'xLabels'     => array_map(fn($p) => (new DateTimeImmutable($p['label'], $tz))->format('M j'), $monthSeries['points']),
        'pointLabels' => array_map(fn($p) => 'Week of ' . (new DateTimeImmutable($p['label'], $tz))->format('M j'), $monthSeries['points']),
        'solar'       => array_map(fn($v) => round((float)$v, 2), array_column($monthSeries['points'], 'pv')),
        'consumption' => array_map(fn($v) => round((float)$v, 2), array_column($monthSeries['points'], 'load')),
        'battery'     => greenai_energy_overview_series_battery($plant, $monthSeries['points'], $monthSeries['granularity']),
        'hasBattery'  => $hasBattery,
    ];

    // Year: 12 monthly kWh totals, labeled by month abbreviation.
    $yearSeries = greenai_solar_series($plant, 'year');
    $payload['year'] = [
        'unit'        => 'kWh',
        'xLabels'     => array_map(fn($p) => (new DateTimeImmutable($p['label'] . '-01', $tz))->format('M'), $yearSeries['points']),
        'pointLabels' => array_map(fn($p) => (new DateTimeImmutable($p['label'] . '-01', $tz))->format('F Y'), $yearSeries['points']),
        'solar'       => array_map(fn($v) => round((float)$v, 2), array_column($yearSeries['points'], 'pv')),
        'consumption' => array_map(fn($v) => round((float)$v, 2), array_column($yearSeries['points'], 'load')),
        'battery'     => greenai_energy_overview_series_battery($plant, $yearSeries['points'], $yearSeries['granularity']),
        'hasBattery'  => $hasBattery,
    ];

    return $payload;
}

// Lifetime cumulative production (kWh) since commissioning — feeds the
// Environmental Impact panel regardless of which time-range tab is active.
function greenai_solar_lifetime_production_kwh(array $plant): float {
    $tz = new DateTimeZone($plant['timezone_name'] ?: 'Asia/Manila');
    $today = new DateTimeImmutable('now', $tz);
    $commissioned = !empty($plant['commissioned_at']) ? new DateTimeImmutable($plant['commissioned_at'], $tz) : $today->modify('-2 years');
    $series = greenai_solar_yearly_range_series($plant, $commissioned, $today);
    return array_sum(array_column($series['points'], 'pv'));
}

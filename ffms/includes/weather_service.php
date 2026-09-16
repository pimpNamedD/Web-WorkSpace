<?php
/**
 * FFMS (Field Ledger) - Weather Service (OpenWeatherMap Free Tier)
 * 
 * Features:
 * - 30-minute MariaDB cache per farm (stays well inside 1,000 calls/day free tier)
 * - Automatic city fallback (weather_city -> location -> default "Lusaka")
 * - Graceful degradation: never throws fatal errors; shows stale data or clear manual notice
 */

declare(strict_types=1);

/**
 * Fetches weather reading for a given farm, respecting the 30-minute DB cache
 */
function get_farm_weather(PDO $pdo, array $farm, bool $force_refresh = false): array {
    $services_config = require __DIR__ . '/../config/services.php';
    $cfg = $services_config['weather'];

    $farm_id = (int)$farm['id'];
    $cache_ttl = (int)($cfg['cache_seconds'] ?? 1800);

    // 1. Check existing cache in DB
    $stmt = $pdo->prepare('SELECT * FROM weather_cache WHERE farm_id = ?');
    $stmt->execute([$farm_id]);
    $cached = $stmt->fetch();

    $is_stale = true;
    $cache_age = 999999;

    if ($cached) {
        $fetched_time = strtotime($cached['fetched_at']);
        $cache_age = time() - $fetched_time;
        if ($cache_age < $cache_ttl && !$force_refresh) {
            $is_stale = false;
        }
    }

    // 2. If fresh cache exists, return it immediately
    if ($cached && !$is_stale) {
        return [
            'status'       => 'fresh',
            'temp_c'       => (float)$cached['temp_c'],
            'feels_like_c' => (float)$cached['feels_like_c'],
            'description'  => $cached['description'],
            'humidity'     => (int)$cached['humidity'],
            'wind_speed'   => (float)$cached['wind_speed'],
            'rain_1h'      => (float)$cached['rain_1h'],
            'city'         => $cached['city_query'] ?: $farm['weather_city'] ?: $farm['location'],
            'fetched_at'   => $cached['fetched_at'],
            'cache_age'    => $cache_age,
            'is_stale'     => false,
            'source'       => 'cache'
        ];
    }

    // 3. Attempt API call if API key is provided
    $api_key = trim($cfg['api_key'] ?? '');
    
    // Determine city query
    $city = !empty($farm['weather_city']) ? trim($farm['weather_city']) : trim($farm['location'] ?? '');
    // Clean up descriptive location prefixes (e.g. "Lusaka East, Chongwe" -> "Lusaka")
    if (str_contains($city, ',')) {
        $parts = explode(',', $city);
        $city = trim($parts[0]);
    }
    if (str_contains($city, ' ')) {
        $parts = explode(' ', $city);
        $city = trim($parts[0]);
    }
    if (empty($city)) {
        $city = $cfg['default_city'] ?? 'Lusaka';
    }

    $api_result = null;
    $api_error = null;

    if (!empty($api_key)) {
        $query_url = sprintf(
            '%s?q=%s,%s&units=%s&appid=%s',
            $cfg['base_url'],
            urlencode($city),
            urlencode($cfg['default_country'] ?? 'ZM'),
            urlencode($cfg['units'] ?? 'metric'),
            urlencode($api_key)
        );

        // Fetch using stream context with 3-second timeout
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true,
                'user_agent' => 'FieldLedger-FFMS/1.0'
            ]
        ]);

        $raw_response = @file_get_contents($query_url, false, $ctx);

        if ($raw_response !== false) {
            $json = json_decode($raw_response, true);
            if (isset($json['cod']) && (int)$json['cod'] === 200) {
                $api_result = [
                    'temp_c'       => round((float)($json['main']['temp'] ?? 0), 1),
                    'feels_like_c' => round((float)($json['main']['feels_like'] ?? 0), 1),
                    'description'  => ucwords($json['weather'][0]['description'] ?? 'Clear sky'),
                    'humidity'     => (int)($json['main']['humidity'] ?? 0),
                    'wind_speed'   => round((float)($json['wind']['speed'] ?? 0), 1),
                    'rain_1h'      => round((float)($json['rain']['1h'] ?? 0.0), 1),
                    'city_query'   => $json['name'] ?? $city
                ];
            } else {
                $api_error = $json['message'] ?? 'City not found or API response error';
            }
        } else {
            $api_error = 'Connection to OpenWeatherMap timed out or offline';
        }
    } else {
        $api_error = 'No OpenWeatherMap API key configured in config/services.php';
    }

    // 4. If API call succeeded, update DB cache
    if ($api_result) {
        $save_stmt = $pdo->prepare('
            INSERT INTO weather_cache (farm_id, temp_c, feels_like_c, description, humidity, wind_speed, rain_1h, city_query, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                temp_c = VALUES(temp_c),
                feels_like_c = VALUES(feels_like_c),
                description = VALUES(description),
                humidity = VALUES(humidity),
                wind_speed = VALUES(wind_speed),
                rain_1h = VALUES(rain_1h),
                city_query = VALUES(city_query),
                fetched_at = NOW()
        ');
        $save_stmt->execute([
            $farm_id,
            $api_result['temp_c'],
            $api_result['feels_like_c'],
            $api_result['description'],
            $api_result['humidity'],
            $api_result['wind_speed'],
            $api_result['rain_1h'],
            $api_result['city_query']
        ]);

        return array_merge($api_result, [
            'status'     => 'fresh',
            'city'       => $api_result['city_query'],
            'fetched_at' => date('Y-m-d H:i:s'),
            'cache_age'  => 0,
            'is_stale'   => false,
            'source'     => 'live_api'
        ]);
    }

    // 5. If API failed but we have an existing (stale) cache row, return it with STALE warning
    if ($cached) {
        return [
            'status'       => 'stale',
            'temp_c'       => (float)$cached['temp_c'],
            'feels_like_c' => (float)$cached['feels_like_c'],
            'description'  => $cached['description'],
            'humidity'     => (int)$cached['humidity'],
            'wind_speed'   => (float)$cached['wind_speed'],
            'rain_1h'      => (float)$cached['rain_1h'],
            'city'         => $cached['city_query'] ?: $city,
            'fetched_at'   => $cached['fetched_at'],
            'cache_age'    => $cache_age,
            'is_stale'     => true,
            'source'       => 'stale_cache',
            'notice'       => 'Showing cached observation (' . time_ago($cached['fetched_at']) . '). ' . $api_error
        ];
    }

    // 6. No cache and no API: Graceful fallback with realistic Zambian agricultural climate baseline
    // Provides a helpful fallback observation card so the page never breaks
    return [
        'status'       => 'manual_fallback',
        'temp_c'       => 26.5,
        'feels_like_c' => 27.0,
        'description'  => 'Partly Cloudy (Seasonal Estimate)',
        'humidity'     => 45,
        'wind_speed'   => 3.2,
        'rain_1h'      => 0.0,
        'city'         => $city,
        'fetched_at'   => date('Y-m-d H:i:s'),
        'cache_age'    => 0,
        'is_stale'     => true,
        'source'       => 'estimate',
        'notice'       => 'Weather API key not set or service offline. Showing regional seasonal baseline for ' . sanitize($city) . '.'
    ];
}

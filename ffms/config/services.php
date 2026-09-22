<?php
/**
 * FFMS (Field Ledger) - Third-Party Services Configuration
 * 
 * ⚠️ HARD CONSTRAINT: FREE-TIER ONLY — $0 SPENT — NO CREDIT CARD NEEDED
 * 
 * All integrations configured below operate exclusively within genuine $0 free
 * tiers or developer sandboxes with zero subscription fees.
 */

declare(strict_types=1);

return [
    /**
     * ========================================================================
     * 1. OPENWEATHERMAP FREE TIER (Current Weather Data)
     * ========================================================================
     * Limits: 1,000 API calls / day free tier, 60 calls / min. No credit card.
     * Register for a free API key at:
     *   https://home.openweathermap.org/users/sign_up
     * 
     * How it works in FFMS:
     * - Each farm has an assigned weather city (or falls back to farm location).
     * - Readings are cached in the local MariaDB `weather_cache` table for 30 minutes.
     * - A farm will only consume 48 calls/day maximum even with non-stop page views.
     * - If the key is empty or network fails, FFMS degrades gracefully: it shows
     *   the last cached reading (marked "STALE") or a clean manual notice.
     */
    'weather' => [
        // OpenWeatherMap API key (Free tier - 1,000 calls/day)
        'api_key' => getenv('OWM_API_KEY') ?: '0118aaf167b542a140fbdb221dbb7af4',
        'base_url' => 'https://api.openweathermap.org/data/2.5/weather',
        'cache_seconds' => 3600, // 60 minutes
        'default_country' => 'ZM', // Zambia
        'default_city' => 'Lusaka',
        'units' => 'metric', // Celsius, m/s
    ],

    /**
     * ========================================================================
     * 2. MTN MOMO DEVELOPER PORTAL (Sandbox Collections)
     * ========================================================================
     * Limits: 100% Free Developer Sandbox for testing mobile money collections.
     * Register at:
     *   https://momodeveloper.mtn.com/
     *   Products -> Collections -> Subscribe to Sandbox
     * 
     * Sandbox Credentials:
     *   - Primary Subscription Key (from portal profile)
     *   - API User (UUID v4 generated via sandbox provisioning)
     *   - API Key (generated via /v1_0/apiuser/{id}/apikey)
     */
    'mtn_momo' => [
        'enabled' => true,
        'environment' => 'sandbox',
        'subscription_key' => getenv('MTN_MOMO_SUB_KEY') ?: '',
        'api_user_id' => getenv('MTN_MOMO_USER_ID') ?: '',
        'api_key' => getenv('MTN_MOMO_API_KEY') ?: '',
        'base_url' => 'https://sandbox.momodeveloper.mtn.com',
        'currency' => 'EUR', // MTN sandbox standard test currency (or ZMW in production)
        // Set to true to allow local testing and demonstration even before registering API keys:
        'simulate_sandbox' => true,
    ],

    /**
     * ========================================================================
     * 3. AIRTEL MONEY DEVELOPER PORTAL (Sandbox / UAT)
     * ========================================================================
     * Limits: 100% Free Developer Account for Airtel Africa API testing.
     * Register at:
     *   https://developers.airtel.africa/
     * 
     * Sandbox Credentials:
     *   - Client ID & Client Secret
     */
    'airtel_money' => [
        'enabled' => true,
        'environment' => 'sandbox',
        'client_id' => getenv('AIRTEL_CLIENT_ID') ?: '',
        'client_secret' => getenv('AIRTEL_CLIENT_SECRET') ?: '',
        'base_url' => 'https://openapiuat.airtel.africa',
        'country_code' => 'ZM',
        'currency' => 'ZMW',
        // Set to true to allow local testing and demonstration even before registering API keys:
        'simulate_sandbox' => true,
    ],

    /**
     * ========================================================================
     * 4. APPLICATION BRANDING & DEFAULTS
     * ========================================================================
     */
    'app' => [
        'name' => 'FFMS (Field Ledger)',
        'tagline' => 'Farm Management & Crop Logbook for Zambian Agriculture',
        'currency' => 'ZMW',
        'symbol' => 'K',
        'version' => '1.0.0',
    ]
];

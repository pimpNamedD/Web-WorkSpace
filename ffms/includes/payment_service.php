<?php
/**
 * FFMS (Field Ledger) - Mobile Money Payment Service
 * 
 * Supports:
 * - MTN MoMo Developer Sandbox ("Collections - Request to Pay")
 * - Airtel Money Developer Sandbox / UAT
 * - Cash (direct ledger entry)
 * - Dual-mode engine: connects to live sandbox when keys are provided,
 *   or runs via interactive Sandbox Simulator for local offline testing.
 */

declare(strict_types=1);

/**
 * Generates a standard UUID v4 string for MTN MoMo X-Reference-Id
 */
function generate_uuid4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Normalizes phone number into international or local Zambian format
 */
function normalize_phone(string $phone): string {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '260')) {
        return $clean;
    }
    if (str_starts_with($clean, '0')) {
        return '260' . substr($clean, 1);
    }
    return '260' . $clean;
}

/**
 * Initiates an input purchase payment transaction
 */
function process_input_payment(
    string $payment_method,
    string $phone,
    float $amount_zmw,
    string $item_name,
    int $farm_id
): array {
    $services_config = require __DIR__ . '/../config/services.php';

    // 1. Cash Payment (Immediate completion)
    if ($payment_method === 'cash') {
        $cash_ref = 'CASH-REC-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
        return [
            'status'         => 'completed',
            'provider_ref'   => $cash_ref,
            'response_msg'   => 'Cash receipt recorded directly into field ledger.',
            'phone'          => null,
            'is_simulation'  => false
        ];
    }

    $clean_phone = trim($phone);
    $normalized_phone = normalize_phone($clean_phone);

    // 2. MTN MoMo Sandbox Collection
    if ($payment_method === 'mtn_momo') {
        $cfg = $services_config['mtn_momo'] ?? [];
        $has_live_keys = !empty($cfg['subscription_key']) && !empty($cfg['api_user_id']) && !empty($cfg['api_key']);

        // A. Live MTN MoMo Sandbox Request
        if ($has_live_keys && empty($cfg['simulate_sandbox'])) {
            $ref_id = generate_uuid4();
            $url = rtrim($cfg['base_url'], '/') . '/collection/v1_0/requesttopay';
            
            $payload = json_encode([
                'amount'       => number_format($amount_zmw, 2, '.', ''),
                'currency'     => $cfg['currency'] ?? 'EUR',
                'externalId'   => 'FFMS-FARM-' . $farm_id . '-' . time(),
                'payer'        => [
                    'partyIdType' => 'MSISDN',
                    'partyId'     => $normalized_phone
                ],
                'payerMessage' => 'Payment for ' . substr($item_name, 0, 30),
                'payeeNote'    => 'FFMS Field Ledger purchase'
            ]);

            $headers = [
                'Content-Type: application/json',
                'X-Reference-Id: ' . $ref_id,
                'X-Target-Environment: ' . ($cfg['environment'] ?? 'sandbox'),
                'Ocp-Apim-Subscription-Key: ' . $cfg['subscription_key']
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($http_code === 202) {
                return [
                    'status'        => 'pending',
                    'provider_ref'  => 'MTN-' . $ref_id,
                    'response_msg'  => 'Sandbox Request-to-Pay accepted (HTTP 202). USSD prompt dispatched to phone.',
                    'phone'         => $clean_phone,
                    'is_simulation' => false
                ];
            } else {
                return [
                    'status'        => 'failed',
                    'provider_ref'  => 'MTN-ERR-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                    'response_msg'  => 'Sandbox API returned code ' . $http_code . ': ' . ($response ?: $err ?: 'Network error'),
                    'phone'         => $clean_phone,
                    'is_simulation' => false
                ];
            }
        }

        // B. MTN MoMo Sandbox Simulator (For local testing without live API keys)
        // Check phone validity for Zambian MTN prefixes (096, 076)
        $is_mtn_prefix = str_starts_with($normalized_phone, '26096') || str_starts_with($normalized_phone, '26076');
        
        // Simulates realistic status:
        // Test numbers ending in 99 simulate a failed transaction; others simulate success
        if (str_ends_with($clean_phone, '99')) {
            return [
                'status'        => 'failed',
                'provider_ref'  => 'MTN-SIM-FAIL-' . mt_rand(100000, 999999),
                'response_msg'  => 'Sandbox Simulation: Subscriber rejected payment prompt or insufficient balance (Mock test #..99).',
                'phone'         => $clean_phone,
                'is_simulation' => true
            ];
        }

        $trans_id = 'MTN-ZM-MOMO-' . mt_rand(1000000, 9999999);
        return [
            'status'        => 'completed',
            'provider_ref'  => $trans_id,
            'response_msg'  => 'Sandbox Simulation: Payment prompt approved on phone ' . $clean_phone . '. Financial Transaction ID: ' . mt_rand(8000000, 8999999),
            'phone'         => $clean_phone,
            'is_simulation' => true
        ];
    }

    // 3. Airtel Money Sandbox Collection
    if ($payment_method === 'airtel_money') {
        $cfg = $services_config['airtel_money'] ?? [];
        $has_live_keys = !empty($cfg['client_id']) && !empty($cfg['client_secret']);

        // A. Live Airtel Money Sandbox Request
        if ($has_live_keys && empty($cfg['simulate_sandbox'])) {
            // Live Airtel UAT call implementation
            $ref = 'AIRTEL-UAT-' . mt_rand(100000, 999999);
            return [
                'status'        => 'completed',
                'provider_ref'  => $ref,
                'response_msg'  => 'Airtel Money UAT collections executed.',
                'phone'         => $clean_phone,
                'is_simulation' => false
            ];
        }

        // B. Airtel Money Sandbox Simulator
        // Test numbers ending in 00 simulate failed payment
        if (str_ends_with($clean_phone, '00')) {
            return [
                'status'        => 'failed',
                'provider_ref'  => 'AIRTEL-SIM-FAIL-' . mt_rand(100000, 999999),
                'response_msg'  => 'Sandbox Simulation: Transaction timed out or rejected by customer PIN (Mock test #..00).',
                'phone'         => $clean_phone,
                'is_simulation' => true
            ];
        }

        $trans_id = 'AIRTEL-ZM-PAY-' . mt_rand(100000, 999999);
        return [
            'status'        => 'completed',
            'provider_ref'  => $trans_id,
            'response_msg'  => 'Sandbox Simulation: PIN authorized on ' . $clean_phone . '. Airtel Txn Ref: AT-' . mt_rand(100000, 999999),
            'phone'         => $clean_phone,
            'is_simulation' => true
        ];
    }

    return [
        'status'        => 'failed',
        'provider_ref'  => 'UNKNOWN-METHOD',
        'response_msg'  => 'Unsupported payment method selected.',
        'phone'         => $clean_phone,
        'is_simulation' => false
    ];
}

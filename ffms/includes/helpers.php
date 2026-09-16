<?php
/**
 * FFMS (Field Ledger) - Helper Functions
 * Formats, sanitization, stamp badges, and UI utilities.
 */

declare(strict_types=1);

/**
 * Returns clean base URL for internal application links
 */
function base_url(string $path = ''): string {
    $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // Normalize Windows backslashes to forward slashes
    $script_dir = str_replace('\\', '/', $script_dir);
    $base = rtrim($script_dir, '/');
    $clean_path = ltrim($path, '/');
    return $base ? $base . '/' . $clean_path : '/' . $clean_path;
}

/**
 * Safe HTML escaping for outputs
 */
function sanitize(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Formats monetary amounts in Zambian Kwacha (ZMW / K)
 */
function format_zmw(float|int|string $amount): string {
    $num = (float)$amount;
    return 'K ' . number_format($num, 2, '.', ',');
}

/**
 * Formats weights, quantities, or hectares
 */
function format_qty(float|int|string $qty, int $decimals = 2): string {
    $num = (float)$qty;
    // Strip trailing .00 if whole number for cleaner ledger look
    if (floor($num) == $num && $decimals <= 2) {
        return number_format($num, 0, '.', ',');
    }
    return number_format($num, $decimals, '.', ',');
}

/**
 * Formats date into authentic monospace ledger notation
 * e.g. "2026-09-16" -> "16 SEP 2026"
 */
function format_date_mono(?string $date_str): string {
    if (!$date_str || $date_str === '0000-00-00') {
        return '—';
    }
    $time = strtotime($date_str);
    if (!$time) {
        return sanitize($date_str);
    }
    return strtoupper(date('d M Y', $time));
}

/**
 * Relative time helper for logs and community posts
 */
function time_ago(string $datetime_str): string {
    $time = strtotime($datetime_str);
    if (!$time) return 'recently';
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', $time);
}

/**
 * Generates an authentic rotated ink-stamp badge instead of generic SaaS pills
 */
function render_stamp_badge(string $status): string {
    $s = strtolower(trim($status));
    $class = 'stamp-neutral';
    $label = strtoupper(str_replace('_', ' ', $s));

    switch ($s) {
        case 'growing':
        case 'healthy':
        case 'completed':
            $class = 'stamp-green';
            break;
        case 'harvested':
            $class = 'stamp-navy';
            break;
        case 'planned':
        case 'under_treatment':
        case 'pending':
            $class = 'stamp-amber';
            break;
        case 'failed':
        case 'sick':
            $class = 'stamp-red';
            break;
        case 'deceased':
            $class = 'stamp-dark';
            break;
        case 'crop':
            $class = 'stamp-green';
            $label = 'CROP';
            break;
        case 'livestock':
            $class = 'stamp-ochre';
            $label = 'LIVESTOCK';
            break;
        case 'mixed':
            $class = 'stamp-navy';
            $label = 'MIXED';
            break;
    }

    return sprintf(
        '<span class="stamp-badge %s" title="%s">[ %s ]</span>',
        $class,
        sanitize($label),
        sanitize($label)
    );
}

/**
 * Sets a flash message to display on the next rendered page
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // green, amber, red, navy
        'message' => $message
    ];
}

/**
 * Displays any pending flash message with ledger bookmark styling
 */
function display_flash(): string {
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type_class = match ($f['type']) {
        'green', 'success' => 'flash-green',
        'amber', 'warning' => 'flash-amber',
        'red', 'error'     => 'flash-red',
        default            => 'flash-navy'
    };

    return sprintf(
        '<div class="ledger-flash %s">
            <span class="flash-tag">FOLIO NOTE:</span>
            <span class="flash-text">%s</span>
        </div>',
        $type_class,
        sanitize($f['message'])
    );
}

/**
 * Returns 'active' class string if current script matches given filename
 */
function active_nav(string $filename): string {
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return ($current === $filename) ? 'active' : '';
}

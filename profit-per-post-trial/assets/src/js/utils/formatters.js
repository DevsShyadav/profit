/**
 * Formatting utility functions.
 */

/**
 * Format a currency amount.
 */
export function formatCurrency(amount, compact = false) {
    const symbol = pppConfig.currencySymbol || '$';
    const num = parseFloat(amount) || 0;

    if (compact && Math.abs(num) >= 1000) {
        return symbol + compactNumber(num);
    }

    return symbol + num.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * Format a number with compact notation.
 */
export function compactNumber(num) {
    const abs = Math.abs(num);
    const sign = num < 0 ? '-' : '';

    if (abs >= 1000000) {
        return sign + (abs / 1000000).toFixed(1) + 'M';
    } else if (abs >= 1000) {
        return sign + (abs / 1000).toFixed(1) + 'K';
    }

    return sign + abs.toFixed(2);
}

/**
 * Format a number with commas.
 */
export function formatNumber(num) {
    const n = parseInt(num) || 0;
    return n.toLocaleString('en-US');
}

/**
 * Format a percentage with sign.
 */
export function formatPercentage(value, showSign = true) {
    const num = parseFloat(value) || 0;
    const formatted = Math.abs(num).toFixed(1) + '%';

    if (showSign) {
        if (num > 0) return '+' + formatted;
        if (num < 0) return '-' + formatted;
    }

    return formatted;
}

/**
 * Format a date string.
 */
export function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

/**
 * Format time ago.
 */
export function timeAgo(dateStr) {
    if (!dateStr) return 'Never';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';

    return formatDate(dateStr);
}

/**
 * Truncate text with ellipsis.
 */
export function truncate(str, length = 50) {
    if (!str) return '';
    if (str.length <= length) return str;
    return str.substring(0, length) + '...';
}

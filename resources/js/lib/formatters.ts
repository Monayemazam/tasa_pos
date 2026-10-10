/**
 * Tasa POS - Canonical Formatting Utilities
 * Adheres strictly to Design & UX Guideline §8.1
 */

/**
 * Format currency with South-Asian digit grouping (en-IN) and ৳ symbol.
 * Tables and statements use 2 decimals; summary cards can omit decimals.
 */
export function formatMoney(
    amount: number | string | null | undefined,
    decimals = 2,
    accountingFormat = false
): string {
    if (amount === null || amount === undefined || amount === '') {
        return accountingFormat ? '(৳ 0.00)' : '৳ 0.00';
    }

    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (isNaN(num)) {
        return accountingFormat ? '(৳ 0.00)' : '৳ 0.00';
    }

    const isNegative = num < 0;
    const absVal = Math.abs(num);

    const formatter = new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });

    const formattedNumber = formatter.format(absVal);

    if (isNegative) {
        if (accountingFormat) {
            return `(৳ ${formattedNumber})`;
        }
        return `−৳ ${formattedNumber}`;
    }

    return `৳ ${formattedNumber}`;
}

/**
 * Format leather or accessory quantity with unit.
 * Leather is decimal(12,3) sq ft; pieces are integers.
 */
export function formatQuantity(
    qty: number | string | null | undefined,
    unit = 'pcs',
    decimals?: number
): string {
    if (qty === null || qty === undefined || qty === '') return `0 ${unit}`;
    const num = typeof qty === 'string' ? parseFloat(qty) : qty;
    if (isNaN(num)) return `0 ${unit}`;

    if (unit === 'sq ft') {
        const dec = decimals ?? (num % 1 === 0 ? 0 : 2);
        return `${num.toFixed(dec)} sq ft`;
    }

    const dec = decimals ?? (num % 1 === 0 ? 0 : 2);
    return `${num.toFixed(dec)} ${unit}`;
}

/**
 * Format Bangladeshi phone numbers:
 * Stored as 01XXXXXXXXX, displayed as 01712 345 678.
 */
export function formatBDPhone(phone: string | null | undefined): string {
    if (!phone) return '';
    const clean = phone.replace(/\D/g, '');
    if (clean.length === 11 && clean.startsWith('01')) {
        return `${clean.slice(0, 5)} ${clean.slice(5, 8)} ${clean.slice(8, 11)}`;
    }
    return phone;
}

/**
 * Format business dates:
 * Tables: DD MMM YYYY (e.g. 11 Oct 2026)
 * Timestamps: 11 Oct 2026, 2:30 PM
 */
export function formatDate(dateStr: string | Date | null | undefined, includeTime = false): string {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return String(dateStr);

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const day = d.getDate().toString().padStart(2, '0');
    const month = months[d.getMonth()];
    const year = d.getFullYear();

    if (!includeTime) {
        return `${day} ${month} ${year}`;
    }

    let hours = d.getHours();
    const minutes = d.getMinutes().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;

    return `${day} ${month} ${year}, ${hours}:${minutes} ${ampm}`;
}

/**
 * Format percentage:
 * One decimal, e.g. 58.5%
 */
export function formatPercent(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') return '0.0%';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num)) return '0.0%';
    return `${num.toFixed(1)}%`;
}

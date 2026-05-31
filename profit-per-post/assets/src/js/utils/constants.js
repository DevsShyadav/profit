/**
 * Application constants.
 */

export const ROUTES = {
    DASHBOARD: 'dashboard',
    POSTS: 'posts',
    POST_DETAIL: 'post-detail',
    SETTINGS: 'settings',
    ONBOARDING: 'onboarding',
};

export const DATE_PRESETS = [
    { value: '7d', label: 'Last 7 Days' },
    { value: '14d', label: 'Last 14 Days' },
    { value: '30d', label: 'Last 30 Days' },
    { value: '90d', label: 'Last 90 Days' },
    { value: '6m', label: 'Last 6 Months' },
    { value: '12m', label: 'Last 12 Months' },
];

export const SOURCES = {
    google_analytics: { id: 'google_analytics', name: 'Google Analytics', color: '#4285F4', icon: 'chart-line' },
    adsense: { id: 'adsense', name: 'Google AdSense', color: '#34A853', icon: 'money' },
    mediavine: { id: 'mediavine', name: 'Mediavine', color: '#7C3AED', icon: 'chart-bar' },
    woocommerce: { id: 'woocommerce', name: 'WooCommerce', color: '#7F54B3', icon: 'cart' },
    affiliate: { id: 'affiliate', name: 'Affiliate Links', color: '#F59E0B', icon: 'admin-links' },
};

export const SOURCE_COLORS = {
    adsense: '#34A853',
    mediavine: '#7C3AED',
    woocommerce: '#7F54B3',
    affiliate: '#F59E0B',
};

export const STATUS_COLORS = {
    connected: '#16a34a',
    disconnected: '#6b7280',
    error: '#dc2626',
    expired: '#f59e0b',
};

export const CHART_COLORS = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316', '#eab308', '#22c55e', '#14b8a6'];

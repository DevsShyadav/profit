/**
 * API utility - handles all REST API communication.
 */

const API = {
    /**
     * Make a GET request to the plugin REST API.
     */
    async get(endpoint, params = {}) {
        const url = new URL(pppConfig.restUrl + '/' + endpoint, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.append(key, value);
            }
        });

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'X-WP-Nonce': pppConfig.nonce,
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `Request failed: ${response.status}`);
        }

        return response.json();
    },

    /**
     * Make a POST request to the plugin REST API.
     */
    async post(endpoint, data = {}) {
        const url = pppConfig.restUrl + '/' + endpoint;

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-WP-Nonce': pppConfig.nonce,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `Request failed: ${response.status}`);
        }

        return response.json();
    },

    /**
     * Dashboard endpoints.
     */
    dashboard: {
        get(params) { return API.get('dashboard', params); },
        getInsights(params) { return API.get('dashboard/insights', params); },
    },

    /**
     * Posts endpoints.
     */
    posts: {
        list(params) { return API.get('posts', params); },
        detail(id, params) { return API.get(`posts/${id}`, params); },
    },

    /**
     * Settings endpoints.
     */
    settings: {
        get() { return API.get('settings'); },
        update(data) { return API.post('settings', data); },
        getOnboarding() { return API.get('settings/onboarding'); },
        completeOnboarding() { return API.post('settings/onboarding/complete'); },
    },

    /**
     * Connections endpoints.
     */
    connections: {
        list() { return API.get('connections'); },
        connect(source, data) { return API.post(`connections/${source}/connect`, data); },
        disconnect(source) { return API.post(`connections/${source}/disconnect`); },
        test(source) { return API.post(`connections/${source}/test`); },
        testAll() { return API.post('connections/test-all'); },
        getAuthUrl(source) { return API.get(`connections/${source}/auth-url`); },
        oauthCallback(source, code) { return API.post(`connections/${source}/oauth-callback`, { code }); },
    },

    /**
     * Sync endpoints.
     */
    sync: {
        status() { return API.get('sync/status'); },
        trigger(data) { return API.post('sync/trigger', data); },
        history(params) { return API.get('sync/history', params); },
    },

    /**
     * Export endpoints.
     */
    export: {
        csvUrl(params) {
            const url = new URL(pppConfig.restUrl + '/export/csv', window.location.origin);
            Object.entries(params).forEach(([key, value]) => {
                if (value) url.searchParams.append(key, value);
            });
            url.searchParams.append('_wpnonce', pppConfig.nonce);
            return url.toString();
        },
        json(params) { return API.get('export/json', params); },
    },
};

export default API;

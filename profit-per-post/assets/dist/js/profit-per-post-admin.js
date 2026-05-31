/**
 * Profit Per Post - Admin App Bundle
 * Built from assets/src/js/app.js
 * 
 * This is a pre-built bundle. For development, run `npm run build`.
 * Uses WordPress's bundled React (@wordpress/element).
 */
(function() {
'use strict';

const { createElement, useState, useEffect, useCallback, render } = wp.element;

/* ============ UTILS ============ */
const API = {
    async get(endpoint, params = {}) {
        const url = new URL(pppConfig.restUrl + '/' + endpoint, window.location.origin);
        Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== '') url.searchParams.append(k, v); });
        const r = await fetch(url.toString(), { method: 'GET', headers: { 'X-WP-Nonce': pppConfig.nonce, 'Content-Type': 'application/json' } });
        if (!r.ok) { const e = await r.json().catch(() => ({})); throw new Error(e.message || 'Request failed'); }
        return r.json();
    },
    async post(endpoint, data = {}) {
        const r = await fetch(pppConfig.restUrl + '/' + endpoint, { method: 'POST', headers: { 'X-WP-Nonce': pppConfig.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        if (!r.ok) { const e = await r.json().catch(() => ({})); throw new Error(e.message || 'Request failed'); }
        return r.json();
    },
    dashboard: { get(p) { return API.get('dashboard', p); } },
    posts: { list(p) { return API.get('posts', p); }, detail(id, p) { return API.get('posts/' + id, p); } },
    settings: { get() { return API.get('settings'); }, update(d) { return API.post('settings', d); }, completeOnboarding() { return API.post('settings/onboarding/complete'); } },
    connections: { list() { return API.get('connections'); }, connect(s, d) { return API.post('connections/' + s + '/connect', d); }, disconnect(s) { return API.post('connections/' + s + '/disconnect'); }, test(s) { return API.post('connections/' + s + '/test'); } },
    sync: { status() { return API.get('sync/status'); }, trigger(d) { return API.post('sync/trigger', d); }, history(p) { return API.get('sync/history', p); } }
};

function formatCurrency(amount) {
    const s = pppConfig.currencySymbol || '$';
    const n = parseFloat(amount) || 0;
    return s + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatNumber(num) { return (parseInt(num) || 0).toLocaleString('en-US'); }
function timeAgo(d) { if (!d) return 'Never'; const diff = Math.floor((new Date() - new Date(d)) / 1000); if (diff < 60) return 'Just now'; if (diff < 3600) return Math.floor(diff/60) + 'm ago'; if (diff < 86400) return Math.floor(diff/3600) + 'h ago'; return Math.floor(diff/86400) + 'd ago'; }

const DATE_PRESETS = [{value:'7d',label:'Last 7 Days'},{value:'14d',label:'Last 14 Days'},{value:'30d',label:'Last 30 Days'},{value:'90d',label:'Last 90 Days'},{value:'6m',label:'Last 6 Months'},{value:'12m',label:'Last 12 Months'}];
const STATUS_COLORS = { connected: '#16a34a', disconnected: '#6b7280', error: '#dc2626', expired: '#f59e0b' };


/* ============ SHARED COMPONENTS ============ */
function LoadingState({ message = 'Loading...' }) {
    return createElement('div', { className: 'ppp-loading' },
        createElement('div', { className: 'ppp-loading__spinner' }),
        createElement('p', { className: 'ppp-loading__message' }, message)
    );
}

function EmptyState({ title, description, action, onAction }) {
    return createElement('div', { className: 'ppp-empty-state' },
        createElement('div', { className: 'ppp-empty-state__icon' }, createElement('span', { className: 'dashicons dashicons-chart-area' })),
        createElement('h3', { className: 'ppp-empty-state__title' }, title || 'No Data Yet'),
        description && createElement('p', { className: 'ppp-empty-state__description' }, description),
        action && createElement('button', { className: 'ppp-btn ppp-btn--primary', onClick: onAction }, action)
    );
}

function DateRangePicker({ value, onChange }) {
    return createElement('select', { className: 'ppp-date-picker__select', value, onChange: e => onChange(e.target.value) },
        DATE_PRESETS.map(p => createElement('option', { key: p.value, value: p.value }, p.label))
    );
}

function StatCard({ title, value, subtitle, change, icon, color }) {
    const cc = change > 0 ? 'ppp-stat-change--positive' : change < 0 ? 'ppp-stat-change--negative' : '';
    const ci = change > 0 ? '\u2191' : change < 0 ? '\u2193' : '';
    return createElement('div', { className: 'ppp-stat-card' },
        createElement('div', { className: 'ppp-stat-card__header' },
            icon && createElement('div', { className: 'ppp-stat-card__icon', style: { color: color || '#6366f1' } }, createElement('span', { className: 'dashicons dashicons-' + icon })),
            title && createElement('span', { className: 'ppp-stat-card__title' }, title)
        ),
        createElement('div', { className: 'ppp-stat-card__value' }, value),
        createElement('div', { className: 'ppp-stat-card__footer' },
            subtitle && createElement('span', { className: 'ppp-stat-card__subtitle' }, subtitle),
            change !== undefined && change !== 0 && createElement('span', { className: 'ppp-stat-change ' + cc }, ci + ' ' + Math.abs(change).toFixed(1) + '%')
        )
    );
}

function Toast({ message, type = 'success', onClose }) {
    useEffect(() => { const t = setTimeout(onClose, 3000); return () => clearTimeout(t); }, []);
    return createElement('div', { className: 'ppp-toast ppp-toast--' + type },
        createElement('span', null, message),
        createElement('button', { className: 'ppp-toast__close', onClick: onClose }, '\u00D7')
    );
}


/* ============ DASHBOARD PAGE ============ */
function DashboardPage() {
    const [period, setPeriod] = useState((pppConfig.defaultRange || '30') + 'd');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        API.dashboard.get({ period }).then(r => setData(r.data)).catch(() => {}).finally(() => setLoading(false));
    }, [period]);

    if (loading) return createElement(LoadingState, { message: 'Loading revenue data...' });
    if (!data || !data.totals) return createElement(EmptyState, { title: 'No Revenue Data Yet', description: 'Connect your revenue sources and sync data to see your per-post earnings.' });

    const { totals, top_posts, dead_posts, revenue_trend, by_source } = data;

    return createElement('div', { className: 'ppp-dashboard' },
        createElement('div', { className: 'ppp-page-header' },
            createElement('div', null, createElement('h1', { className: 'ppp-page-title' }, 'Revenue Dashboard'), createElement('p', { className: 'ppp-page-subtitle' }, 'Track exactly how much each post earns')),
            createElement(DateRangePicker, { value: period, onChange: setPeriod })
        ),
        createElement('div', { className: 'ppp-stats-grid' },
            createElement(StatCard, { title: 'Total Revenue', value: formatCurrency(totals.total_revenue), change: totals.revenue_change, icon: 'chart-area', color: '#6366f1' }),
            createElement(StatCard, { title: 'Top Post Revenue', value: formatCurrency(totals.top_post_revenue), subtitle: 'Highest earner', icon: 'star-filled', color: '#f59e0b' }),
            createElement(StatCard, { title: 'Dead Posts', value: String(totals.dead_posts_count), subtitle: '$0 revenue', icon: 'warning', color: '#ef4444' }),
            createElement(StatCard, { title: 'Top 10 Concentration', value: totals.top_10_percent + '%', subtitle: 'of total revenue', icon: 'chart-pie', color: '#8b5cf6' })
        ),
        createElement('div', { className: 'ppp-card' },
            createElement('h3', { className: 'ppp-card__title' }, 'Revenue Trend'),
            createElement('div', { className: 'ppp-chart-container' },
                revenue_trend && revenue_trend.length > 0
                    ? createElement('div', { className: 'ppp-simple-chart' },
                        revenue_trend.map((day, i) => createElement('div', { key: i, className: 'ppp-simple-chart__bar', style: { height: Math.max(4, (day.revenue / Math.max(...revenue_trend.map(d => d.revenue || 1))) * 100) + '%' }, title: day.date + ': ' + formatCurrency(day.revenue) }))
                    ) : createElement('p', { className: 'ppp-muted' }, 'No trend data available')
            )
        ),
        createElement('div', { className: 'ppp-grid-2' },
            createElement('div', { className: 'ppp-card' },
                createElement('h3', { className: 'ppp-card__title' }, 'Top Earning Posts'),
                top_posts && top_posts.length > 0 ? createElement('div', { className: 'ppp-top-posts-list' },
                    top_posts.slice(0, 5).map((post, i) => createElement('div', { key: post.post_id, className: 'ppp-top-post-item' },
                        createElement('span', { className: 'ppp-top-post-item__rank' }, '#' + (i+1)),
                        createElement('div', { className: 'ppp-top-post-item__info' }, createElement('span', { className: 'ppp-top-post-item__title' }, post.title), createElement('span', { className: 'ppp-top-post-item__meta' }, formatNumber(post.pageviews) + ' views')),
                        createElement('span', { className: 'ppp-top-post-item__revenue' }, formatCurrency(post.revenue))
                    ))
                ) : createElement('p', { className: 'ppp-muted' }, 'No data yet')
            ),
            createElement('div', { className: 'ppp-card' },
                createElement('h3', { className: 'ppp-card__title' }, 'Revenue by Source'),
                by_source && by_source.length > 0 ? createElement('div', { className: 'ppp-source-list' },
                    by_source.map(s => createElement('div', { key: s.source, className: 'ppp-source-item' },
                        createElement('div', { className: 'ppp-source-item__info' }, createElement('span', { className: 'ppp-source-item__name' }, s.label), createElement('span', { className: 'ppp-source-item__pct' }, s.percentage + '%')),
                        createElement('div', { className: 'ppp-source-item__bar' }, createElement('div', { className: 'ppp-source-item__fill', style: { width: s.percentage + '%' } })),
                        createElement('span', { className: 'ppp-source-item__amount' }, formatCurrency(s.revenue))
                    ))
                ) : createElement('p', { className: 'ppp-muted' }, 'No data yet')
            )
        ),
        dead_posts && dead_posts.count > 0 && createElement('div', { className: 'ppp-card ppp-card--warning' },
            createElement('h3', { className: 'ppp-card__title' }, '\u26A0\uFE0F ' + dead_posts.count + ' Posts Making $0'),
            createElement('p', { className: 'ppp-card__description' }, 'These posts generate zero revenue. Consider updating, promoting, or consolidating them.')
        )
    );
}


/* ============ POSTS LIST PAGE ============ */
function PostsListPage({ onViewPost }) {
    const [period, setPeriod] = useState((pppConfig.defaultRange || '30') + 'd');
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [orderBy, setOrderBy] = useState('revenue');
    const [order, setOrder] = useState('DESC');
    const [data, setData] = useState({ posts: [], total: 0, total_pages: 0 });
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        API.posts.list({ period, page, per_page: 20, order_by: orderBy, order, search })
            .then(r => setData(r.data)).catch(() => {}).finally(() => setLoading(false));
    }, [period, page, orderBy, order, search]);

    const handleSort = (field) => { if (orderBy === field) setOrder(o => o === 'DESC' ? 'ASC' : 'DESC'); else { setOrderBy(field); setOrder('DESC'); } setPage(1); };
    const handleExport = () => { window.open(pppConfig.restUrl + '/export/csv?period=' + period + '&_wpnonce=' + pppConfig.nonce, '_blank'); };

    return createElement('div', { className: 'ppp-posts-page' },
        createElement('div', { className: 'ppp-page-header' },
            createElement('div', null, createElement('h1', { className: 'ppp-page-title' }, 'Revenue Per Post'), createElement('p', { className: 'ppp-page-subtitle' }, (data.total || 0) + ' posts total')),
            createElement('div', { className: 'ppp-page-header__actions' }, createElement(DateRangePicker, { value: period, onChange: v => { setPeriod(v); setPage(1); } }), createElement('button', { className: 'ppp-btn ppp-btn--secondary', onClick: handleExport }, 'Export CSV'))
        ),
        createElement('div', { className: 'ppp-toolbar' }, createElement('input', { type: 'text', className: 'ppp-search-input', placeholder: 'Search posts...', value: search, onChange: e => { setSearch(e.target.value); setPage(1); } })),
        loading ? createElement(LoadingState) : createElement('div', { className: 'ppp-table-wrapper' },
            createElement('table', { className: 'ppp-table' },
                createElement('thead', null, createElement('tr', null,
                    createElement('th', { onClick: () => handleSort('title'), className: 'ppp-table__th--sortable' }, 'Post Title'),
                    createElement('th', { onClick: () => handleSort('pageviews'), className: 'ppp-table__th--sortable ppp-table__th--right' }, 'Traffic'),
                    createElement('th', { onClick: () => handleSort('revenue'), className: 'ppp-table__th--sortable ppp-table__th--right' }, 'Revenue'),
                    createElement('th', { className: 'ppp-table__th--right' }, 'RPM')
                )),
                createElement('tbody', null, data.posts && data.posts.length > 0
                    ? data.posts.map(post => createElement('tr', { key: post.post_id, className: 'ppp-table__row', onClick: () => onViewPost && onViewPost(post.post_id) },
                        createElement('td', null, createElement('span', { className: 'ppp-post-cell__title' }, post.title)),
                        createElement('td', { className: 'ppp-table__td--right' }, formatNumber(post.pageviews)),
                        createElement('td', { className: 'ppp-table__td--right ppp-table__td--revenue' }, formatCurrency(post.revenue)),
                        createElement('td', { className: 'ppp-table__td--right' }, formatCurrency(post.rpm))
                    ))
                    : createElement('tr', null, createElement('td', { colSpan: 4, className: 'ppp-table__empty' }, 'No posts found'))
                )
            )
        ),
        data.total_pages > 1 && createElement('div', { className: 'ppp-pagination' },
            createElement('button', { className: 'ppp-btn ppp-btn--sm', disabled: page <= 1, onClick: () => setPage(p => Math.max(1, p-1)) }, 'Previous'),
            createElement('span', { className: 'ppp-pagination__info' }, 'Page ' + page + ' of ' + data.total_pages),
            createElement('button', { className: 'ppp-btn ppp-btn--sm', disabled: page >= data.total_pages, onClick: () => setPage(p => p+1) }, 'Next')
        )
    );
}


/* ============ SETTINGS PAGE ============ */
function SettingsPage() {
    const [activeTab, setActiveTab] = useState('connections');
    const [settings, setSettings] = useState(null);
    const [connections, setConnections] = useState({});
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        Promise.all([API.settings.get(), API.connections.list()])
            .then(([s, c]) => { setSettings(s.data); setConnections(c.data); })
            .finally(() => setLoading(false));
    }, []);

    if (loading) return createElement(LoadingState);

    const showToast = (msg, type='success') => setToast({ message: msg, type });
    const tabs = [{id:'connections',label:'Connections'},{id:'sync',label:'Sync'},{id:'display',label:'Display'},{id:'advanced',label:'Advanced'}];

    return createElement('div', { className: 'ppp-settings' },
        createElement('div', { className: 'ppp-page-header' }, createElement('h1', { className: 'ppp-page-title' }, 'Settings')),
        createElement('div', { className: 'ppp-tabs' }, tabs.map(t => createElement('button', { key: t.id, className: 'ppp-tab ' + (activeTab===t.id?'ppp-tab--active':''), onClick: () => setActiveTab(t.id) }, t.label))),
        createElement('div', { className: 'ppp-tab-content' },
            activeTab === 'connections' && createElement('div', { className: 'ppp-connections-list' },
                Object.entries(connections).map(([id, info]) => createElement('div', { key: id, className: 'ppp-connection-card' },
                    createElement('div', { className: 'ppp-connection-card__header' },
                        createElement('span', { className: 'dashicons dashicons-' + (info.icon||'admin-generic') }),
                        createElement('div', null, createElement('h4', null, info.source_name), createElement('span', { className: 'ppp-connection-status', style: { color: STATUS_COLORS[info.status]||'#6b7280' } }, info.status === 'connected' ? 'Connected' : info.status === 'expired' ? 'Expired' : 'Not Connected'))
                    ),
                    createElement('div', { className: 'ppp-connection-card__actions' },
                        info.status === 'connected'
                            ? createElement('button', { className: 'ppp-btn ppp-btn--sm ppp-btn--danger', onClick: () => API.connections.disconnect(id).then(() => { connections[id].status='disconnected'; setConnections({...connections}); showToast('Disconnected'); }) }, 'Disconnect')
                            : createElement('button', { className: 'ppp-btn ppp-btn--sm ppp-btn--primary', onClick: () => showToast('Configure API credentials to connect', 'info') }, 'Connect')
                    )
                ))
            ),
            activeTab === 'sync' && createElement(SyncTabContent, { showToast }),
            activeTab === 'display' && createElement(DisplayTabContent, { settings, showToast }),
            activeTab === 'advanced' && createElement(AdvancedTabContent, { settings, showToast })
        ),
        toast && createElement(Toast, { ...toast, onClose: () => setToast(null) })
    );
}

function SyncTabContent({ showToast }) {
    const [syncing, setSyncing] = useState(false);
    const [history, setHistory] = useState([]);
    useEffect(() => { API.sync.history({ limit: 10 }).then(r => setHistory(r.data||[])).catch(()=>{}); }, []);

    return createElement('div', null,
        createElement('div', { className: 'ppp-card' },
            createElement('div', { className: 'ppp-flex-between' },
                createElement('div', null, createElement('h3', null, 'Manual Sync'), createElement('p', { className: 'ppp-muted' }, 'Fetch latest data from all connected sources.')),
                createElement('button', { className: 'ppp-btn ppp-btn--primary', disabled: syncing, onClick: () => { setSyncing(true); API.sync.trigger({}).then(() => { showToast('Sync completed!'); API.sync.history({limit:10}).then(r=>setHistory(r.data||[])); }).catch(e => showToast(e.message,'error')).finally(() => setSyncing(false)); } }, syncing ? 'Syncing...' : 'Sync Now')
            )
        ),
        history.length > 0 && createElement('div', { className: 'ppp-card ppp-mt-4' },
            createElement('h3', null, 'Recent History'),
            createElement('div', { className: 'ppp-sync-history' }, history.map((e,i) => createElement('div', { key: i, className: 'ppp-sync-entry' },
                createElement('span', { className: 'ppp-sync-entry__status ppp-sync-entry__status--' + e.status }),
                createElement('span', null, e.source),
                createElement('span', { className: 'ppp-muted' }, timeAgo(e.started_at)),
                e.records_synced > 0 && createElement('span', null, e.records_synced + ' records')
            )))
        )
    );
}

function DisplayTabContent({ settings, showToast }) {
    const [currency, setCurrency] = useState(settings?.currency || 'USD');
    const [perPage, setPerPage] = useState(settings?.posts_per_page || 20);
    const [showCol, setShowCol] = useState(settings?.show_revenue_column !== false);
    return createElement('div', { className: 'ppp-card' },
        createElement('div', { className: 'ppp-form-group' }, createElement('label', null, 'Currency'),
            createElement('select', { value: currency, onChange: e => setCurrency(e.target.value), className: 'ppp-select' },
                settings?._options?.currencies && Object.entries(settings._options.currencies).map(([c,n]) => createElement('option', { key: c, value: c }, n))
            )),
        createElement('div', { className: 'ppp-form-group' }, createElement('label', null, 'Posts Per Page'),
            createElement('input', { type: 'number', min: 5, max: 100, value: perPage, onChange: e => setPerPage(parseInt(e.target.value)), className: 'ppp-input' })),
        createElement('div', { className: 'ppp-form-group' }, createElement('label', { className: 'ppp-checkbox-label' },
            createElement('input', { type: 'checkbox', checked: showCol, onChange: e => setShowCol(e.target.checked) }), ' Show revenue column in Posts list')),
        createElement('button', { className: 'ppp-btn ppp-btn--primary', onClick: () => API.settings.update({ currency, posts_per_page: perPage, show_revenue_column: showCol }).then(() => showToast('Saved!')).catch(e => showToast(e.message, 'error')) }, 'Save Settings')
    );
}

function AdvancedTabContent({ settings, showToast }) {
    const [debug, setDebug] = useState(settings?.debug_mode || false);
    const [retention, setRetention] = useState(settings?.data_retention_days || 365);
    return createElement('div', { className: 'ppp-card' },
        createElement('div', { className: 'ppp-form-group' }, createElement('label', { className: 'ppp-checkbox-label' },
            createElement('input', { type: 'checkbox', checked: debug, onChange: e => setDebug(e.target.checked) }), ' Enable Debug Mode'),
            createElement('p', { className: 'ppp-muted' }, 'Logs detailed information for troubleshooting.')),
        createElement('div', { className: 'ppp-form-group' }, createElement('label', null, 'Data Retention (days)'),
            createElement('input', { type: 'number', min: 30, max: 730, value: retention, onChange: e => setRetention(parseInt(e.target.value)), className: 'ppp-input' }),
            createElement('p', { className: 'ppp-muted' }, 'Data older than this will be automatically deleted.')),
        createElement('button', { className: 'ppp-btn ppp-btn--primary', onClick: () => API.settings.update({ debug_mode: debug, data_retention_days: retention }).then(() => showToast('Saved!')).catch(e => showToast(e.message, 'error')) }, 'Save Settings')
    );
}


/* ============ ONBOARDING WIZARD ============ */
function OnboardingWizard({ onComplete }) {
    const [step, setStep] = useState(0);
    const steps = [
        { title: 'Welcome to Profit Per Post', description: 'Track exactly how much revenue each of your blog posts generates.' },
        { title: 'Connect Google Analytics', description: "We'll pull traffic data (pageviews) to calculate RPM per post." },
        { title: 'Connect Ad Revenue', description: 'Connect AdSense or Mediavine to see ad revenue per post.' },
        { title: 'WooCommerce & Affiliates', description: 'Track product sales and affiliate clicks attributed to each post.' },
        { title: "You're All Set!", description: 'Your dashboard will populate as data syncs. This usually takes 2-5 minutes.' },
    ];
    const current = steps[step];
    const finish = () => { API.settings.completeOnboarding().then(onComplete); };

    return createElement('div', { className: 'ppp-onboarding' },
        createElement('div', { className: 'ppp-onboarding__card' },
            createElement('div', { className: 'ppp-onboarding__progress' }, steps.map((_, i) => createElement('div', { key: i, className: 'ppp-onboarding__dot ' + (i <= step ? 'ppp-onboarding__dot--active' : '') }))),
            createElement('div', { className: 'ppp-onboarding__content' },
                createElement('h2', { className: 'ppp-onboarding__title' }, current.title),
                createElement('p', { className: 'ppp-onboarding__description' }, current.description),
                step === 0 && createElement('div', { className: 'ppp-onboarding__features' },
                    ['Google Analytics traffic per post', 'AdSense/Mediavine ad revenue', 'WooCommerce sales attribution', 'Affiliate link click tracking'].map(f => createElement('div', { key: f, className: 'ppp-onboarding__feature' }, '\u2713 ' + f))
                )
            ),
            createElement('div', { className: 'ppp-onboarding__actions' },
                step < steps.length - 1
                    ? createElement('div', { className: 'ppp-flex-between' },
                        createElement('button', { className: 'ppp-btn ppp-btn--ghost', onClick: finish }, 'Skip Setup'),
                        createElement('button', { className: 'ppp-btn ppp-btn--primary', onClick: () => setStep(s => s + 1) }, 'Continue'))
                    : createElement('button', { className: 'ppp-btn ppp-btn--primary ppp-btn--lg', onClick: finish }, 'Go to Dashboard')
            )
        )
    );
}

/* ============ MAIN APP ============ */
function App() {
    const [route, setRoute] = useState(getInitialRoute());
    const [isOnboarded, setIsOnboarded] = useState(pppConfig.isOnboarded);

    if (!isOnboarded) return createElement(OnboardingWizard, { onComplete: () => setIsOnboarded(true) });

    const renderPage = () => {
        switch (route) {
            case 'posts': return createElement(PostsListPage, {});
            case 'settings': return createElement(SettingsPage);
            default: return createElement(DashboardPage);
        }
    };

    return createElement('div', { className: 'ppp-app' },
        createElement('nav', { className: 'ppp-sidebar' },
            createElement('div', { className: 'ppp-sidebar__brand' }, createElement('span', { className: 'dashicons dashicons-chart-area' }), createElement('span', { className: 'ppp-sidebar__brand-text' }, 'Profit Per Post')),
            createElement('ul', { className: 'ppp-sidebar__nav' },
                createElement('li', null, createElement('button', { className: 'ppp-sidebar__link ' + (route==='dashboard'?'ppp-sidebar__link--active':''), onClick: () => setRoute('dashboard') }, createElement('span', { className: 'dashicons dashicons-dashboard' }), ' Dashboard')),
                createElement('li', null, createElement('button', { className: 'ppp-sidebar__link ' + (route==='posts'?'ppp-sidebar__link--active':''), onClick: () => setRoute('posts') }, createElement('span', { className: 'dashicons dashicons-admin-post' }), ' All Posts')),
                pppConfig.capabilities.canManageSettings && createElement('li', null, createElement('button', { className: 'ppp-sidebar__link ' + (route==='settings'?'ppp-sidebar__link--active':''), onClick: () => setRoute('settings') }, createElement('span', { className: 'dashicons dashicons-admin-generic' }), ' Settings'))
            )
        ),
        createElement('main', { className: 'ppp-main' }, renderPage())
    );
}

function getInitialRoute() {
    const p = pppConfig.currentPage || '';
    if (p.includes('posts')) return 'posts';
    if (p.includes('settings')) return 'settings';
    return 'dashboard';
}

/* ============ MOUNT ============ */
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('ppp-app-root');
    if (container) render(createElement(App), container);
});

})();

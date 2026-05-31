/**
 * Settings Page - plugin configuration.
 */
import { createElement, useState } from '@wordpress/element';
import { useSettings } from '../../hooks/useSettings';
import { useConnections } from '../../hooks/useConnections';
import { useSync } from '../../hooks/useSync';
import LoadingState from '../Shared/LoadingState';
import Toast from '../Shared/Toast';
import { SOURCES, STATUS_COLORS } from '../../utils/constants';
import { timeAgo } from '../../utils/formatters';

export default function SettingsPage() {
    const [activeTab, setActiveTab] = useState('connections');
    const { settings, loading, saving, updateSettings } = useSettings();
    const { connections, connect, disconnect, testConnection, refetch: refetchConn } = useConnections();
    const { status, syncing, triggerSync, history } = useSync();
    const [toast, setToast] = useState(null);

    if (loading) return createElement(LoadingState, null);

    const tabs = [
        { id: 'connections', label: 'Connections' },
        { id: 'sync', label: 'Sync' },
        { id: 'display', label: 'Display' },
        { id: 'advanced', label: 'Advanced' },
    ];

    const showToast = (message, type = 'success') => setToast({ message, type });

    return createElement('div', { className: 'ppp-settings' },
        createElement('div', { className: 'ppp-page-header' },
            createElement('h1', { className: 'ppp-page-title' }, 'Settings')
        ),

        // Tab Navigation
        createElement('div', { className: 'ppp-tabs' },
            tabs.map(tab =>
                createElement('button', {
                    key: tab.id,
                    className: `ppp-tab ${activeTab === tab.id ? 'ppp-tab--active' : ''}`,
                    onClick: () => setActiveTab(tab.id)
                }, tab.label)
            )
        ),

        // Tab Content
        createElement('div', { className: 'ppp-tab-content' },
            activeTab === 'connections' && createElement(ConnectionsTab, { connections, connect, disconnect, testConnection, showToast }),
            activeTab === 'sync' && createElement(SyncTab, { status, syncing, triggerSync, history, showToast }),
            activeTab === 'display' && createElement(DisplayTab, { settings, updateSettings, showToast }),
            activeTab === 'advanced' && createElement(AdvancedTab, { settings, updateSettings, showToast })
        ),

        // Toast
        toast && createElement(Toast, { ...toast, onClose: () => setToast(null) })
    );
}

function ConnectionsTab({ connections, connect, disconnect, testConnection, showToast }) {
    return createElement('div', { className: 'ppp-connections-list' },
        Object.entries(connections).map(([id, info]) =>
            createElement('div', { key: id, className: 'ppp-connection-card' },
                createElement('div', { className: 'ppp-connection-card__header' },
                    createElement('span', { className: `dashicons dashicons-${info.icon || 'admin-generic'}` }),
                    createElement('div', null,
                        createElement('h4', null, info.source_name),
                        createElement('span', {
                            className: 'ppp-connection-status',
                            style: { color: STATUS_COLORS[info.status] || '#6b7280' }
                        }, info.status === 'connected' ? 'Connected' : info.status === 'expired' ? 'Expired' : 'Not Connected')
                    )
                ),
                createElement('div', { className: 'ppp-connection-card__actions' },
                    info.status === 'connected'
                        ? createElement('button', {
                            className: 'ppp-btn ppp-btn--sm ppp-btn--danger',
                            onClick: async () => { await disconnect(id); showToast('Disconnected'); }
                        }, 'Disconnect')
                        : createElement('button', {
                            className: 'ppp-btn ppp-btn--sm ppp-btn--primary',
                            onClick: () => showToast('Configure credentials in your integration settings', 'info')
                        }, 'Connect')
                )
            )
        )
    );
}

function SyncTab({ status, syncing, triggerSync, history, showToast }) {
    return createElement('div', null,
        createElement('div', { className: 'ppp-card' },
            createElement('div', { className: 'ppp-flex-between' },
                createElement('div', null,
                    createElement('h3', null, 'Manual Sync'),
                    createElement('p', { className: 'ppp-muted' }, 'Fetch the latest data from all connected sources.')
                ),
                createElement('button', {
                    className: 'ppp-btn ppp-btn--primary',
                    disabled: syncing,
                    onClick: async () => {
                        try { await triggerSync(); showToast('Sync completed!'); }
                        catch (e) { showToast(e.message, 'error'); }
                    }
                }, syncing ? 'Syncing...' : 'Sync Now')
            ),
            status && status.next_sync && createElement('p', { className: 'ppp-muted ppp-mt-2' },
                'Next automatic sync: ', timeAgo(status.next_sync)
            )
        ),

        history && history.length > 0 && createElement('div', { className: 'ppp-card ppp-mt-4' },
            createElement('h3', null, 'Recent Sync History'),
            createElement('div', { className: 'ppp-sync-history' },
                history.slice(0, 10).map((entry, i) =>
                    createElement('div', { key: i, className: 'ppp-sync-entry' },
                        createElement('span', { className: `ppp-sync-entry__status ppp-sync-entry__status--${entry.status}` }),
                        createElement('span', null, entry.source),
                        createElement('span', { className: 'ppp-muted' }, timeAgo(entry.started_at)),
                        entry.records_synced > 0 && createElement('span', null, entry.records_synced + ' records')
                    )
                )
            )
        )
    );
}

function DisplayTab({ settings, updateSettings, showToast }) {
    const [currency, setCurrency] = useState(settings?.currency || 'USD');
    const [postsPerPage, setPostsPerPage] = useState(settings?.posts_per_page || 20);
    const [showColumn, setShowColumn] = useState(settings?.show_revenue_column !== false);

    const handleSave = async () => {
        const success = await updateSettings({
            currency, posts_per_page: postsPerPage, show_revenue_column: showColumn
        });
        if (success) showToast('Settings saved!');
    };

    return createElement('div', { className: 'ppp-card' },
        createElement('div', { className: 'ppp-form-group' },
            createElement('label', null, 'Currency'),
            createElement('select', { value: currency, onChange: e => setCurrency(e.target.value), className: 'ppp-select' },
                settings?._options?.currencies && Object.entries(settings._options.currencies).map(([code, name]) =>
                    createElement('option', { key: code, value: code }, name)
                )
            )
        ),
        createElement('div', { className: 'ppp-form-group' },
            createElement('label', null, 'Posts Per Page'),
            createElement('input', {
                type: 'number', min: 5, max: 100, value: postsPerPage,
                onChange: e => setPostsPerPage(parseInt(e.target.value)),
                className: 'ppp-input'
            })
        ),
        createElement('div', { className: 'ppp-form-group' },
            createElement('label', { className: 'ppp-checkbox-label' },
                createElement('input', { type: 'checkbox', checked: showColumn, onChange: e => setShowColumn(e.target.checked) }),
                ' Show revenue column in Posts list'
            )
        ),
        createElement('button', { className: 'ppp-btn ppp-btn--primary', onClick: handleSave }, 'Save Settings')
    );
}

function AdvancedTab({ settings, updateSettings, showToast }) {
    const [debugMode, setDebugMode] = useState(settings?.debug_mode || false);
    const [retention, setRetention] = useState(settings?.data_retention_days || 365);

    const handleSave = async () => {
        const success = await updateSettings({ debug_mode: debugMode, data_retention_days: retention });
        if (success) showToast('Settings saved!');
    };

    return createElement('div', { className: 'ppp-card' },
        createElement('div', { className: 'ppp-form-group' },
            createElement('label', { className: 'ppp-checkbox-label' },
                createElement('input', { type: 'checkbox', checked: debugMode, onChange: e => setDebugMode(e.target.checked) }),
                ' Enable Debug Mode'
            ),
            createElement('p', { className: 'ppp-muted' }, 'Logs detailed sync and API information for troubleshooting.')
        ),
        createElement('div', { className: 'ppp-form-group' },
            createElement('label', null, 'Data Retention (days)'),
            createElement('input', {
                type: 'number', min: 30, max: 730, value: retention,
                onChange: e => setRetention(parseInt(e.target.value)),
                className: 'ppp-input'
            }),
            createElement('p', { className: 'ppp-muted' }, 'Revenue and traffic data older than this will be automatically deleted.')
        ),
        createElement('button', { className: 'ppp-btn ppp-btn--primary', onClick: handleSave }, 'Save Settings')
    );
}

/**
 * StatCard component - displays a single metric.
 */
import { createElement } from '@wordpress/element';

export default function StatCard({ title, value, subtitle, change, icon, color }) {
    const changeClass = change > 0 ? 'ppp-stat-change--positive' : change < 0 ? 'ppp-stat-change--negative' : '';
    const changeIcon = change > 0 ? '↑' : change < 0 ? '↓' : '';

    return createElement('div', { className: 'ppp-stat-card' },
        createElement('div', { className: 'ppp-stat-card__header' },
            icon && createElement('div', { className: `ppp-stat-card__icon`, style: { color: color || '#6366f1' } },
                createElement('span', { className: `dashicons dashicons-${icon}` })
            ),
            title && createElement('span', { className: 'ppp-stat-card__title' }, title)
        ),
        createElement('div', { className: 'ppp-stat-card__value' }, value),
        createElement('div', { className: 'ppp-stat-card__footer' },
            subtitle && createElement('span', { className: 'ppp-stat-card__subtitle' }, subtitle),
            change !== undefined && change !== 0 && createElement('span', { className: `ppp-stat-change ${changeClass}` },
                changeIcon, ' ', Math.abs(change).toFixed(1), '%'
            )
        )
    );
}

/**
 * Empty state component.
 */
import { createElement } from '@wordpress/element';

export default function EmptyState({ title, description, action, onAction }) {
    return createElement('div', { className: 'ppp-empty-state' },
        createElement('div', { className: 'ppp-empty-state__icon' },
            createElement('span', { className: 'dashicons dashicons-chart-area' })
        ),
        createElement('h3', { className: 'ppp-empty-state__title' }, title || 'No Data Yet'),
        description && createElement('p', { className: 'ppp-empty-state__description' }, description),
        action && createElement('button', {
            className: 'ppp-btn ppp-btn--primary',
            onClick: onAction
        }, action)
    );
}

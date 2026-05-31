/**
 * Loading state component.
 */
import { createElement } from '@wordpress/element';

export default function LoadingState({ message = 'Loading...' }) {
    return createElement('div', { className: 'ppp-loading' },
        createElement('div', { className: 'ppp-loading__spinner' }),
        createElement('p', { className: 'ppp-loading__message' }, message)
    );
}

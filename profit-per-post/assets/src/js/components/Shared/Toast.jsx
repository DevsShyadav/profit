/**
 * Toast notification component.
 */
import { createElement, useState, useEffect } from '@wordpress/element';

export default function Toast({ message, type = 'success', duration = 3000, onClose }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => {
            setVisible(false);
            if (onClose) onClose();
        }, duration);
        return () => clearTimeout(timer);
    }, [duration, onClose]);

    if (!visible) return null;

    return createElement('div', { className: `ppp-toast ppp-toast--${type}` },
        createElement('span', { className: 'ppp-toast__message' }, message),
        createElement('button', {
            className: 'ppp-toast__close',
            onClick: () => { setVisible(false); if (onClose) onClose(); }
        }, '\u00D7')
    );
}

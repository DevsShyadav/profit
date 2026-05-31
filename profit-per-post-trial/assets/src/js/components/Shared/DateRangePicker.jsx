/**
 * DateRangePicker component.
 */
import { createElement } from '@wordpress/element';
import { DATE_PRESETS } from '../../utils/constants';

export default function DateRangePicker({ value, onChange }) {
    return createElement('div', { className: 'ppp-date-picker' },
        createElement('select', {
            className: 'ppp-date-picker__select',
            value: value,
            onChange: (e) => onChange(e.target.value)
        },
            DATE_PRESETS.map(preset =>
                createElement('option', { key: preset.value, value: preset.value }, preset.label)
            )
        )
    );
}

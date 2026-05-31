/**
 * Custom hook for settings management.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import API from '../utils/api';

export function useSettings() {
    const [settings, setSettings] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const fetchSettings = useCallback(async () => {
        setLoading(true);
        try {
            const response = await API.settings.get();
            setSettings(response.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, []);

    const updateSettings = useCallback(async (newSettings) => {
        setSaving(true);
        setError(null);
        try {
            const response = await API.settings.update(newSettings);
            setSettings(prev => ({ ...prev, ...response.data.updated }));
            return true;
        } catch (err) {
            setError(err.message);
            return false;
        } finally {
            setSaving(false);
        }
    }, []);

    useEffect(() => { fetchSettings(); }, [fetchSettings]);

    return { settings, loading, saving, error, updateSettings, refetch: fetchSettings };
}

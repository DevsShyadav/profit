/**
 * Custom hook for sync operations.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import API from '../utils/api';

export function useSync() {
    const [status, setStatus] = useState(null);
    const [syncing, setSyncing] = useState(false);
    const [history, setHistory] = useState([]);
    const [error, setError] = useState(null);

    const fetchStatus = useCallback(async () => {
        try {
            const response = await API.sync.status();
            setStatus(response.data);
        } catch (err) {
            setError(err.message);
        }
    }, []);

    const fetchHistory = useCallback(async (limit = 20) => {
        try {
            const response = await API.sync.history({ limit });
            setHistory(response.data || []);
        } catch (err) {
            // silent
        }
    }, []);

    const triggerSync = useCallback(async (params = {}) => {
        setSyncing(true);
        setError(null);
        try {
            const response = await API.sync.trigger(params);
            await fetchStatus();
            await fetchHistory();
            return response;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setSyncing(false);
        }
    }, [fetchStatus, fetchHistory]);

    useEffect(() => {
        fetchStatus();
        fetchHistory();
    }, [fetchStatus, fetchHistory]);

    return { status, syncing, history, error, triggerSync, refetchStatus: fetchStatus, refetchHistory: fetchHistory };
}

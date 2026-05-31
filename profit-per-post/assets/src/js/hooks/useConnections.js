/**
 * Custom hook for managing connections.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import API from '../utils/api';

export function useConnections() {
    const [connections, setConnections] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchConnections = useCallback(async () => {
        setLoading(true);
        try {
            const response = await API.connections.list();
            setConnections(response.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, []);

    const connect = useCallback(async (source, credentials) => {
        try {
            const response = await API.connections.connect(source, credentials);
            await fetchConnections();
            return response;
        } catch (err) {
            throw err;
        }
    }, [fetchConnections]);

    const disconnect = useCallback(async (source) => {
        try {
            const response = await API.connections.disconnect(source);
            await fetchConnections();
            return response;
        } catch (err) {
            throw err;
        }
    }, [fetchConnections]);

    const testConnection = useCallback(async (source) => {
        return API.connections.test(source);
    }, []);

    useEffect(() => { fetchConnections(); }, [fetchConnections]);

    return { connections, loading, error, connect, disconnect, testConnection, refetch: fetchConnections };
}

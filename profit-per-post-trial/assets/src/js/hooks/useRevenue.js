/**
 * Custom hook for revenue/dashboard data.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import API from '../utils/api';

export function useRevenue(period = '30d') {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchData = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await API.dashboard.get({ period });
            setData(response.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [period]);

    useEffect(() => { fetchData(); }, [fetchData]);

    return { data, loading, error, refetch: fetchData };
}

export function useInsights(period = '30d') {
    const [insights, setInsights] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        API.dashboard.getInsights({ period })
            .then(res => setInsights(res.data || []))
            .catch(() => setInsights([]))
            .finally(() => setLoading(false));
    }, [period]);

    return { insights, loading };
}

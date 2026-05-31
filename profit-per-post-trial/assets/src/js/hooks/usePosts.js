/**
 * Custom hook for posts revenue data.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import API from '../utils/api';

export function usePosts(params = {}) {
    const [data, setData] = useState({ posts: [], total: 0, total_pages: 0 });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchData = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await API.posts.list(params);
            setData(response.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [JSON.stringify(params)]);

    useEffect(() => { fetchData(); }, [fetchData]);

    return { data, loading, error, refetch: fetchData };
}

export function usePostDetail(postId, period = '30d') {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!postId) return;
        setLoading(true);
        API.posts.detail(postId, { period })
            .then(res => setData(res.data))
            .catch(err => setError(err.message))
            .finally(() => setLoading(false));
    }, [postId, period]);

    return { data, loading, error };
}

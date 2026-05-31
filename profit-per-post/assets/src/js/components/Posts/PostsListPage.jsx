/**
 * Posts List Page - all posts with revenue data.
 */
import { createElement, useState } from '@wordpress/element';
import { usePosts } from '../../hooks/usePosts';
import { formatCurrency, formatNumber } from '../../utils/formatters';
import LoadingState from '../Shared/LoadingState';
import DateRangePicker from '../Shared/DateRangePicker';

export default function PostsListPage({ onViewPost }) {
    const [period, setPeriod] = useState(pppConfig.defaultRange + 'd');
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [orderBy, setOrderBy] = useState('revenue');
    const [order, setOrder] = useState('DESC');

    const { data, loading } = usePosts({
        period, page, per_page: 20, order_by: orderBy, order, search
    });

    const handleSort = (field) => {
        if (orderBy === field) {
            setOrder(order === 'DESC' ? 'ASC' : 'DESC');
        } else {
            setOrderBy(field);
            setOrder('DESC');
        }
        setPage(1);
    };

    const handleExport = () => {
        const url = pppConfig.restUrl + `/export/csv?period=${period}&_wpnonce=${pppConfig.nonce}`;
        window.open(url, '_blank');
    };

    return createElement('div', { className: 'ppp-posts-page' },
        // Header
        createElement('div', { className: 'ppp-page-header' },
            createElement('div', null,
                createElement('h1', { className: 'ppp-page-title' }, 'Revenue Per Post'),
                createElement('p', { className: 'ppp-page-subtitle' }, `${data.total || 0} posts total`)
            ),
            createElement('div', { className: 'ppp-page-header__actions' },
                createElement(DateRangePicker, { value: period, onChange: (v) => { setPeriod(v); setPage(1); } }),
                createElement('button', { className: 'ppp-btn ppp-btn--secondary', onClick: handleExport }, 'Export CSV')
            )
        ),

        // Search
        createElement('div', { className: 'ppp-toolbar' },
            createElement('input', {
                type: 'text',
                className: 'ppp-search-input',
                placeholder: 'Search posts...',
                value: search,
                onChange: (e) => { setSearch(e.target.value); setPage(1); }
            })
        ),

        // Table
        loading ? createElement(LoadingState, null) :
        createElement('div', { className: 'ppp-table-wrapper' },
            createElement('table', { className: 'ppp-table' },
                createElement('thead', null,
                    createElement('tr', null,
                        createElement('th', { onClick: () => handleSort('title'), className: 'ppp-table__th--sortable' }, 'Post Title'),
                        createElement('th', { onClick: () => handleSort('pageviews'), className: 'ppp-table__th--sortable ppp-table__th--right' }, 'Traffic'),
                        createElement('th', { onClick: () => handleSort('revenue'), className: 'ppp-table__th--sortable ppp-table__th--right' }, 'Revenue'),
                        createElement('th', { className: 'ppp-table__th--right' }, 'RPM')
                    )
                ),
                createElement('tbody', null,
                    data.posts && data.posts.length > 0
                        ? data.posts.map(post =>
                            createElement('tr', {
                                key: post.post_id,
                                className: 'ppp-table__row',
                                onClick: () => onViewPost && onViewPost(post.post_id)
                            },
                                createElement('td', null,
                                    createElement('div', { className: 'ppp-post-cell' },
                                        createElement('span', { className: 'ppp-post-cell__title' }, post.title)
                                    )
                                ),
                                createElement('td', { className: 'ppp-table__td--right' }, formatNumber(post.pageviews)),
                                createElement('td', { className: 'ppp-table__td--right ppp-table__td--revenue' },
                                    formatCurrency(post.revenue)
                                ),
                                createElement('td', { className: 'ppp-table__td--right' }, formatCurrency(post.rpm))
                            )
                        )
                        : createElement('tr', null,
                            createElement('td', { colSpan: 4, className: 'ppp-table__empty' }, 'No posts found')
                        )
                )
            )
        ),

        // Pagination
        data.total_pages > 1 && createElement('div', { className: 'ppp-pagination' },
            createElement('button', {
                className: 'ppp-btn ppp-btn--sm',
                disabled: page <= 1,
                onClick: () => setPage(p => Math.max(1, p - 1))
            }, 'Previous'),
            createElement('span', { className: 'ppp-pagination__info' },
                `Page ${page} of ${data.total_pages}`
            ),
            createElement('button', {
                className: 'ppp-btn ppp-btn--sm',
                disabled: page >= data.total_pages,
                onClick: () => setPage(p => p + 1)
            }, 'Next')
        )
    );
}

/**
 * Dashboard Page - main revenue overview.
 */
import { createElement, useState } from '@wordpress/element';
import { useRevenue } from '../../hooks/useRevenue';
import { formatCurrency, formatNumber, formatPercentage } from '../../utils/formatters';
import StatCard from '../Shared/StatCard';
import LoadingState from '../Shared/LoadingState';
import EmptyState from '../Shared/EmptyState';
import DateRangePicker from '../Shared/DateRangePicker';

export default function DashboardPage() {
    const [period, setPeriod] = useState(pppConfig.defaultRange + 'd');
    const { data, loading, error } = useRevenue(period);

    if (loading) return createElement(LoadingState, { message: 'Loading revenue data...' });
    if (error) return createElement('div', { className: 'ppp-error' }, 'Error: ', error);
    if (!data || !data.totals) return createElement(EmptyState, {
        title: 'No Revenue Data Yet',
        description: 'Connect your revenue sources and sync data to see your per-post earnings.',
    });

    const { totals, top_posts, dead_posts, revenue_trend, by_source } = data;

    return createElement('div', { className: 'ppp-dashboard' },
        // Header
        createElement('div', { className: 'ppp-page-header' },
            createElement('div', null,
                createElement('h1', { className: 'ppp-page-title' }, 'Revenue Dashboard'),
                createElement('p', { className: 'ppp-page-subtitle' }, 'Track exactly how much each post earns')
            ),
            createElement(DateRangePicker, { value: period, onChange: setPeriod })
        ),

        // Stats Grid
        createElement('div', { className: 'ppp-stats-grid' },
            createElement(StatCard, {
                title: 'Total Revenue',
                value: formatCurrency(totals.total_revenue),
                change: totals.revenue_change,
                icon: 'chart-area',
                color: '#6366f1'
            }),
            createElement(StatCard, {
                title: 'Top Post Revenue',
                value: formatCurrency(totals.top_post_revenue),
                subtitle: 'Highest earner',
                icon: 'star-filled',
                color: '#f59e0b'
            }),
            createElement(StatCard, {
                title: 'Dead Posts',
                value: totals.dead_posts_count.toString(),
                subtitle: '$0 revenue',
                icon: 'warning',
                color: '#ef4444'
            }),
            createElement(StatCard, {
                title: 'Top 10 Concentration',
                value: totals.top_10_percent + '%',
                subtitle: 'of total revenue',
                icon: 'chart-pie',
                color: '#8b5cf6'
            })
        ),

        // Revenue Trend
        createElement('div', { className: 'ppp-card' },
            createElement('h3', { className: 'ppp-card__title' }, 'Revenue Trend'),
            createElement('div', { className: 'ppp-chart-container' },
                revenue_trend && revenue_trend.length > 0
                    ? createElement('div', { className: 'ppp-simple-chart' },
                        revenue_trend.map((day, i) =>
                            createElement('div', {
                                key: i,
                                className: 'ppp-simple-chart__bar',
                                style: {
                                    height: `${Math.max(4, (day.revenue / Math.max(...revenue_trend.map(d => d.revenue || 1))) * 100)}%`
                                },
                                title: `${day.date}: ${formatCurrency(day.revenue)}`
                            })
                        )
                    )
                    : createElement('p', { className: 'ppp-muted' }, 'No trend data available')
            )
        ),

        // Two Column Layout
        createElement('div', { className: 'ppp-grid-2' },
            // Top Posts
            createElement('div', { className: 'ppp-card' },
                createElement('h3', { className: 'ppp-card__title' }, 'Top Earning Posts'),
                top_posts && top_posts.length > 0
                    ? createElement('div', { className: 'ppp-top-posts-list' },
                        top_posts.slice(0, 5).map((post, i) =>
                            createElement('div', { key: post.post_id, className: 'ppp-top-post-item' },
                                createElement('span', { className: 'ppp-top-post-item__rank' }, '#' + (i + 1)),
                                createElement('div', { className: 'ppp-top-post-item__info' },
                                    createElement('span', { className: 'ppp-top-post-item__title' }, post.title),
                                    createElement('span', { className: 'ppp-top-post-item__meta' },
                                        formatNumber(post.pageviews) + ' views'
                                    )
                                ),
                                createElement('span', { className: 'ppp-top-post-item__revenue' },
                                    formatCurrency(post.revenue)
                                )
                            )
                        )
                    )
                    : createElement('p', { className: 'ppp-muted' }, 'No data yet')
            ),

            // Revenue by Source
            createElement('div', { className: 'ppp-card' },
                createElement('h3', { className: 'ppp-card__title' }, 'Revenue by Source'),
                by_source && by_source.length > 0
                    ? createElement('div', { className: 'ppp-source-list' },
                        by_source.map(source =>
                            createElement('div', { key: source.source, className: 'ppp-source-item' },
                                createElement('div', { className: 'ppp-source-item__info' },
                                    createElement('span', { className: 'ppp-source-item__name' }, source.label),
                                    createElement('span', { className: 'ppp-source-item__pct' }, source.percentage + '%')
                                ),
                                createElement('div', { className: 'ppp-source-item__bar' },
                                    createElement('div', {
                                        className: 'ppp-source-item__fill',
                                        style: { width: source.percentage + '%' }
                                    })
                                ),
                                createElement('span', { className: 'ppp-source-item__amount' },
                                    formatCurrency(source.revenue)
                                )
                            )
                        )
                    )
                    : createElement('p', { className: 'ppp-muted' }, 'No data yet')
            )
        ),

        // Dead Posts Summary
        dead_posts && dead_posts.count > 0 && createElement('div', { className: 'ppp-card ppp-card--warning' },
            createElement('h3', { className: 'ppp-card__title' },
                '\u26A0\uFE0F ', dead_posts.count, ' Posts Making $0'
            ),
            createElement('p', { className: 'ppp-card__description' },
                'These posts generate zero revenue. Consider updating, promoting, or consolidating them.'
            )
        )
    );
}

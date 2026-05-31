/**
 * Profit Per Post - Main React Application Entry Point.
 */
import { createElement, useState, render } from '@wordpress/element';
import DashboardPage from './components/Dashboard/DashboardPage';
import PostsListPage from './components/Posts/PostsListPage';
import SettingsPage from './components/Settings/SettingsPage';
import OnboardingWizard from './components/Onboarding/OnboardingWizard';
import { ROUTES } from './utils/constants';

/**
 * Main App component with routing.
 */
function App() {
    const [currentRoute, setCurrentRoute] = useState(getInitialRoute());
    const [selectedPostId, setSelectedPostId] = useState(null);
    const [isOnboarded, setIsOnboarded] = useState(pppConfig.isOnboarded);

    // Show onboarding wizard if not completed.
    if (!isOnboarded) {
        return createElement(OnboardingWizard, {
            onComplete: () => setIsOnboarded(true)
        });
    }

    const handleViewPost = (postId) => {
        setSelectedPostId(postId);
        setCurrentRoute(ROUTES.POST_DETAIL);
    };

    const renderPage = () => {
        switch (currentRoute) {
            case ROUTES.POSTS:
                return createElement(PostsListPage, { onViewPost: handleViewPost });
            case ROUTES.SETTINGS:
                return createElement(SettingsPage);
            case ROUTES.DASHBOARD:
            default:
                return createElement(DashboardPage);
        }
    };

    return createElement('div', { className: 'ppp-app' },
        // Sidebar Navigation
        createElement('nav', { className: 'ppp-sidebar' },
            createElement('div', { className: 'ppp-sidebar__brand' },
                createElement('span', { className: 'dashicons dashicons-chart-area' }),
                createElement('span', { className: 'ppp-sidebar__brand-text' }, 'Profit Per Post')
            ),
            createElement('ul', { className: 'ppp-sidebar__nav' },
                createElement('li', null,
                    createElement('button', {
                        className: `ppp-sidebar__link ${currentRoute === ROUTES.DASHBOARD ? 'ppp-sidebar__link--active' : ''}`,
                        onClick: () => setCurrentRoute(ROUTES.DASHBOARD)
                    }, createElement('span', { className: 'dashicons dashicons-dashboard' }), ' Dashboard')
                ),
                createElement('li', null,
                    createElement('button', {
                        className: `ppp-sidebar__link ${currentRoute === ROUTES.POSTS ? 'ppp-sidebar__link--active' : ''}`,
                        onClick: () => setCurrentRoute(ROUTES.POSTS)
                    }, createElement('span', { className: 'dashicons dashicons-admin-post' }), ' All Posts')
                ),
                pppConfig.capabilities.canManageSettings && createElement('li', null,
                    createElement('button', {
                        className: `ppp-sidebar__link ${currentRoute === ROUTES.SETTINGS ? 'ppp-sidebar__link--active' : ''}`,
                        onClick: () => setCurrentRoute(ROUTES.SETTINGS)
                    }, createElement('span', { className: 'dashicons dashicons-admin-generic' }), ' Settings')
                )
            )
        ),

        // Main Content
        createElement('main', { className: 'ppp-main' },
            renderPage()
        )
    );
}

/**
 * Determine initial route based on current WordPress admin page.
 */
function getInitialRoute() {
    const page = pppConfig.currentPage || '';
    if (page.includes('posts')) return ROUTES.POSTS;
    if (page.includes('settings')) return ROUTES.SETTINGS;
    return ROUTES.DASHBOARD;
}

/**
 * Mount the React app.
 */
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('ppp-app-root');
    if (container) {
        render(createElement(App), container);
    }
});

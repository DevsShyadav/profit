/**
 * Onboarding Wizard component.
 */
import { createElement, useState } from '@wordpress/element';
import API from '../../utils/api';

export default function OnboardingWizard({ onComplete }) {
    const [step, setStep] = useState(0);

    const steps = [
        { title: 'Welcome to Profit Per Post', description: 'Track exactly how much revenue each of your blog posts generates.' },
        { title: 'Connect Google Analytics', description: 'We\'ll pull traffic data (pageviews) to calculate RPM per post.' },
        { title: 'Connect Ad Revenue', description: 'Connect AdSense or Mediavine to see ad revenue per post.' },
        { title: 'WooCommerce & Affiliates', description: 'Track product sales and affiliate clicks attributed to each post.' },
        { title: 'You\'re All Set!', description: 'Your dashboard will populate as data syncs. This usually takes 2-5 minutes.' },
    ];

    const handleSkip = async () => {
        await API.settings.completeOnboarding();
        onComplete();
    };

    const handleComplete = async () => {
        await API.settings.completeOnboarding();
        onComplete();
    };

    const current = steps[step];

    return createElement('div', { className: 'ppp-onboarding' },
        createElement('div', { className: 'ppp-onboarding__card' },
            // Progress
            createElement('div', { className: 'ppp-onboarding__progress' },
                steps.map((_, i) =>
                    createElement('div', {
                        key: i,
                        className: `ppp-onboarding__dot ${i <= step ? 'ppp-onboarding__dot--active' : ''}`
                    })
                )
            ),

            // Content
            createElement('div', { className: 'ppp-onboarding__content' },
                createElement('h2', { className: 'ppp-onboarding__title' }, current.title),
                createElement('p', { className: 'ppp-onboarding__description' }, current.description),

                step === 0 && createElement('div', { className: 'ppp-onboarding__features' },
                    ['Google Analytics traffic per post', 'AdSense/Mediavine ad revenue', 'WooCommerce sales attribution', 'Affiliate link click tracking'].map(f =>
                        createElement('div', { key: f, className: 'ppp-onboarding__feature' }, '\u2713 ', f)
                    )
                )
            ),

            // Actions
            createElement('div', { className: 'ppp-onboarding__actions' },
                step < steps.length - 1
                    ? createElement('div', { className: 'ppp-flex-between' },
                        createElement('button', { className: 'ppp-btn ppp-btn--ghost', onClick: handleSkip }, 'Skip Setup'),
                        createElement('button', { className: 'ppp-btn ppp-btn--primary', onClick: () => setStep(s => s + 1) }, 'Continue')
                    )
                    : createElement('button', { className: 'ppp-btn ppp-btn--primary ppp-btn--lg', onClick: handleComplete }, 'Go to Dashboard')
            )
        )
    );
}

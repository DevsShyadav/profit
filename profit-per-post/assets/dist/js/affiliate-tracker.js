/**
 * Profit Per Post - Affiliate Link Click Tracker.
 * Tracks outbound clicks on affiliate links from blog posts.
 */
(function() {
    'use strict';

    if (typeof pppAffiliateTracker === 'undefined') return;

    var config = pppAffiliateTracker;
    var patterns = config.patterns || [];
    var postId = config.postId;
    var restUrl = config.restUrl;
    var nonce = config.nonce;

    if (!postId || !patterns.length) return;

    /**
     * Check if a URL matches any tracked affiliate pattern.
     */
    function isAffiliateLink(url) {
        if (!url) return false;
        var urlLower = url.toLowerCase();
        for (var i = 0; i < patterns.length; i++) {
            if (urlLower.indexOf(patterns[i].toLowerCase()) !== -1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Send click tracking beacon.
     */
    function trackClick(linkUrl, linkLabel) {
        var data = {
            post_id: postId,
            link_url: linkUrl,
            link_label: linkLabel || ''
        };

        // Use sendBeacon for reliability (doesn't block navigation).
        if (navigator.sendBeacon) {
            var blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            var url = restUrl + '?_wpnonce=' + nonce;
            navigator.sendBeacon(url, blob);
        } else {
            // Fallback to fetch.
            fetch(restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(data),
                keepalive: true
            }).catch(function() {});
        }
    }

    /**
     * Handle click events on the document.
     */
    function handleClick(event) {
        var target = event.target;

        // Walk up the DOM to find the anchor element.
        while (target && target.tagName !== 'A') {
            target = target.parentElement;
        }

        if (!target || !target.href) return;

        var href = target.href;

        // Skip internal links.
        if (href.indexOf(window.location.hostname) !== -1) return;

        // Check if it's an affiliate link.
        if (isAffiliateLink(href)) {
            var label = target.innerText || target.textContent || '';
            trackClick(href, label.trim().substring(0, 255));
        }
    }

    // Attach listener.
    document.addEventListener('click', handleClick, true);
})();

=== Profit Per Post ===
Contributors: profitperpost
Tags: revenue, analytics, adsense, woocommerce, affiliate, blogging, income
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Shows exact revenue each blog post generates by combining Google Analytics, AdSense, Mediavine, WooCommerce, and affiliate tracking.

== Description ==

**Profit Per Post** is the ultimate revenue tracking plugin for content creators. It connects to your revenue sources and shows you exactly how much money each individual blog post earns.

= The Problem =
You have 100+ blog posts but no idea which ones make money and which are dead weight. You can't prioritize what to update, promote, or delete.

= The Solution =
Profit Per Post connects to Google Analytics (traffic) + AdSense/Mediavine (ad revenue) + WooCommerce (sales) + affiliate links (clicks) and calculates EXACT revenue per post per month.

= Key Features =

* **Per-Post Revenue Tracking** - See exactly how much each post earns
* **Google Analytics Integration** - Automatic traffic data per post
* **AdSense Revenue Tracking** - Ad earnings attributed to specific posts
* **Mediavine Support** - Premium ad network revenue per post
* **WooCommerce Attribution** - Track which posts drive product sales
* **Affiliate Click Tracking** - Monitor outbound affiliate link clicks
* **Beautiful Dashboard** - Premium SaaS-quality interface
* **Revenue Trends** - See how earnings change over time
* **Dead Post Detection** - Identify posts earning $0
* **CSV Export** - Export all revenue data
* **Background Sync** - Data updates automatically every 6 hours

= Why Profit Per Post? =

No other WordPress plugin gives you "this specific post made $847 this month from ads + affiliates + product sales combined." This is the HOLY GRAIL for content creators.

"Stop writing new posts. Your existing Post #47 makes $0. Post #12 makes $340/month. Focus on Post #12." Life-changing clarity.

== Installation ==

1. Upload the `profit-per-post` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Profit Per Post** in the admin sidebar
4. Follow the setup wizard to connect your revenue sources
5. Wait for the initial data sync (2-5 minutes)

== Frequently Asked Questions ==

= What revenue sources are supported? =
Google Analytics (traffic), Google AdSense (ad revenue), Mediavine (ad revenue), WooCommerce (product sales), and built-in affiliate link click tracking.

= How often does data sync? =
By default, every 6 hours. You can configure this to hourly, every 12 hours, or daily. You can also trigger manual syncs anytime.

= Does this slow down my website? =
No. All API calls happen in the background via WordPress cron. The only frontend code is a tiny affiliate click tracker (under 2KB).

= Is my data secure? =
Yes. All API credentials are encrypted with AES-256-CBC. Access is restricted by WordPress capabilities. All inputs are sanitized and outputs are escaped.

= Does it work with any theme? =
Yes. The plugin only adds admin-side functionality. It has zero impact on your frontend theme.

= How accurate is the WooCommerce attribution? =
We use cookie-based tracking with configurable expiry (default 30 days). You can choose between first-touch and last-touch attribution models.

== Screenshots ==

1. Revenue Dashboard - see total earnings at a glance
2. Per-Post Revenue Table - sortable list of all posts with earnings
3. Revenue by Source breakdown - see where your money comes from
4. Settings - connect your revenue sources
5. Onboarding Wizard - easy setup in under 2 minutes

== Changelog ==

= 1.0.0 =
* Initial release
* Google Analytics GA4 integration
* Google AdSense integration
* Mediavine integration
* WooCommerce sales attribution
* Built-in affiliate link click tracking
* Premium SaaS-quality dashboard
* Revenue trends and insights
* Dead post detection
* CSV export
* Background sync with WP-Cron
* Mobile responsive admin UI

== Upgrade Notice ==

= 1.0.0 =
Initial release of Profit Per Post.

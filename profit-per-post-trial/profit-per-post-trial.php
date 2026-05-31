<?php
/**
 * Plugin Name: Profit Per Post - Trial
 * Plugin URI: https://devsarun.io/plugin/profit/
 * Description: 24-Hour Trial: Shows exact revenue each blog post generates. Upgrade to Pro for unlimited access.
 * Version: 1.0.0-trial
 * Author: Profit Per Post
 * Author URI: https://devsarun.io/plugin/profit/
 * License: GPL-2.0+
 * Text Domain: profit-per-post-trial
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PPP_TRIAL_VERSION', '1.0.0-trial' );
define( 'PPP_TRIAL_FILE', __FILE__ );
define( 'PPP_TRIAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPP_TRIAL_URL', plugin_dir_url( __FILE__ ) );
define( 'PPP_TRIAL_BASENAME', plugin_basename( __FILE__ ) );
define( 'PPP_TRIAL_HOURS', 24 );
define( 'PPP_UPGRADE_URL', 'https://devsarun.io/plugin/profit/' );

/**
 * On activation: record the trial start time.
 */
function ppp_trial_activate() {
    if ( ! get_option( 'ppp_trial_started' ) ) {
        update_option( 'ppp_trial_started', time() );
    }
}
register_activation_hook( __FILE__, 'ppp_trial_activate' );

/**
 * Check if trial has expired.
 */
function ppp_trial_is_expired() {
    $started = get_option( 'ppp_trial_started', 0 );
    if ( ! $started ) {
        return false;
    }
    $elapsed = time() - (int) $started;
    $limit   = PPP_TRIAL_HOURS * 3600;
    return $elapsed >= $limit;
}

/**
 * Get remaining time in seconds.
 */
function ppp_trial_remaining() {
    $started = get_option( 'ppp_trial_started', 0 );
    if ( ! $started ) {
        return PPP_TRIAL_HOURS * 3600;
    }
    $elapsed   = time() - (int) $started;
    $limit     = PPP_TRIAL_HOURS * 3600;
    $remaining = $limit - $elapsed;
    return max( 0, $remaining );
}

/**
 * If trial expired: deactivate plugin and delete it.
 */
function ppp_trial_check_expiry() {
    if ( ! is_admin() ) {
        return;
    }
    if ( ! ppp_trial_is_expired() ) {
        return;
    }
    // Deactivate this plugin.
    deactivate_plugins( PPP_TRIAL_BASENAME );
    // Delete trial options.
    delete_option( 'ppp_trial_started' );
    // Try to delete plugin files.
    if ( function_exists( 'delete_plugins' ) ) {
        delete_plugins( array( PPP_TRIAL_BASENAME ) );
    }
    // Redirect to plugins page with message.
    wp_redirect( admin_url( 'plugins.php?ppp_trial_expired=1' ) );
    exit;
}
add_action( 'admin_init', 'ppp_trial_check_expiry', 1 );

/**
 * Show expired notice on plugins page.
 */
function ppp_trial_expired_notice() {
    if ( ! isset( $_GET['ppp_trial_expired'] ) ) {
        return;
    }
    echo '<div class="notice notice-error"><p><strong>Profit Per Post Trial has expired!</strong> Your 24-hour trial period is over. The plugin has been removed. <a href="' . esc_url( PPP_UPGRADE_URL ) . '" target="_blank" style="color:#16a34a;font-weight:700;">Upgrade to Pro for just $29 &rarr;</a></p></div>';
}
add_action( 'admin_notices', 'ppp_trial_expired_notice' );

/**
 * Add countdown timer + upgrade button in admin bar.
 */
function ppp_trial_admin_bar( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $remaining = ppp_trial_remaining();
    $hours     = floor( $remaining / 3600 );
    $minutes   = floor( ( $remaining % 3600 ) / 60 );
    $time_text = sprintf( '%02d:%02d', $hours, $minutes );

    $wp_admin_bar->add_node( array(
        'id'    => 'ppp-trial-timer',
        'title' => '<span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:4px;font-weight:700;font-size:12px;">TRIAL: ' . $time_text . ' left</span>',
        'href'  => false,
        'meta'  => array( 'class' => 'ppp-trial-bar-item' ),
    ) );

    $wp_admin_bar->add_node( array(
        'id'    => 'ppp-trial-upgrade',
        'title' => '<span style="background:linear-gradient(135deg,#059669,#10b981);color:#fff;padding:4px 14px;border-radius:6px;font-weight:700;font-size:12px;">UPGRADE TO PRO &rarr;</span>',
        'href'  => PPP_UPGRADE_URL,
        'meta'  => array( 'target' => '_blank', 'class' => 'ppp-trial-bar-item' ),
    ) );
}
add_action( 'admin_bar_menu', 'ppp_trial_admin_bar', 999 );

/**
 * Add a top banner inside plugin pages.
 */
function ppp_trial_top_banner() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'profit-per-post' ) === false ) {
        return;
    }
    $remaining = ppp_trial_remaining();
    $hours     = floor( $remaining / 3600 );
    $minutes   = floor( ( $remaining % 3600 ) / 60 );
    $seconds   = $remaining % 60;
    ?>
    <div id="ppp-trial-banner" style="position:sticky;top:32px;z-index:9999;background:linear-gradient(90deg,#064e3b,#059669);color:#fff;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-family:-apple-system,sans-serif;">
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:20px;">&#9200;</span>
            <div>
                <strong style="font-size:14px;">Trial Version</strong>
                <span style="font-size:13px;opacity:0.9;margin-left:8px;">Expires in <span id="ppp-countdown" style="font-weight:800;font-size:15px;background:rgba(255,255,255,0.15);padding:2px 8px;border-radius:4px;"><?php echo sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds); ?></span></span>
            </div>
        </div>
        <a href="<?php echo esc_url( PPP_UPGRADE_URL ); ?>" target="_blank" style="background:#fff;color:#059669;padding:8px 20px;border-radius:8px;font-weight:700;font-size:13px;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,0.15);transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)';">
            Upgrade to Pro — $29
        </a>
    </div>
    <script>
    (function(){
        var remaining = <?php echo (int) $remaining; ?>;
        var el = document.getElementById('ppp-countdown');
        if (!el) return;
        setInterval(function(){
            remaining--;
            if (remaining <= 0) { location.reload(); return; }
            var h = Math.floor(remaining/3600);
            var m = Math.floor((remaining%3600)/60);
            var s = remaining%60;
            el.textContent = (h<10?'0'+h:h) + ':' + (m<10?'0'+m:m) + ':' + (s<10?'0'+s:s);
        }, 1000);
    })();
    </script>
    <?php
}
add_action( 'admin_notices', 'ppp_trial_top_banner' );

/**
 * Add "Upgrade" link in plugins page.
 */
function ppp_trial_plugin_links( $links ) {
    $upgrade = '<a href="' . esc_url( PPP_UPGRADE_URL ) . '" target="_blank" style="color:#059669;font-weight:700;">Upgrade to Pro</a>';
    array_unshift( $links, $upgrade );
    return $links;
}
add_filter( 'plugin_action_links_' . PPP_TRIAL_BASENAME, 'ppp_trial_plugin_links' );

/**
 * Modify plugin description row to show trial status.
 */
function ppp_trial_plugin_row_meta( $meta, $file ) {
    if ( $file === PPP_TRIAL_BASENAME ) {
        $remaining = ppp_trial_remaining();
        $hours     = floor( $remaining / 3600 );
        $minutes   = floor( ( $remaining % 3600 ) / 60 );
        $meta[]    = '<span style="color:#d97706;font-weight:600;">Trial: ' . $hours . 'h ' . $minutes . 'm remaining</span>';
    }
    return $meta;
}
add_filter( 'plugin_row_meta', 'ppp_trial_plugin_row_meta', 10, 2 );

// =============================================
// LOAD THE ACTUAL PLUGIN (same as Pro version)
// =============================================

// All the Pro plugin constants.
define( 'PPP_VERSION', '1.0.0' );
define( 'PPP_PLUGIN_FILE', __FILE__ );
define( 'PPP_PLUGIN_DIR', PPP_TRIAL_DIR );
define( 'PPP_PLUGIN_URL', PPP_TRIAL_URL );
define( 'PPP_PLUGIN_BASENAME', PPP_TRIAL_BASENAME );
define( 'PPP_TABLE_PREFIX', 'ppp_' );
define( 'PPP_MIN_WP_VERSION', '6.0' );
define( 'PPP_MIN_PHP_VERSION', '7.4' );
define( 'PPP_REST_NAMESPACE', 'ppp/v1' );
define( 'PPP_ENCRYPTION_KEY_OPTION', 'ppp_encryption_key' );
define( 'PPP_DEBUG', get_option( 'ppp_debug_mode', false ) );

// Load autoloader and run plugin.
require_once PPP_TRIAL_DIR . 'includes/class-autoloader.php';
\ProfitPerPost\Autoloader::register();

function ppp_trial_run() {
    // Check requirements.
    global $wp_version;
    if ( version_compare( PHP_VERSION, PPP_MIN_PHP_VERSION, '<' ) || version_compare( $wp_version, PPP_MIN_WP_VERSION, '<' ) ) {
        return;
    }
    $plugin = \ProfitPerPost\Plugin::get_instance();
    $plugin->run();
}
add_action( 'plugins_loaded', 'ppp_trial_run' );

// Activation hook for main plugin logic.
function ppp_trial_main_activate() {
    require_once PPP_TRIAL_DIR . 'includes/class-activator.php';
    \ProfitPerPost\Activator::activate();
}
register_activation_hook( __FILE__, 'ppp_trial_main_activate' );

// Deactivation hook.
function ppp_trial_main_deactivate() {
    require_once PPP_TRIAL_DIR . 'includes/class-deactivator.php';
    \ProfitPerPost\Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'ppp_trial_main_deactivate' );

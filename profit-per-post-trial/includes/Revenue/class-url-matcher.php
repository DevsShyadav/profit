<?php
/**
 * URL Matcher - maps URLs/paths to WordPress post IDs.
 *
 * @package ProfitPerPost\Revenue
 */

namespace ProfitPerPost\Revenue;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class UrlMatcher
 *
 * Resolves page paths and URLs from external APIs (GA, AdSense, Mediavine)
 * to WordPress post IDs. Handles various URL formats, trailing slashes,
 * query parameters, and permalink structures.
 */
class UrlMatcher {

    /**
     * Cache of matched URLs to post IDs.
     *
     * @var array
     */
    private static $cache = array();

    /**
     * Match a URL path to a WordPress post ID.
     *
     * @param string $url_or_path Full URL or path (e.g., /2024/01/my-post/ or https://example.com/my-post/).
     * @return int|false Post ID or false if no match found.
     */
    public function match( $url_or_path ) {
        // Normalize the input.
        $path = $this->normalize_path( $url_or_path );

        if ( empty( $path ) || '/' === $path ) {
            return false;
        }

        // Check static cache first.
        if ( isset( self::$cache[ $path ] ) ) {
            return self::$cache[ $path ];
        }

        // Try multiple matching strategies.
        $post_id = $this->try_url_to_postid( $path );

        if ( ! $post_id ) {
            $post_id = $this->try_slug_match( $path );
        }

        if ( ! $post_id ) {
            $post_id = $this->try_path_match( $path );
        }

        if ( ! $post_id ) {
            $post_id = $this->try_fuzzy_match( $path );
        }

        // Cache the result (even false to avoid repeated lookups).
        self::$cache[ $path ] = $post_id;

        return $post_id;
    }

    /**
     * Normalize a URL or path for consistent matching.
     *
     * @param string $url_or_path The URL or path to normalize.
     * @return string Normalized path.
     */
    private function normalize_path( $url_or_path ) {
        // If it's a full URL, extract the path.
        if ( 0 === strpos( $url_or_path, 'http' ) ) {
            $parsed = wp_parse_url( $url_or_path );
            $path   = isset( $parsed['path'] ) ? $parsed['path'] : '';
        } else {
            $path = $url_or_path;
        }

        // Remove query string and fragment.
        $path = strtok( $path, '?' );
        $path = strtok( $path, '#' );

        // Ensure leading slash.
        if ( 0 !== strpos( $path, '/' ) ) {
            $path = '/' . $path;
        }

        // Remove WordPress installation subdirectory if present.
        $home_path = wp_parse_url( home_url(), PHP_URL_PATH );
        if ( $home_path && '/' !== $home_path ) {
            if ( 0 === strpos( $path, $home_path ) ) {
                $path = substr( $path, strlen( $home_path ) );
                if ( empty( $path ) ) {
                    $path = '/';
                }
            }
        }

        // Normalize multiple slashes.
        $path = preg_replace( '#/+#', '/', $path );

        return $path;
    }

    /**
     * Try WordPress's built-in url_to_postid function.
     *
     * @param string $path The normalized path.
     * @return int|false
     */
    private function try_url_to_postid( $path ) {
        $url     = home_url( $path );
        $post_id = url_to_postid( $url );

        if ( $post_id && $this->is_valid_post( $post_id ) ) {
            return $post_id;
        }

        // Try with trailing slash.
        $url_with_slash = home_url( trailingslashit( $path ) );
        $post_id        = url_to_postid( $url_with_slash );

        if ( $post_id && $this->is_valid_post( $post_id ) ) {
            return $post_id;
        }

        // Try without trailing slash.
        $url_without_slash = home_url( untrailingslashit( $path ) );
        $post_id           = url_to_postid( $url_without_slash );

        if ( $post_id && $this->is_valid_post( $post_id ) ) {
            return $post_id;
        }

        return false;
    }

    /**
     * Try matching by post slug (last segment of path).
     *
     * @param string $path The normalized path.
     * @return int|false
     */
    private function try_slug_match( $path ) {
        global $wpdb;

        // Extract the slug (last meaningful segment).
        $path     = untrailingslashit( $path );
        $segments = explode( '/', trim( $path, '/' ) );
        $slug     = end( $segments );

        if ( empty( $slug ) ) {
            return false;
        }

        // Remove file extensions if present.
        $slug = preg_replace( '/\.(html?|php|asp)$/i', '', $slug );

        // Query by post_name (slug).
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_name = %s
                AND post_type IN ('post', 'page')
                AND post_status = 'publish'
                LIMIT 1",
                $slug
            )
        );

        if ( $post_id ) {
            return (int) $post_id;
        }

        return false;
    }

    /**
     * Try matching by full path (for sites with category in permalink).
     *
     * @param string $path The normalized path.
     * @return int|false
     */
    private function try_path_match( $path ) {
        global $wpdb;

        // Try to match posts where the permalink contains this path.
        $path_clean = trim( untrailingslashit( $path ), '/' );

        if ( empty( $path_clean ) ) {
            return false;
        }

        // For paths like /category/post-slug or /2024/01/post-slug.
        $segments = explode( '/', $path_clean );
        $slug     = end( $segments );

        // Remove date components if present (YYYY/MM/DD patterns).
        $slug_candidates = array( $slug );

        // Also try joining last two segments (for slugs with categories).
        if ( count( $segments ) > 1 ) {
            $slug_candidates[] = $segments[ count( $segments ) - 2 ] . '-' . $slug;
        }

        foreach ( $slug_candidates as $candidate ) {
            $post_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                    WHERE post_name = %s
                    AND post_type = 'post'
                    AND post_status = 'publish'
                    LIMIT 1",
                    $candidate
                )
            );

            if ( $post_id ) {
                return (int) $post_id;
            }
        }

        return false;
    }

    /**
     * Try fuzzy matching using LIKE queries.
     *
     * @param string $path The normalized path.
     * @return int|false
     */
    private function try_fuzzy_match( $path ) {
        global $wpdb;

        $path_clean = trim( untrailingslashit( $path ), '/' );

        if ( empty( $path_clean ) || strlen( $path_clean ) < 3 ) {
            return false;
        }

        // Extract what looks like a slug from the path.
        $segments = explode( '/', $path_clean );
        $slug     = end( $segments );

        // Remove common suffixes.
        $slug = preg_replace( '/\.(html?|php|asp|aspx)$/i', '', $slug );
        $slug = preg_replace( '/-\d+$/', '', $slug ); // Remove trailing numbers.

        if ( strlen( $slug ) < 3 ) {
            return false;
        }

        // Try LIKE match on post_name.
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_name LIKE %s
                AND post_type = 'post'
                AND post_status = 'publish'
                ORDER BY post_date DESC
                LIMIT 1",
                '%' . $wpdb->esc_like( $slug ) . '%'
            )
        );

        if ( $post_id ) {
            return (int) $post_id;
        }

        return false;
    }

    /**
     * Check if a post ID is a valid, published post.
     *
     * @param int $post_id The post ID to validate.
     * @return bool
     */
    private function is_valid_post( $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return false;
        }

        // Only match posts and pages.
        if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
            return false;
        }

        // Only match published posts.
        if ( 'publish' !== $post->post_status ) {
            return false;
        }

        return true;
    }

    /**
     * Batch match multiple URLs at once (more efficient).
     *
     * @param array $urls Array of URLs/paths to match.
     * @return array Associative array of url => post_id (only matched ones).
     */
    public function batch_match( $urls ) {
        $results = array();

        foreach ( $urls as $url ) {
            $post_id = $this->match( $url );
            if ( $post_id ) {
                $results[ $url ] = $post_id;
            }
        }

        return $results;
    }

    /**
     * Clear the internal cache.
     *
     * @return void
     */
    public static function clear_cache() {
        self::$cache = array();
    }

    /**
     * Get the reverse: post ID to URL path.
     *
     * @param int $post_id The post ID.
     * @return string The permalink path (relative).
     */
    public function get_path_for_post( $post_id ) {
        $permalink = get_permalink( $post_id );

        if ( ! $permalink ) {
            return '';
        }

        $parsed = wp_parse_url( $permalink );
        return isset( $parsed['path'] ) ? $parsed['path'] : '';
    }
}

<?php
/**
 * AI Endpoint - AI-powered post optimization suggestions.
 *
 * @package ProfitPerPost\API
 */

namespace ProfitPerPost\API;

use ProfitPerPost\Security\CapabilityManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIEndpoint extends RestController {

    public function register_routes() {
        register_rest_route( $this->namespace, '/ai/settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_ai_settings' ),
                'permission_callback' => array( CapabilityManager::class, 'rest_permission_admin' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'save_ai_settings' ),
                'permission_callback' => array( CapabilityManager::class, 'rest_permission_admin' ),
            ),
        ));

        register_rest_route( $this->namespace, '/ai/suggestions', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'get_suggestions' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
        ));
    }

    public function get_ai_settings( $request ) {
        $settings = array(
            'provider' => get_option( 'ppp_ai_provider', 'groq' ),
            'api_key'  => get_option( 'ppp_ai_api_key', '' ) ? '••••••••' : '',
        );
        return $this->success( $settings );
    }

    public function save_ai_settings( $request ) {
        $params   = $request->get_json_params();
        $provider = isset( $params['provider'] ) ? sanitize_text_field( $params['provider'] ) : 'groq';
        $api_key  = isset( $params['api_key'] ) ? sanitize_text_field( $params['api_key'] ) : '';

        $valid_providers = array( 'openai', 'gemini', 'groq' );
        if ( ! in_array( $provider, $valid_providers, true ) ) {
            return $this->error( 'Invalid AI provider.' );
        }

        update_option( 'ppp_ai_provider', $provider );
        if ( ! empty( $api_key ) && '••••••••' !== $api_key ) {
            update_option( 'ppp_ai_api_key', $api_key );
        }

        return $this->success( array( 'message' => 'AI settings saved.' ) );
    }

    public function get_suggestions( $request ) {
        $params  = $request->get_json_params();
        $post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;

        if ( ! $post_id ) {
            return $this->error( 'Post ID is required.' );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return $this->error( 'Post not found.' );
        }

        $api_key  = get_option( 'ppp_ai_api_key', '' );
        $provider = get_option( 'ppp_ai_provider', 'groq' );

        if ( empty( $api_key ) ) {
            return $this->error( 'AI API key not configured. Go to Settings > AI Optimizer to add your API key.' );
        }

        $post_content = wp_strip_all_tags( $post->post_content );
        $post_content = mb_substr( $post_content, 0, 2000 );

        $prompt = "You are an expert SEO and content monetization consultant. Analyze this blog post and provide specific, actionable suggestions to increase its revenue.\n\n";
        $prompt .= "Post Title: " . $post->post_title . "\n";
        $prompt .= "Post Content (first 2000 chars): " . $post_content . "\n\n";
        $prompt .= "This post currently generates $0 revenue. Provide 5-7 specific suggestions covering:\n";
        $prompt .= "1. SEO improvements (title, meta, keywords)\n";
        $prompt .= "2. Content structure improvements\n";
        $prompt .= "3. Ad placement optimization\n";
        $prompt .= "4. Internal linking strategy\n";
        $prompt .= "5. Call-to-action improvements\n";
        $prompt .= "6. Monetization opportunities\n";
        $prompt .= "Be specific and actionable. Format with bullet points.";

        $response = $this->call_ai_api( $provider, $api_key, $prompt );

        if ( is_wp_error( $response ) ) {
            return $this->error( $response->get_error_message() );
        }

        return $this->success( $response );
    }

    private function call_ai_api( $provider, $api_key, $prompt ) {
        switch ( $provider ) {
            case 'openai':
                return $this->call_openai( $api_key, $prompt );
            case 'gemini':
                return $this->call_gemini( $api_key, $prompt );
            case 'groq':
            default:
                return $this->call_groq( $api_key, $prompt );
        }
    }

    private function call_openai( $api_key, $prompt ) {
        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'    => 'gpt-4o-mini',
                'messages' => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'max_tokens'  => 1000,
                'temperature' => 0.7,
            )),
        ));

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'ai_error', $body['error']['message'] ?? 'OpenAI API error' );
        }

        return isset( $body['choices'][0]['message']['content'] )
            ? $body['choices'][0]['message']['content']
            : 'No suggestions generated.';
    }

    private function call_gemini( $api_key, $prompt ) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key;

        $response = wp_remote_post( $url, array(
            'timeout' => 60,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'contents' => array(
                    array( 'parts' => array( array( 'text' => $prompt ) ) ),
                ),
                'generationConfig' => array(
                    'maxOutputTokens' => 1000,
                    'temperature'     => 0.7,
                ),
            )),
        ));

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'ai_error', $body['error']['message'] ?? 'Gemini API error' );
        }

        return isset( $body['candidates'][0]['content']['parts'][0]['text'] )
            ? $body['candidates'][0]['content']['parts'][0]['text']
            : 'No suggestions generated.';
    }

    private function call_groq( $api_key, $prompt ) {
        $response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'    => 'llama-3.1-8b-instant',
                'messages' => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'max_tokens'  => 1000,
                'temperature' => 0.7,
            )),
        ));

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'ai_error', $body['error']['message'] ?? 'Groq API error' );
        }

        return isset( $body['choices'][0]['message']['content'] )
            ? $body['choices'][0]['message']['content']
            : 'No suggestions generated.';
    }
}

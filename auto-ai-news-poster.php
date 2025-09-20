<?php

// TEMPORARY: Force PHP error logging for debugging (START)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', plugin_dir_path(__FILE__) . 'php_error.log');
// TEMPORARY: Force PHP error logging for debugging (END)

/**
 * Plugin Name: Auto AI News Poster
 * Description: Un plugin care preia știri de pe minim trei surse, le analizează și publică un articol obiectiv în mod automat sau manual.
 * Version: 1.15.3
 * Author: Gabriel Sandu
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include fișierele necesare
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-metabox.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-parser.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-hooks.php';

// --- Asset Enqueuing ---
add_action('admin_enqueue_scripts', 'auto_ai_news_poster_enqueue_admin_assets');

function auto_ai_news_poster_enqueue_admin_assets($hook)
{
    // For debugging: log the hook and screen ID on every admin page load
    $screen = get_current_screen();
    $current_screen_id = $screen ? $screen->id : 'no_screen';
    error_log("--- AANP Enqueue Check --- Hook: {$hook} | Screen ID: {$current_screen_id} ---");

    // --- Settings Page Assets ---
    // The hook for a submenu page under "Posts" is 'posts_page_{submenu_slug}'
    if ($current_screen_id === 'posts_page_auto-ai-news-poster') {
        error_log('✅ AANP: Settings page MATCH. Enqueuing settings assets.');
        echo '<script type="text/javascript">console.log("✅ AANP: Settings page MATCH. Enqueuing settings assets.");</script>';

        // Enqueue the main stylesheet
        wp_enqueue_style(
            'auto-ai-news-poster-styles',
            plugin_dir_url(__FILE__) . 'includes/css/auto-ai-news-poster.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'includes/css/auto-ai-news-poster.css') // Cache busting
        );

        // Enqueue the settings-specific JavaScript file
        wp_enqueue_script(
            'auto-ai-news-poster-settings-js',
            plugin_dir_url(__FILE__) . 'includes/js/auto-ai-news-poster-settings.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . 'includes/js/auto-ai-news-poster-settings.js'),
            true
        );

        // Settings page does not need localized script for AJAX calls as they are handled differently
    }

    // --- Post Edit Page Assets ---
    if ($screen && ($current_screen_id === 'post' || $screen->post_type === 'post')) {
        error_log('✅ AANP: Post edit page MATCH. Enqueuing metabox assets.');

        // Enqueue the metabox-specific JavaScript file
        wp_enqueue_script(
            'auto-ai-news-poster-metabox-js',
            plugin_dir_url(__FILE__) . 'includes/js/auto-ai-news-poster-metabox.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . 'includes/js/auto-ai-news-poster-metabox.js'),
            true
        );

        // Pass PHP variables to the metabox script
        wp_localize_script('auto-ai-news-poster-metabox-js', 'autoAiNewsPosterAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'generate_image_nonce' => wp_create_nonce('generate_image_nonce'),
            'get_article_nonce' => wp_create_nonce('get_article_from_sources_nonce'),
        ]);
    }
}

// Remove the old, problematic inline asset loading method
// This is critical. We ensure the old function is not being called.
$tag = 'admin_head';
$function_to_remove = 'auto_ai_news_poster_fix_css_mime_type';
if (has_action($tag, $function_to_remove)) {
    remove_action($tag, $function_to_remove, 1);
}

// Old inline asset function - ensure it is removed or commented out.

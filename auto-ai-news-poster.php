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
    // Obținem URL-ul de bază al site-ului
    $site_url = site_url();
    // Obținem calea către directorul de plugin-uri
    $plugin_dir_path = plugin_dir_path(__FILE__);
    // Găsim calea relativă de la rădăcina site-ului la directorul de plugin-uri
    $relative_path = str_replace(ABSPATH, '', $plugin_dir_path);
    // Construim URL-ul de bază al plugin-ului
    $plugin_base_url = $site_url . '/' . $relative_path;

    // Pentru depanare
    error_log("--- AANP Enqueue Paths ---");
    error_log("Site URL: " . $site_url);
    error_log("Plugin Dir Path: " . $plugin_dir_path);
    error_log("ABSPATH: " . ABSPATH);
    error_log("Relative Path: " . $relative_path);
    error_log("Constructed Plugin Base URL: " . $plugin_base_url);
    
    $screen = get_current_screen();
    $current_screen_id = $screen ? $screen->id : 'no_screen';

    // --- Active pe pagina de setări ---
    if ($current_screen_id === 'posts_page_auto-ai-news-poster') {
        // Adăugăm fișierul CSS principal
        wp_enqueue_style(
            'auto-ai-news-poster-styles',
            $plugin_base_url . 'includes/css/auto-ai-news-poster.css',
            [],
            '1.0.2' // Versiune statică
        );

        // Adăugăm fișierul JavaScript specific setărilor
        wp_enqueue_script(
            'auto-ai-news-poster-settings-js',
            $plugin_base_url . 'includes/js/auto-ai-news-poster-settings.js',
            ['jquery'],
            '1.0.2', // Versiune statică
            true
        );
    }

    // --- Active pe pagina de editare a articolelor ---
    if ($screen && ($current_screen_id === 'post' || $screen->post_type === 'post')) {
        // Adăugăm fișierul JavaScript specific metabox-ului
        wp_enqueue_script(
            'auto-ai-news-poster-metabox-js',
            $plugin_base_url . 'includes/js/auto-ai-news-poster-metabox.js',
            ['jquery'],
            '1.0.2', // Versiune statică
            true
        );

        // Trimitem variabile PHP către scriptul metabox-ului
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

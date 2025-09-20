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
    // Pentru depanare: afișăm hook-ul pe fiecare pagină de administrare
    error_log('AANP Admin Enqueue Hook: ' . $hook);

    // Obținem ecranul curent pentru o verificare mai fiabilă
    $screen = get_current_screen();
    $current_screen_id = $screen ? $screen->id : 'no_screen';
    error_log("--- AANP Enqueue Check --- Hook: {$hook} | Screen ID: {$current_screen_id} ---");

    // --- Active pe pagina de setări ---
    if ($current_screen_id === 'posts_page_auto-ai-news-poster') {
        error_log('✅ AANP: Pagină de setări DETECTATĂ. Se adaugă în coadă resursele pentru setări.');

        // Adăugăm fișierul CSS principal
        wp_enqueue_style(
            'auto-ai-news-poster-styles',
            plugin_dir_url(__FILE__) . 'includes/css/auto-ai-news-poster.css',
            [],
            '1.0.1' // Versiune statică pentru depanare
        );

        // Adăugăm fișierul JavaScript specific setărilor
        wp_enqueue_script(
            'auto-ai-news-poster-settings-js',
            plugin_dir_url(__FILE__) . 'includes/js/auto-ai-news-poster-settings.js',
            ['jquery'],
            '1.0.1', // Versiune statică pentru depanare
            true
        );
    }

    // --- Active pe pagina de editare a articolelor ---
    if ($screen && ($current_screen_id === 'post' || $screen->post_type === 'post')) {
        error_log('✅ AANP: Pagină de editare articol DETECTATĂ. Se adaugă în coadă resursele pentru metabox.');

        // Adăugăm fișierul JavaScript specific metabox-ului
        wp_enqueue_script(
            'auto-ai-news-poster-metabox-js',
            plugin_dir_url(__FILE__) . 'includes/js/auto-ai-news-poster-metabox.js',
            ['jquery'],
            '1.0.1', // Versiune statică pentru depanare
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

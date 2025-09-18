<?php

/**
 * Plugin Name: Auto AI News Poster
 * Description: Un plugin care preia știri de pe minim trei surse, le analizează și publică un articol obiectiv în mod automat sau manual.
 * Version: 1.3
 * Author: Gabriel Sandu
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include fișierele necesare
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-metabox.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-hooks.php';

// Funcția pentru înregistrarea scripturilor și stilurilor
add_action('admin_enqueue_scripts', 'auto_ai_news_poster_enqueue_scripts');
function auto_ai_news_poster_enqueue_scripts($hook_suffix)
{
    // Verificăm dacă ne aflăm pe pagina de setări a pluginului sau pe pagina de editare a unui articol
    if ($hook_suffix === 'post.php' || $hook_suffix === 'post-new.php' || (isset($_GET['page']) && $_GET['page'] === 'auto-ai-news-poster')) {

        // Include CSS-ul modern pentru admin PRIMUL (pentru a avea prioritate)
        wp_enqueue_style(
            'auto-ai-news-poster-admin-css',
            plugin_dir_url(__FILE__) . 'includes/css/auto-ai-news-poster.css',
            [],
            '1.2',
            'all'
        );

        // Include Bootstrap CSS
        wp_enqueue_style(
            'bootstrap-css',
            'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css',
            [],
            '4.5.2',
            'all'
        );

        // Scriptul nostru JavaScript
        wp_enqueue_script(
            'auto-ai-news-poster-ajax',
            plugin_dir_url(__FILE__) . 'includes/js/auto-ai-news-poster.js',
            ['jquery'], // jQuery este deja inclus implicit în WordPress
            '1.2',
            true
        );

        // Localizare variabile pentru AJAX
        wp_localize_script('auto-ai-news-poster-ajax', 'autoAiNewsPosterAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('get_article_from_sources_nonce'),
        ]);
    }
}

// Înregistrăm fișierul CSS doar pentru paginile relevante
add_action('wp_enqueue_scripts', 'auto_ai_news_poster_enqueue_css');
function auto_ai_news_poster_enqueue_css()
{
    // Verificăm dacă ne aflăm pe o pagină de articol singular sau pe o pagină unde este utilizat pluginul
    if (is_singular('post') || is_admin()) {
        wp_enqueue_style(
            'auto-ai-news-poster-css',
            plugin_dir_url(__FILE__) . 'includes/css/auto-ai-news-poster.css',
            [],
            '1.0',
            'all'
        );
    }
}

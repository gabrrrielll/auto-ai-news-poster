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
 * Version: 1.7
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

// Fix pentru problema MIME type cu CSS-ul și JavaScript-ul

add_action('admin_enqueue_scripts', 'auto_ai_news_poster_enqueue_scripts');
function auto_ai_news_poster_enqueue_scripts($hook_suffix)
{
    $is_settings_page = ($hook_suffix === 'toplevel_page_auto-ai-news-poster');
    $is_post_edit_page = ($hook_suffix === 'post.php' || $hook_suffix === 'post-new.php');

    // Încărcăm stilurile doar pe paginile de setări și editare postare
    if ($is_settings_page || $is_post_edit_page) {
        wp_enqueue_style('auto-ai-news-poster-admin-style', plugin_dir_url(__FILE__) . 'includes/css/auto-ai-news-poster.css', [], '1.0.0');
        wp_enqueue_script('auto-ai-news-poster-admin-script', plugin_dir_url(__FILE__) . 'includes/js/auto-ai-news-poster-admin.js', ['jquery'], '1.0.0', true);

        // Localizăm scriptul pentru a pasa variabile PHP către JavaScript
        wp_localize_script(
            'auto-ai-news-poster-admin-script',
            'autoAiNewsPosterAjax',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'generate_image_nonce' => wp_create_nonce('generate_image_nonce'),
                'get_article_nonce' => wp_create_nonce('get_article_nonce'),
                'refresh_models_nonce' => wp_create_nonce('refresh_openai_models_nonce'),
            ]
        );
    }
}

// Eliminăm funcționalitatea veche de încărcare CSS/JS inline
// Această secțiune este acum goală, deoarece totul este gestionat prin wp_enqueue_script și wp_enqueue_style
// Funcția de activare a plugin-ului

<?php

/**
 * Plugin Name: Auto AI News Poster
 * Description: Un plugin care preia știri de pe minim trei surse, le analizează și publică un articol obiectiv în mod automat sau manual.
 * Version: 1.5
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
            '1.3',
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
            '1.3',
            true
        );

        // Localizare variabile pentru AJAX
        wp_localize_script('auto-ai-news-poster-ajax', 'autoAiNewsPosterAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('get_article_from_sources_nonce'),
        ]);
    }
}

// Fix pentru problema MIME type cu CSS-ul
add_action('wp_head', 'auto_ai_news_poster_fix_css_mime_type', 1);
function auto_ai_news_poster_fix_css_mime_type() {
    if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'auto-ai-news-poster') {
        echo '<style type="text/css">
        /* Auto AI News Poster - Fallback styles */
        .auto-ai-news-poster-admin {
            background: #f8fafc;
            min-height: 100vh;
            padding: 20px;
        }
        .auto-ai-news-poster-admin .wrap {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            overflow: hidden;
        }
        .auto-ai-news-poster-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .auto-ai-news-poster-header h1 {
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin: 0;
            font-size: 2.5rem;
        }
        .settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .settings-card-header {
            background: #f8fafc;
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
        }
        .settings-card-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        .settings-card-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        .settings-card-content {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .control-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-outline-primary {
            background: transparent;
            color: #2563eb;
            border: 1px solid #2563eb;
        }
        </style>';
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

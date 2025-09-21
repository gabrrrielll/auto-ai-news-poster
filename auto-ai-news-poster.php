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
// Implementarea inline pentru CSS și JS (metodă testată pe server)
add_action('admin_head', 'auto_ai_news_poster_inline_admin_css');
add_action('admin_footer', 'auto_ai_news_poster_inline_admin_js');

function auto_ai_news_poster_inline_admin_css()
{
    if (is_admin()) {
        $screen = get_current_screen();
        $current_screen_id = $screen ? $screen->id : 'no_screen';

        // Verificăm dacă suntem pe pagina de setări
        if ($current_screen_id !== 'posts_page_auto-ai-news-poster') {
            return; // Ieșim dacă nu suntem pe pagina corectă
        }

        $css_path = plugin_dir_path(__FILE__) . 'includes/css/auto-ai-news-poster.css';

        if (file_exists($css_path)) {
            echo '<style type="text/css">';
            echo file_get_contents($css_path);
            echo '</style>';
        }
    }
}

function auto_ai_news_poster_inline_admin_js()
{
    if (is_admin()) {
        $screen = get_current_screen();
        $current_screen_id = $screen ? $screen->id : 'no_screen';

        // Verificăm dacă suntem pe pagina de setări
        if ($current_screen_id !== 'posts_page_auto-ai-news-poster') {
            return; // Ieșim dacă nu suntem pe pagina corectă
        }

        $js_path = plugin_dir_path(__FILE__) . 'includes/js/auto-ai-news-poster-settings.js';

        if (file_exists($js_path)) {
            echo '<script type="text/javascript">';
            echo file_get_contents($js_path);
            echo '</script>';
        }
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

<?php

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
require_once plugin_dir_path(__FILE__) . 'includes/constants/config.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-metabox.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-hooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-scanner.php';

// Initialize Classest Enqueuing ---
// Implementarea inline pentru CSS și JS (metodă testată pe server)
add_action('admin_head', 'auto_ai_news_poster_inline_admin_css');
add_action('admin_footer', 'auto_ai_news_poster_inline_admin_js');
add_action('wp_head', 'auto_ai_news_poster_inline_frontend_css'); // CSS pe frontend pentru tooltip

function auto_ai_news_poster_inline_admin_css()
{
    if (is_admin()) {
        $screen = get_current_screen();
        $current_screen_id = $screen ? $screen->id : 'no_screen';

        // Verificăm dacă suntem pe pagina de setări sau pe pagina de editare articol
        // Verificăm dacă suntem pe pagina de setări sau pe pagina de editare articol
        $settings_page_id = 'toplevel_page_' . AUTO_AI_NEWS_POSTER_SETTINGS_PAGE;
        $allowed_screens = [$settings_page_id, 'post', 'post-new'];
        if (!in_array($current_screen_id, $allowed_screens)) {
            return; // Ieșim dacă nu suntem pe paginile corecte
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

        // Verificăm dacă suntem pe pagina de setări sau pe pagina de editare articol
        // Verificăm dacă suntem pe pagina de setări sau pe pagina de editare articol
        $settings_page_id = 'toplevel_page_' . AUTO_AI_NEWS_POSTER_SETTINGS_PAGE;
        $allowed_screens = [$settings_page_id, 'post', 'post-new'];
        if (!in_array($current_screen_id, $allowed_screens)) {
            return; // Ieșim dacă nu suntem pe paginile corecte
        }

        // Alegem fișierul JS potrivit în funcție de pagină
        if ($current_screen_id === $settings_page_id) {
            // Pagina de setări
            $js_path = plugin_dir_path(__FILE__) . 'includes/js/auto-ai-news-poster-settings.js';
        } else {
            // Pagina de editare articol
            $js_path = plugin_dir_path(__FILE__) . 'includes/js/auto-ai-news-poster-metabox.js';
        }

        if (file_exists($js_path)) {
            echo '<script type="text/javascript">';
            echo file_get_contents($js_path);
            echo '</script>';
        }
    }
}

function auto_ai_news_poster_inline_frontend_css()
{
    // Încărcăm CSS-ul pe frontend pentru tooltip-ul info icon
    $css_path = plugin_dir_path(__FILE__) . 'includes/css/auto-ai-news-poster.css';
    
    if (file_exists($css_path)) {
        echo '<style type="text/css">';
        echo file_get_contents($css_path);
        echo '</style>';
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

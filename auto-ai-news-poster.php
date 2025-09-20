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
// Schimbăm hook-ul la admin_head pentru a încerca o altă metodă de încărcare
add_action('admin_head', 'auto_ai_news_poster_load_assets_inline');

function auto_ai_news_poster_load_assets_inline()
{
    $screen = get_current_screen();
    $current_screen_id = $screen ? $screen->id : 'no_screen';

    // Verificăm dacă suntem pe pagina de setări
    if ($current_screen_id !== 'posts_page_auto-ai-news-poster') {
        return; // Ieșim dacă nu suntem pe pagina corectă
    }

    error_log('✅ AANP: Pagină de setări DETECTATĂ. Se încarcă resursele direct în <head>.');

    // Construim URL-urile manual pentru a fi siguri
    $site_url = site_url();
    $relative_path = str_replace(ABSPATH, '', plugin_dir_path(__FILE__));
    $plugin_base_url = $site_url . '/' . $relative_path;

    $css_url = $plugin_base_url . 'includes/css/auto-ai-news-poster.css?ver=' . time();
    $settings_js_url = $plugin_base_url . 'includes/js/auto-ai-news-poster-settings.js?ver=' . time();

    // Încărcăm CSS și JS direct în head
    echo '<link rel="stylesheet" id="auto-ai-news-poster-styles-css" href="' . esc_url($css_url) . '" type="text/css" media="all" />';
    echo '<script src="' . esc_url($settings_js_url) . '" id="auto-ai-news-poster-settings-js-js"></script>';
}

// Remove the old, problematic inline asset loading method
// This is critical. We ensure the old function is not being called.
$tag = 'admin_head';
$function_to_remove = 'auto_ai_news_poster_fix_css_mime_type';
if (has_action($tag, $function_to_remove)) {
    remove_action($tag, $function_to_remove, 1);
}

// Old inline asset function - ensure it is removed or commented out.

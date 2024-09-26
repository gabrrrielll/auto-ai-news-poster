<?php
/**
 * Plugin Name: Auto AI News Poster
 * Description: Un plugin care preia știri de pe minim trei surse, le analizează și publică un articol obiectiv în mod automat sau manual.
 * Version: 1.0
 * Author: Gabriel Sandu
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include files
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-parser.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-metabox.php';


// Include CSS-ul Bootstrap doar pentru paginile pluginului
add_action('admin_enqueue_scripts', 'auto_ai_news_poster_admin_styles');
function auto_ai_news_poster_admin_styles($hook_suffix)
{
    // Verifică dacă ne aflăm în pagina setărilor acestui plugin
    if ($hook_suffix == 'edit.php' || strpos($hook_suffix, 'auto-ai-news-poster') !== false) {
        wp_enqueue_style('auto-ai-news-poster-bootstrap', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    }
}

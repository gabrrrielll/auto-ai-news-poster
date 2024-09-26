<?php
/**
 * Plugin Name: Auto AI News Poster
 * Description: Un plugin care preia știri de pe minim trei surse, le analizează și publică un articol obiectiv în mod automat sau manual.
 * Version: 1.0
 * Author: Gabriel Sandu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-auto-ai-news-poster-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-auto-ai-news-poster-parser.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-auto-ai-news-poster-cron.php';

// Activare cron la activarea pluginului
register_activation_hook(__FILE__, 'auto_ai_news_poster_activate');
function auto_ai_news_poster_activate() {
    if (! wp_next_scheduled ( 'auto_ai_news_poster_cron_hook' )) {
        wp_schedule_event(time(), 'hourly', 'auto_ai_news_poster_cron_hook');
    }
}

// Dezactivare cron la dezactivarea pluginului
register_deactivation_hook(__FILE__, 'auto_ai_news_poster_deactivate');
function auto_ai_news_poster_deactivate() {
    wp_clear_scheduled_hook('auto_ai_news_poster_cron_hook');
}

// Functia care rulează cron-ul
add_action('auto_ai_news_poster_cron_hook', 'auto_ai_news_poster_auto_post');
function auto_ai_news_poster_auto_post() {
    $settings = get_option('auto_ai_news_poster_settings');
    if ($settings['mode'] === 'auto') {
        Auto_Ai_News_Poster_Parser::generate_and_publish_article();
    }
}

// Adăugare panou în zona articolelor din admin
add_action('admin_menu', 'auto_ai_news_poster_menu');
function auto_ai_news_poster_menu() {
    add_submenu_page(
        'edit.php',
        'Auto AI News Poster Settings',
        'Auto AI News Poster',
        'manage_options',
        'auto-ai-news-poster',
        'auto_ai_news_poster_settings_page'
    );
}

function auto_ai_news_poster_settings_page() {
    Auto_Ai_News_Poster_Settings::display_settings_page();
}

// Add Bootstrap Library styles
add_action('admin_enqueue_scripts', 'auto_ai_news_poster_admin_styles');
function auto_ai_news_poster_admin_styles($hook_suffix) {
    if ($hook_suffix == 'edit.php' || strpos($hook_suffix, 'auto-ai-news-poster') !== false) {
        wp_enqueue_style('auto-ai-news-poster-bootstrap', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    }
}


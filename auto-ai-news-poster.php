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

// Include fișierele necesare
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-metabox.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-api.php'; // Include noul fișier pentru apeluri API

// Funcția pentru înregistrarea scripturilor și stilurilor
add_action('admin_enqueue_scripts', 'auto_ai_news_poster_enqueue_scripts');
function auto_ai_news_poster_enqueue_scripts($hook_suffix)
{
    // Scriptul nostru JavaScript
    wp_enqueue_script(
        'auto-ai-news-poster-ajax',
        plugin_dir_url(__FILE__) . 'includes/js/auto-ai-news-poster.js',
        ['jquery'], // jQuery este deja inclus implicit în WordPress
        null,
        true
    );

    // Localizare variabile pentru AJAX
    wp_localize_script('auto-ai-news-poster-ajax', 'autoAiNewsPosterAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('get_article_from_sources_nonce'),
    ]);
}

function display_external_image_source($content) {
    global $post;

    // Preluăm sursa imaginii din meta
    $external_image_source = get_post_meta($post->ID, '_external_image_source', true);

    // Dacă există o sursă a imaginii, o adăugăm la începutul conținutului
    if (!empty($external_image_source)) {
        $source_html = '<p><em>Sursa foto: ' . esc_html($external_image_source) . '</em></p>';
        // Adăugăm sursa înaintea conținutului articolului
        $content = $source_html . $content;
    }

    return $content;
}
add_filter('the_content', 'display_external_image_source');


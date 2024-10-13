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

function display_external_image($content) {
    global $post;

    // Preluăm opțiunea din setările pluginului
    $options = get_option('auto_ai_news_poster_settings');
    $use_external_images = isset($options['use_external_images']) ? $options['use_external_images'] : 'external';

    // Preluăm URL-ul imaginii externe și sursa
    $external_image_url = get_post_meta($post->ID, '_external_image_url', true);
    $external_image_source = get_post_meta($post->ID, '_external_image_source', true);

    // Dacă folosim imagini externe și există un URL extern
    if ($use_external_images === 'external' && !empty($external_image_url)) {
        $image_html = '<div class="external-image">';
        $image_html .= '<img src="' . esc_url($external_image_url) . '" alt="" />';

        // Adăugăm și sursa imaginii dacă există
        if (!empty($external_image_source)) {
            $image_html .= '<p><em>Sursa foto: ' . esc_html($external_image_source) . '</em></p>';
        }
        $image_html .= '</div>';

        // Adăugăm imaginea externă înainte de conținut
        $content = $image_html . $content;
    }
    // Dacă folosim importul de imagini, afișăm imaginea reprezentativă din WordPress
    else {
        if (has_post_thumbnail($post->ID)) {
            $content = get_the_post_thumbnail($post->ID, 'full') . $content;
        }
    }

    return $content;
}
// Aplicăm filtrul pentru a adăuga imaginea externă sau importată în funcție de setare
add_filter('the_content', 'display_external_image');




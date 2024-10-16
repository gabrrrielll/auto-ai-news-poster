<?php

class Auto_Ai_News_Poster_Hooks
{

    public static function init()
    {
        // Adăugăm toate hook-urile și filtrele necesare aici
        add_filter('admin_post_thumbnail_html', [self::class, 'display_external_featured_image_in_metabox'], 10, 2);

        // Aplicăm filtrul pentru a adăuga imaginea externă sau importată în funcție de setare
        add_filter('the_content', [self::class, 'display_external_image'], 10, 2);

    }

    // Funcție pentru a afișa imaginea externă în metabox-ul de imagine reprezentativă
    function display_external_featured_image_in_metabox($content, $post_id)
    {
        // Verificăm dacă există o imagine reprezentativă externă
        $external_image_url = get_post_meta($post_id, '_external_image_url', true);

        if ($external_image_url) {
            // Construim HTML-ul pentru a afișa imaginea externă
            $image_html = '<div style="margin-top: 10px;">';
            $image_html .= '<img src="' . esc_url($external_image_url) . '" alt="" style="max-width:100%; height:auto;">';
            $image_html .= '</div>';
            $image_html .= '<p style="margin-top:10px;"><strong>Imagine reprezentativă externă:</strong></p>';

            // Adăugăm HTML-ul în conținutul original al metabox-ului
            return $content . $image_html;
        }

        // Dacă nu există imagine externă, returnăm conținutul original
        return $content;
    }

    function display_external_image($content)
    {
        global $post;

        // Preluăm opțiunea din setările pluginului
        $options = get_option('auto_ai_news_poster_settings');
        $use_external_images = $options['use_external_images'] ?? 'external';

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
        } // Dacă folosim importul de imagini, afișăm imaginea reprezentativă din WordPress
        else {
            if (has_post_thumbnail($post->ID)) {
//                $content = get_the_post_thumbnail($post->ID, 'full') . $content;
                $content = '<p><em>Sursa foto: ' . esc_html($external_image_source) . '</em></p>' . $content;
            }
        }

        return $content;
    }

}

Auto_Ai_News_Poster_Hooks::init();

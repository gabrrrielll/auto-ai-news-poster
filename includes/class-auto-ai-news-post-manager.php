<?php

class Post_Manager
{
    public static function insert_or_update_post($post_id, $post_data)
    {
        error_log('insert_or_update_post for post ID: ' . $post_id);
        if (!get_post($post_id)) {
            // Inserăm articolul nou și obținem ID-ul acestuia
            $post_id = wp_insert_post($post_data);

            // Verificăm dacă inserarea a fost cu succes
            if (is_wp_error($post_id)) {
                return ['error' => 'Nu am reușit să creez un articol nou.'];
            }
        } else {
            // Actualizăm articolul existent în baza de date
            wp_update_post($post_data);
        }

        return $post_id;
    }

    public static function set_post_tags($post_id, $tags)
    {
        wp_set_post_tags($post_id, $tags);
    }

    public static function set_post_categories($post_id, $category)
    {
        $category_id = get_cat_ID($category); // Obține ID-ul categoriei
        if ($category_id) {
            wp_set_post_categories($post_id, [$category_id]); // Actualizează categoriile articolului
        } else {
            error_log('Numele categoriei nu se potriveste cu baza de date: ' . $category);
        }
    }

    public static function set_featured_image($post_id, $image_url)
    {
        $options = get_option('auto_ai_news_poster_settings');
        $image_handling_mode = $options['image_handling_mode'] ?? 'import';

        if ($image_handling_mode === 'import') {
            // Importăm imaginea în biblioteca media
            $image_id = media_sideload_image($image_url, $post_id, null, 'id');
            if (!is_wp_error($image_id)) {
                set_post_thumbnail($post_id, $image_id);
            } else {
                return ['error' => 'Nu am reușit să descarc imaginea.'];
            }
        } else {
            // Setăm imaginea reprezentativă externă folosind URL-ul direct
            update_post_meta($post_id, '_external_image_url', esc_url_raw($image_url));
        }
    }

}

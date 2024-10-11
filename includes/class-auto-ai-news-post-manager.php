<?php

class Post_Manager {

    public static function insert_or_update_post($post_id, $title, $content, $summary) {

        $options = get_option('auto_ai_news_poster_settings');
        $current_user = wp_get_current_user(); // Preluăm numele utilizatorului curent (admin)
        $author_name = $options['author_name'] ?? $current_user->display_name;
        // Dacă post_id este gol, creăm un articol nou în modul draft
        if (empty($post_id)) {
            $new_post = array(
                'post_title'    => $title,
                'post_content'  => $content,
                'post_status'   => 'draft',
                'post_type'     => 'post',
                'post_excerpt'  => $summary,
                'post_author'   => $author_name,
                'post_category' => $options['default_category'] ?? [],
            );

            // Inserăm articolul nou și obținem ID-ul acestuia
            $post_id = wp_insert_post($new_post);

            // Verificăm dacă inserarea a fost cu succes
            if (is_wp_error($post_id)) {
                return ['error' => 'Nu am reușit să creez un articol nou.'];
            }
        } else {
            // Actualizăm articolul existent în baza de date
            wp_update_post([
                'ID' => $post_id,
                'post_title' => $title,
                'post_content' => $content,
                'post_excerpt' => $summary, // Rezumatul este salvat în câmpul post_excerpt
                'post_author'   => $author_name,
                'post_category' => $options['default_category'] ?? [],
            ]);
        }

        return $post_id;
    }

    public static function set_post_tags($post_id, $tags) {
        // Setăm etichetele
        wp_set_post_tags($post_id, $tags);
    }

    public static function set_featured_image($post_id, $image_url) {
        // Descărcăm imaginea din Freepik ca imagine reprezentativă
        $image_id = media_sideload_image($image_url, $post_id, null, 'id');

        if (!is_wp_error($image_id)) {
            // Setăm imaginea ca imagine reprezentativă
            set_post_thumbnail($post_id, $image_id);
        } else {
            return ['error' => 'Nu am reușit să descarc imaginea.'];
        }
    }
}

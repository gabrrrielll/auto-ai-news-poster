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
            // Asigurăm că 'ID' este setat în $post_data pentru actualizare
            $post_data['ID'] = $post_id;

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



    public static function set_featured_image($post_id, $image_url, $title = '', $summary = '')
    {
        $options = get_option('auto_ai_news_poster_settings');
        $image_handling_mode = $options['use_external_images'] ?? 'import';
        $title_slug = sanitize_title($title); // Transformăm titlul într-un slug URL-friendly
        $author_id = $options['author_name'] ?? get_current_user_id();
        error_log('set_featured_image for post ID: ' . $post_id . ', ' . $image_url . ', ' . $image_handling_mode);

        // Verificăm și includem fișierul necesar pentru media_sideload_image
        if (!function_exists('media_sideload_image')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        if ($image_handling_mode == 'import') {
            // Descarcă imaginea și redenumește-o
            $temp_file = download_url($image_url);
            if (is_wp_error($temp_file)) {
                error_log('Eroare la descărcarea imaginii: ' . $temp_file->get_error_message());
                return ['error' => 'Nu am reușit să descarc imaginea.'];
            }


            // Căutăm extensia în URL (în calea URL-ului sau în tipul MIME din parametrii URL-ului)
            if (preg_match('/\.(jpg|jpeg|png|gif|bmp)(\?|$)/i', $image_url, $matches)) {
                $file_extension = $matches[1];
            } elseif (strpos($image_url, 'rsct=image/png') !== false) {
                $file_extension = 'png';
            } elseif (strpos($image_url, 'rsct=image/jpeg') !== false) {
                $file_extension = 'jpg';
            } elseif (strpos($image_url, 'rsct=image/gif') !== false) {
                $file_extension = 'gif';
            } else {
                $file_extension = 'png'; // Implicit, dacă nu e detectată extensia
            }

            $new_file_name = $title_slug . '.' . $file_extension;


            // Mutăm fișierul temporar în locația finală
            $file_array = [
                'name' => $new_file_name,
                'tmp_name' => $temp_file,
            ];

            error_log('Încărcăm fișierul în biblioteca media: ' . $post_id . '   $file_extension = ' . $file_extension . '   $new_file_name:'.  $new_file_name);

            // Încărcăm fișierul în biblioteca media
            $image_id = media_handle_sideload($file_array, $post_id, $summary);

            // Verificăm dacă încărcarea a fost cu succes
            if (is_wp_error($image_id)) {
                @unlink($temp_file); // Ștergem fișierul temporar în caz de eroare
                error_log('Eroare la încărcarea imaginii: ' . $image_id->get_error_message());
                return ['error' => 'Nu am reușit să salvez imaginea.'];
            }

            // Setăm imaginea reprezentativă și actualizăm atributele alt și description
            // set_post_thumbnail($post_id, $image_id);
            // update_post_meta($image_id, '_wp_attachment_image_alt', $title); // Setăm "alt" cu titlul articolului
            // wp_update_post([
            //     'ID' => $image_id,
            //     'post_excerpt' => $summary // Setăm "description" cu rezumatul articolului
            // ]);

            // Setăm imaginea reprezentativă și actualizăm atributele alt și description
            set_post_thumbnail($post_id, $image_id);

            // Actualizăm atributele imaginii: titlu, "alt" și "description"
            wp_update_post([
                'ID' => $image_id,
                'post_author' => $author_id,  // Setăm autorul imaginii
                'post_title' => $title, // Setăm titlul imaginii cu titlul articolului
                'post_content' => $summary, // Nu adăugăm nimic în "Text asociat"
                // 'post_excerpt' => $summary, // Nu utilizam Text asociat deoarece apare sub imaginea reprezentativa si nu
            ]);

            update_post_meta($image_id, '_wp_attachment_image_alt', $title); // Setăm atributul "alt" cu titlul articolului


        } else {
            // Setăm imaginea reprezentativă externă folosind URL-ul direct
            update_post_meta($post_id, '_external_image_url', esc_url_raw($image_url));
            update_post_meta($post_id, '_external_image_alt', $title); // Atribuim "alt" extern cu titlul articolului
            update_post_meta($post_id, '_external_image_description', $summary); // Atribuim "description" extern cu rezumatul

            error_log('Setăm imaginea reprezentativă externă folosind URL-ul direct. ' . $post_id . ', ' . $image_url);
        }
    }




}

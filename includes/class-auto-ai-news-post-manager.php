<?php

class Post_Manager
{
    public static function insert_or_update_post($post_id, $post_data)
    {
        if (!get_post($post_id)) {
            // Inserăm articolul nou și obținem ID-ul acestuia
            $post_id = wp_insert_post($post_data);

            // Verificăm dacă inserarea a fost cu succes
            if (is_wp_error($post_id)) {
                return new WP_Error('post_insert_failed', 'Nu am reușit să creez un articol nou: ' . $post_id->get_error_message());
            }

            // Verificăm dacă ID-ul este valid (nu 0)
            if (empty($post_id) || $post_id === 0) {
                return new WP_Error('post_insert_failed', 'wp_insert_post returned invalid ID: ' . $post_id);
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
        $options = get_option('auto_ai_news_poster_settings', []);
        $generate_tags_option = $options['generate_tags'] ?? 'yes';

        if ($generate_tags_option === 'yes' && !empty($tags)) {
            // Validăm numărul de etichete: minim 1, maxim 3
            if (is_array($tags)) {
                $tags = array_filter($tags, 'trim'); // Eliminăm etichetele goale
                $tags = array_slice($tags, 0, 3); // Limităm la maximum 3 etichete

                if (empty($tags)) {
                    return;
                }

                wp_set_post_tags($post_id, $tags);
            } else {
                error_log('🚫 Tags is not an array for post ID: ' . $post_id . ', Type: ' . gettype($tags));
            }
        } else {
            error_log('🚫 Tags generation is disabled or tags are empty for post ID: ' . $post_id);
        }
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



    public static function set_featured_image($post_id, $image_url, $title = '', $summary = '', $source_url = '', $use_external_images_override = null)
    {
        $options = get_option('auto_ai_news_poster_settings');
        // Folosim override dacă este furnizat, altfel folosim setarea globală
        $image_handling_mode = $use_external_images_override ?? ($options['use_external_images'] ?? 'import');
        $title_slug = sanitize_title($title); // Transformăm titlul într-un slug URL-friendly
        $author_id = $options['author_name'] ?? get_current_user_id();
        
        // Extragem numele site-ului din URL-ul sursă pentru "Sursa foto"
        $site_name = 'Sursă externă'; // Default
        $url_to_extract_from = !empty($source_url) ? $source_url : $image_url;

        if (!empty($url_to_extract_from) && class_exists('Auto_AI_News_Poster_Image_Extractor')) {
            $site_name = Auto_AI_News_Poster_Image_Extractor::get_site_name_from_url($url_to_extract_from);
        }

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
                return new WP_Error('image_download_failed', 'Nu am reușit să descarc imaginea: ' . $temp_file->get_error_message());
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

            // Încărcăm fișierul în biblioteca media
            $image_id = media_handle_sideload($file_array, $post_id, $summary);

            // Verificăm dacă încărcarea a fost cu succes
            if (is_wp_error($image_id)) {
                @unlink($temp_file); // Ștergem fișierul temporar în caz de eroare
                return new WP_Error('image_upload_failed', 'Nu am reușit să salvez imaginea: ' . $image_id->get_error_message());
            }

            // Setăm imaginea reprezentativă și actualizăm atributele alt și description
            set_post_thumbnail($post_id, $image_id);

            // Actualizăm atributele imaginii: titlu, "alt" și "description"
            wp_update_post([
                'ID' => $image_id,
                'post_author' => $author_id,  // Setăm autorul imaginii
                'post_title' => $title, // Setăm titlul imaginii cu titlul articolului
                'post_content' => $summary, // Nu adăugăm nimic în "Text asociat"
            ]);

            update_post_meta($image_id, '_wp_attachment_image_alt', $title); // Setăm atributul "alt" cu titlul articolului

            // Curățăm metadatele externe, deoarece imaginea a fost importată
            delete_post_meta($post_id, '_external_image_url');
            update_post_meta($post_id, '_external_image_source', $site_name);


        } else {
            // Setăm imaginea reprezentativă externă folosind URL-ul direct
            update_post_meta($post_id, '_external_image_url', esc_url_raw($image_url));
            update_post_meta($post_id, '_external_image_alt', $title); // Atribuim "alt" extern cu titlul articolului
            update_post_meta($post_id, '_external_image_description', $summary); // Atribuim "description" extern cu rezumatul
            update_post_meta($post_id, '_external_image_source', $site_name); // Setăm numele site-ului ca sursă

        }
    }

    /**
     * Rewrite an existing article with new content from AI
     * 
     * @param int $post_id ID of post to rewrite
     * @param array $article_data Array with 'title', 'content', 'summary', 'tags'
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function rewrite_article($post_id, $article_data)
    {
        // Validate post exists
        if (!get_post($post_id)) {
            return new WP_Error('post_not_found', 'Post ID not found: ' . $post_id);
        }

        // Validate required fields
        if (empty($article_data['title']) || empty($article_data['content'])) {
            return new WP_Error('invalid_data', 'Title and content are required');
        }

        // Update post
        $updated_post = [
            'ID' => $post_id,
            'post_title' => $article_data['title'],
            'post_content' => wp_kses_post($article_data['content']),
            'post_excerpt' => isset($article_data['summary']) ? wp_kses_post($article_data['summary']) : ''
        ];

        $result = wp_update_post($updated_post, true);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update tags if provided
        if (!empty($article_data['tags']) && is_array($article_data['tags'])) {
            self::set_post_tags($post_id, $article_data['tags']);
        }

        // Add metadata to track rewrite
        update_post_meta($post_id, '_last_rewrite_date', current_time('mysql'));
        
        return true;
    }



}

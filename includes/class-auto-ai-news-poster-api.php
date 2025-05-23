<?php
require_once 'constants/config.php';
require_once 'class-auto-ai-news-post-manager.php';

class Auto_Ai_News_Poster_Api
{

    public static function init()
    {
        // Înregistrăm funcția AJAX pentru apelul API
        add_action('wp_ajax_get_article_from_sources', [self::class, 'get_article_from_sources']);
        add_action('auto_ai_news_poster_cron', [self::class, 'auto_generate_article']); // Cron job action
    }

    public static function get_article_from_sources()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $publication_mode = $options['mode']; // Verificăm dacă este 'manual' sau 'auto'

        if ($publication_mode === 'manual') {
            check_ajax_referer('get_article_from_sources_nonce', 'security');
        }

        return self::process_article_generation();
    }

    public static function process_article_generation()
    {
        // Preluăm datele
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;

        $additional_instructions = sanitize_text_field($_POST['instructions'] ?? '');
        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key'];
        $sources = explode("\n", trim($options['news_sources'])); // Sursele din setări

        if (empty($api_key) || empty($sources)) {
            wp_send_json_error(['message' => 'Cheia API sau sursele lipsesc']);
        }

        // Generăm promptul din config.php
        $prompt = generate_prompt($sources, $additional_instructions, []);
        error_log('get_article_from_sources() triggered for post ID: ' . $post_id . ' prompt: ' . $prompt);
        // Apelăm OpenAI API din config.php
        $response = call_openai_api($api_key, $prompt);

        if (is_wp_error($response)) {
            error_log('Eroare API: ' . $response->get_error_message());
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body, true);

        if (isset($body['choices'][0]['message']['content'])) {
            $content_json = json_decode($body['choices'][0]['message']['content'], true);

            if ($content_json) {
                // Extragem datele din răspunsul structurat
                $title = $content_json['title'] ?? '';
                $content = wp_kses_post($content_json['content'] ?? '');
                $summary = wp_kses_post($content_json['summary'] ?? '');
                $category = $content_json['category'] ?? '';
                $tags = $content_json['tags'] ?? [];
                $images = $content_json['images'] ?? [];

                // Construim array-ul de post_data
                $post_data = array(
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'draft',
                    'post_type' => 'post',
                    'post_excerpt' => $summary,
                );

                // Folosim Post_Manager pentru a crea sau actualiza articolul
                $post_id = Post_Manager::insert_or_update_post($post_id, $post_data);

                if (isset($post_id['error'])) {
                    wp_send_json_error(['message' => $post_id['error']]);
                }

                // Setăm etichetele articolului
                Post_Manager::set_post_tags($post_id, $tags);

                // Setăm categoriile articolului
                Post_Manager::set_post_categories($post_id, $category);

                // Setăm imaginea reprezentativă dacă este necesar
                if (!empty($images)) {
                    Post_Manager::set_featured_image($post_id, $images[0]);
                }

                // Returnăm succes și facem refresh la pagina
                wp_send_json_success([
                    'post_id' => $post_id,
                    'title' => $title,
                    'tags' => $tags,
                    'category' => $category,
                    'images' => $images,
                    'summary' => $summary,
                    'article_content' => $content,
                    'body' => $body
                ]);
            } else {
                wp_send_json_error(['message' => 'Datele primite nu sunt în format JSON structurat.']);
            }
        } else {
            wp_send_json_error(['message' => 'A apărut o eroare la generarea articolului.']);
        }
    }


    public static function auto_generate_article()
    {
        // Folosit pentru apelurile cron (automate)
        self::process_article_generation();
    }
}

Auto_Ai_News_Poster_Api::init();

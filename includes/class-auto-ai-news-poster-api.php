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
        add_action('wp_ajax_generate_image_for_article', [self::class, 'generate_image_for_article']);
        add_action('wp_ajax_check_settings_changes', [self::class, 'check_settings_changes']);
        add_action('wp_ajax_force_refresh_test', [self::class, 'force_refresh_test']);
        add_action('wp_ajax_clear_transient', [self::class, 'clear_transient']);
        add_action('wp_ajax_force_refresh_now', [self::class, 'force_refresh_now']);

    }


    public static function get_article_from_sources()
    {
        error_log('🚀 AUTO AI NEWS POSTER - get_article_from_sources() STARTED');
        error_log('📥 Received POST data: ' . print_r($_POST, true));

        $options = get_option('auto_ai_news_poster_settings');
        $publication_mode = $options['mode']; // Verificăm dacă este 'manual' sau 'auto'

        error_log('⚙️ Plugin options loaded:');
        error_log('   - Publication mode: ' . $publication_mode);
        error_log('   - API key exists: ' . (!empty($options['chatgpt_api_key']) ? 'YES' : 'NO'));
        error_log('   - News sources count: ' . (isset($options['news_sources']) ? substr_count($options['news_sources'], "\n") + 1 : 0));

        if ($publication_mode === 'manual') {
            error_log('🔐 Manual mode - checking nonce...');
            try {
                check_ajax_referer('get_article_from_sources_nonce', 'security');
                error_log('✅ Nonce verification successful');
            } catch (Exception $e) {
                error_log('❌ Nonce verification failed: ' . $e->getMessage());
                wp_send_json_error(['message' => 'Nonce verification failed']);
                return;
            }
        } else {
            error_log('🤖 Auto mode - skipping nonce check');
        }

        error_log('🔄 Calling process_article_generation()...');
        return self::process_article_generation();
    }


    // Funcție pentru a obține categoria următoare
    public static function get_next_category()
    {

        // Obținem opțiunile salvate
        $options = get_option('auto_ai_news_poster_settings');

        // Verificăm dacă rularea automată a categoriilor este activată și modul este automat
        if ($options['auto_rotate_categories'] === 'yes' && $options['mode'] === 'auto') {
            $categories = get_categories(['orderby' => 'name', 'order' => 'ASC', 'hide_empty' => false]);
            $category_ids = wp_list_pluck($categories, 'term_id'); // Obținem ID-urile categoriilor

            // Obținem indexul ultimei categorii utilizate
            $current_index = get_option('auto_ai_news_poster_current_category_index', 0);

            // Calculăm următoarea categorie
            $next_category_id = $category_ids[$current_index];

            // Actualizăm indexul pentru următoarea utilizare
            $current_index = ($current_index + 1) % count($category_ids); // Resetăm la 0 când ajungem la finalul listei
            update_option('auto_ai_news_poster_current_category_index', $current_index);

            return get_category($next_category_id)->name; // Returnăm numele categoriei
        }

        // Dacă rularea automată a categoriilor nu este activată, folosim categoria selectată manual
        return $options['categories'][0] ?? ''; // Folosim prima categorie din listă dacă este setată
    }


    public static function getLastCategoryTitles($selected_category_name = null, $titlesNumber = 3)
    {
        $titles = [];
        error_log('CALL getLastCategoryTitles -> $selected_category_name: ' . $selected_category_name . ' $titlesNumber: ' . $titlesNumber);
        if ($selected_category_name === null) {
            // Obține toate categoriile
            $categories = get_categories(['hide_empty' => false]);

            if (empty($categories)) {
                error_log('Nu există categorii disponibile.');
                return;
            }

            // Calculează numărul total de titluri
            $total_titles_count = count($categories) * intval($titlesNumber);

            // Obține articolele din toate categoriile
            $query_args = [
                'posts_per_page' => $total_titles_count,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids' // Reduce cantitatea de date extrasă
            ];

            $query = new WP_Query($query_args);

            if ($query->have_posts()) {
                foreach ($query->posts as $post_id) {
                    $titles[] = get_the_title($post_id);
                }
            } else {
                error_log('Nu există articole în categorii.');
                return ;
            }
        } else {
            // Obține ID-ul categoriei pe baza numelui
            $category = get_category_by_slug(sanitize_title($selected_category_name));

            if (!$category) {
                error_log('Categoria nu există.');
                return;
            }

            $category_id = $category->term_id;

            // Obține ultimele articole din această categorie
            $query_args = [
                'cat' => $category_id,
                'posts_per_page' => intval($titlesNumber),
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids' // Reduce cantitatea de date extrasă
            ];

            $query = new WP_Query($query_args);

            if ($query->have_posts()) {
                foreach ($query->posts as $post_id) {
                    $titles[] = get_the_title($post_id);
                }
            } else {
                error_log("Nu există articole în această categorie ->  $category_id" .  $category_id);
                return ;
            }
        }

        // Concatenăm titlurile într-un singur string, separate prin punct și spațiu
        $titles_string = implode(', ', $titles);

        return $titles_string;
    }


    /**
     * Main handler for generating an article. Can be called via AJAX or internally (e.g., cron).
     */
    public static function process_article_generation() {
        $is_ajax_call = wp_doing_ajax();
        error_log('🚀 PROCESS_ARTICLE_GENERATION() STARTED. AJAX Call: ' . ($is_ajax_call ? 'Yes' : 'No'));

        // Load settings
        $options = get_option('auto_ai_news_poster_settings');
        if (empty($options['chatgpt_api_key'])) {
            $error_msg = 'Error: ChatGPT API Key is not set.';
            error_log($error_msg);
            if ($is_ajax_call) wp_send_json_error(['message' => $error_msg]);
            return;
        }

        $source_link = '';
        $extracted_content = '';
        $is_bulk_processing = false;
        $additional_instructions = '';

        // --- Determine the source of the article content ---
        if ($is_ajax_call) {
            // Manual generation from the post edit screen
            check_ajax_referer('get_article_from_sources_nonce', 'security');
            $source_link = isset($_POST['custom_source_url']) ? esc_url_raw($_POST['custom_source_url']) : '';
            $additional_instructions = isset($_POST['instructions']) ? sanitize_text_field($_POST['instructions']) : '';

            if (empty($source_link)) {
                wp_send_json_error(['message' => 'Please provide a source URL.']);
                return;
            }
            $extracted_content = self::extract_article_content_from_url($source_link);

        } else {
            // Automatic generation from the bulk list (CRON job)
            $is_bulk_processing = true;
            error_log('🤖 CRON JOB: Starting bulk processing run.');
            $bulk_links_str = $options['bulk_custom_source_urls'] ?? '';
            $bulk_links = array_filter(explode("\n", trim($bulk_links_str)), 'trim');

            if (empty($bulk_links)) {
                error_log('🤖 CRON JOB: Bulk list is empty. Nothing to process.');
                if (isset($options['run_until_bulk_exhausted']) && $options['run_until_bulk_exhausted']) {
                    self::force_mode_change_to_manual();
                }
                return;
            }

            // Take the first link from the list
            $source_link = array_shift($bulk_links);
            error_log('🤖 CRON JOB: Picked link from bulk list: ' . $source_link);

            // Immediately update the option with the shortened list to prevent race conditions
            $options['bulk_custom_source_urls'] = implode("\n", $bulk_links);
            update_option('auto_ai_news_poster_settings', $options);
            set_transient('auto_ai_news_poster_force_refresh', 'yes', MINUTE_IN_SECONDS); // Signal frontend to refresh
            error_log('🤖 CRON JOB: Removed link from list and updated options. Remaining links: ' . count($bulk_links));
            
            $extracted_content = self::extract_article_content_from_url($source_link);
        }

        // --- Validate extracted content ---
        if (is_wp_error($extracted_content) || empty(trim($extracted_content))) {
            $error_message = is_wp_error($extracted_content) ? $extracted_content->get_error_message() : 'Extracted content is empty.';
            error_log('❌ Content Extraction Failed for ' . $source_link . ': ' . $error_message);

            if ($is_bulk_processing) {
                self::re_add_link_to_bulk($source_link, 'Failed to extract content');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => "Failed to extract content from URL. Please check the link and try again. Error: " . $error_message]);
            }
            return;
        }
        error_log('✅ Successfully extracted content. Size: ' . strlen($extracted_content) . ' chars.');
        
        // --- Prevent duplicate posts ---
        $existing_posts = get_posts([
            'meta_key' => '_custom_source_url',
            'meta_value' => $source_link,
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'future'],
            'numberposts' => 1
        ]);

        if (!empty($existing_posts)) {
            error_log('⚠️ Link already used to generate post ID ' . $existing_posts[0]->ID . ': ' . $source_link . '. Skipping.');
             if ($is_ajax_call) {
                wp_send_json_error(['message' => 'This link has already been used to generate an article.']);
            }
            return;
        }


        // --- Call OpenAI API ---
        $prompt = generate_custom_source_prompt($extracted_content, $additional_instructions);
        $response = call_openai_api($options['chatgpt_api_key'], $prompt);

        if (is_wp_error($response)) {
            $error_message = 'OpenAI API Error: ' . $response->get_error_message();
            error_log('❌ ' . $error_message);
            if ($is_bulk_processing) self::re_add_link_to_bulk($source_link, 'OpenAI API Error');
            if ($is_ajax_call) wp_send_json_error(['message' => $error_message]);
            return;
        }

        // --- Process API Response ---
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);
        $ai_content_json = $decoded_response['choices'][0]['message']['content'] ?? null;

        if (empty($ai_content_json)) {
            $error_message = '❌ AI response is empty or in an unexpected format.';
            error_log($error_message);
            error_log('Full API Response: ' . print_r($decoded_response, true));
            if ($is_bulk_processing) self::re_add_link_to_bulk($source_link, 'Empty AI Response');
            if ($is_ajax_call) wp_send_json_error(['message' => $error_message, 'response' => $decoded_response]);
            return;
        }

        $article_data = json_decode($ai_content_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = '❌ Failed to decode article data JSON from AI response. Error: ' . json_last_error_msg();
            error_log($error_message);
            error_log('AI content string was: ' . $ai_content_json);
            if ($is_bulk_processing) self::re_add_link_to_bulk($source_link, 'JSON Decode Error');
            if ($is_ajax_call) wp_send_json_error(['message' => $error_message]);
            return;
        }
        
        if (empty($article_data['content']) || empty($article_data['title'])) {
            $error_message = '❌ AI response was valid JSON but missing required "content" or "title".';
            error_log($error_message);
            error_log('Article Data Received: ' . print_r($article_data, true));
            if ($is_bulk_processing) self::re_add_link_to_bulk($source_link, 'Missing Content in AI JSON');
            if ($is_ajax_call) wp_send_json_error(['message' => $error_message]);
            return;
        }

        // --- Prepare and Save Post ---
        $post_id = $is_ajax_call ? (isset($_POST['post_id']) ? intval($_POST['post_id']) : null) : null;
        
        $post_data = [
            'post_title'    => $article_data['title'],
            'post_content'  => wp_kses_post($article_data['content']),
            'post_status'   => $options['status'] ?? 'draft',
            'post_author'   => $options['author_name'] ?? (get_current_user_id() ? get_current_user_id() : 1),
            'post_excerpt'  => isset($article_data['summary']) ? wp_kses_post($article_data['summary']) : '',
        ];

        if($post_id) {
            $post_data['ID'] = $post_id;
        }

        error_log('--- ✅ PREPARING TO SAVE POST ---');
        error_log('Source Link: ' . $source_link);
        error_log('Post Data: ' . print_r($post_data, true));
        error_log('--- END SAVE PREPARATION ---');

        $new_post_id = Post_Manager::insert_or_update_post($post_id, $post_data);

        if (is_wp_error($new_post_id)) {
            $error_message = '❌ Failed to save post to database: ' . $new_post_id->get_error_message();
            error_log($error_message);
            if ($is_bulk_processing) self::re_add_link_to_bulk($source_link, 'DB Save Error');
            if ($is_ajax_call) wp_send_json_error(['message' => $error_message]);
            return;
        }
        
        error_log("✅ Successfully generated and saved post ID: {$new_post_id} from source: {$source_link}");

        // --- Set Taxonomies and Meta ---
        Post_Manager::set_post_tags($new_post_id, $article_data['tags'] ?? []);
        Post_Manager::set_post_categories($new_post_id, $article_data['category'] ?? '');
        update_post_meta($new_post_id, '_custom_source_url', $source_link);


        // --- Generate Image if enabled ---
        if (isset($options['generate_image']) && $options['generate_image'] === 'yes') {
             error_log('🖼️ Auto-generating image for post ID: ' . $new_post_id);
             self::generate_image_for_article($new_post_id);
        }

        if ($is_ajax_call) {
            wp_send_json_success([
                'message' => 'Article generated successfully!',
                'post_id' => $new_post_id,
                'title' => $post_data['post_title'],
                'content' => $post_data['post_content'],
            ]);
        }
    }


    public static function generate_image_for_article($post_id = null)
    {
        error_log('🖼️ GENERATE_IMAGE_FOR_ARTICLE() STARTED');
        // Folosim var_export pentru a vedea exact tipul variabilei (null, '', etc.)
        error_log('📥 Initial call state: post_id argument=' . var_export($post_id, true) . ', $_POST=' . print_r($_POST, true));

        // Corectăm detecția apelului AJAX. empty() va trata corect atât null cât și string-urile goale.
        $is_ajax = empty($post_id);

        if ($is_ajax) {
            // This is an AJAX call, get post_id from $_POST
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            error_log('--- AJAX Call --- Assigned post_id from $_POST. New value: [' . $post_id . ']');
            
            try {
                check_ajax_referer('generate_image_nonce', 'security');
                error_log('✅ Nonce verification successful for image generation.');
            } catch (Exception $e) {
                error_log('❌ Nonce verification failed for image generation: ' . $e->getMessage());
                wp_send_json_error(['message' => 'Nonce verification failed: ' . $e->getMessage()]);
                return;
            }
        } else {
             error_log('--- Internal Call --- Using provided post_id: [' . $post_id . ']');
        }

        if (empty($post_id) || !is_numeric($post_id) || $post_id <= 0) {
            error_log('❌ Invalid or zero post ID (' . $post_id . '). Aborting.');
            wp_send_json_error(['message' => 'ID-ul postării lipsește sau este invalid.']);
            return;
        }

        $feedback = sanitize_text_field($_POST['feedback'] ?? '');
        $post = get_post($post_id);

        if (!$post) {
            error_log('❌ Article not found for ID: ' . $post_id);
            wp_send_json_error(['message' => 'Articolul nu a fost găsit.']);
            return;
        }

        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key'];

        if (empty($api_key)) {
            error_log('❌ API key is empty - cannot generate image.');
            wp_send_json_error(['message' => 'Cheia API lipsește pentru generarea imaginii.']);
            return;
        }

        $summary = get_the_excerpt($post_id) ?: wp_trim_words($post->post_content, 100, '...');
        $tags = wp_get_post_tags($post_id, ['fields' => 'names']);

        error_log('📋 Image generation input:');
        error_log('   - Post ID: ' . $post_id);
        error_log('   - Summary: ' . $summary);
        error_log('   - Tags: ' . implode(', ', $tags));
        error_log('   - Feedback: ' . ($feedback ?: 'EMPTY'));

        error_log('🎨 Calling DALL-E API with:');
        error_log('   - Summary: ' . $summary);
        error_log('   - Tags: ' . implode(', ', $tags));
        error_log('   - Feedback: ' . $feedback);

        $image_response = call_openai_image_api($api_key, $summary, $tags, $feedback);

        if (is_wp_error($image_response)) {
            error_log('❌ DALL-E API WP Error: ' . $image_response->get_error_message());
            wp_send_json_error(['message' => 'Eroare la apelul DALL-E API: ' . $image_response->get_error_message()]);
            return;
        }

        $response_code = wp_remote_retrieve_response_code($image_response);
        error_log('📊 DALL-E API Response Code: ' . $response_code);

        $image_body = wp_remote_retrieve_body($image_response);
        error_log('📥 DALL-E API RAW RESPONSE BODY: ' . $image_body);

        $image_json = json_decode($image_body, true);
        error_log('🔍 DALL-E API DECODED RESPONSE: ' . print_r($image_json, true));

        $image_url = $image_json['data'][0]['url'] ?? '';
        $title = get_the_title($post_id);

        error_log('🖼️ Generated image URL: ' . ($image_url ?: 'NONE'));

        if (!empty($image_url)) {
            Post_Manager::set_featured_image($post_id, $image_url, $title, $summary);
            update_post_meta($post_id, '_external_image_source', 'Imagine generată AI');

            $post_status = $options['status'];
            if ($post_status == 'publish') {
                Post_Manager::insert_or_update_post($post_id, ['post_status' => $post_status]);
            }

            if (isset($post_id['error'])) {
                error_log('❌ Error updating post status after image generation: ' . $post_id['error']);
                wp_send_json_error(['message' => $post_id['error']]);
                return;
            }

            error_log('✅ Image generated and set successfully for post ID: ' . $post_id);
            wp_send_json_success([
                    'post_id' => $post_id,
                    'tags' => $tags,
                    'summary' => $summary,
                    'image_url' => $image_url, // Returnăm URL-ul imaginii generate
                    'message' => 'Imaginea a fost generată și setată!.'
                ]);
        } else {
            $error_message = 'Eroare necunoscută la generarea imaginii.';
            if (isset($image_json['error']['message'])) {
                $error_message = $image_json['error']['message'];
            } elseif (isset($image_json['error'])) {
                $error_message = print_r($image_json['error'], true);
            }
            error_log('❌ Failed to generate image for post ID ' . $post_id . ': ' . $error_message);
            wp_send_json_error(['message' => 'Eroare la generarea imaginii: ' . $error_message]);
        }
    }


    public static function auto_generate_article()
    {
        // Folosit pentru apelurile cron (automate)
        self::process_article_generation();
    }

    /**
     * AJAX handler to check if the settings page needs to be refreshed.
     * This is used for providing feedback during bulk processing.
     */
    public static function check_settings_changes() {
        check_ajax_referer('auto_ai_news_poster_check_settings', 'security');

        error_log('AJAX Polling: Checking for refresh transient...');
        $force_refresh = get_transient('auto_ai_news_poster_force_refresh');

        if ($force_refresh === 'yes') {
            error_log('AJAX Polling: Refresh transient FOUND. Instructing client to reload.');
            delete_transient('auto_ai_news_poster_force_refresh'); // Consume the transient
            wp_send_json_success(['needs_refresh' => true, 'reason' => 'A bulk link was processed or mode changed.']);
        } else {
            error_log('AJAX Polling: No refresh transient. No action needed.');
            wp_send_json_success(['needs_refresh' => false, 'reason' => 'No change detected.']);
        }
    }

    /**
     * Debugging function to test the refresh mechanism.
     */
    public static function force_refresh_test()
    {
        check_ajax_referer('auto_ai_news_poster_check_settings', 'security');
        error_log('DEBUG: Forcing refresh transient via AJAX call.');
        set_transient('auto_ai_news_poster_force_refresh', 'yes', MINUTE_IN_SECONDS);
        wp_send_json_success(['message' => 'Refresh transient set!']);
    }

    public static function clear_transient()
    {
        // Verificăm nonce-ul pentru securitate
        check_ajax_referer('clear_transient_nonce', 'security');

        error_log('clear_transient() called');

        // Ștergem transient-ul
        delete_transient('auto_ai_news_poster_last_bulk_check');

        error_log('clear_transient: transient deleted');

        wp_send_json_success(['message' => 'Transient cleared successfully']);
    }

    private static function force_mode_change_to_manual()
    {
        error_log('🔄 Forcing mode change to manual.');
        $options = get_option('auto_ai_news_poster_settings');
        $options['mode'] = 'manual';
        // Uncheck the "run until exhausted" checkbox
        if (isset($options['run_until_bulk_exhausted'])) {
            $options['run_until_bulk_exhausted'] = 0;
        }
        update_option('auto_ai_news_poster_settings', $options);

        // Set a transient to notify the frontend to refresh
        set_transient('auto_ai_news_poster_force_refresh', 'yes', MINUTE_IN_SECONDS);
        error_log('✅ Mode changed to manual and refresh transient set.');
    }

    public static function force_refresh_now()
    {
        // Verificăm nonce-ul pentru securitate
        check_ajax_referer('force_refresh_now_nonce', 'security');

        error_log('force_refresh_now() called');

        // Forțăm un refresh imediat
        set_transient('auto_ai_news_poster_force_refresh', true, 60);

        error_log('force_refresh_now: Force refresh set');

        wp_send_json_success(['message' => 'Force refresh triggered']);
    }

    /**
     * Re-adds a failed link to the end of the bulk list for a later retry.
     *
     * @param string $link The URL to re-add.
     * @param string $reason The reason for the failure.
     */
    private static function re_add_link_to_bulk($link, $reason = 'Unknown Error') {
        if (empty($link)) return;

        error_log("🔄 Failure processing link: {$link}. Reason: {$reason}. Re-adding to list for retry.");
        $options = get_option('auto_ai_news_poster_settings');
        // Ensure the array key exists and is an array. The links are stored as a string, so we need to convert.
        $bulk_links_str = $options['bulk_custom_source_urls'] ?? '';
        $bulk_links = array_filter(explode("\n", trim($bulk_links_str)), 'trim');

        // To be safe, don't add duplicates
        if (!in_array($link, $bulk_links)) {
            $bulk_links[] = $link;
            $options['bulk_custom_source_urls'] = implode("\n", $bulk_links);
            update_option('auto_ai_news_poster_settings', $options);
            error_log('✅ Link re-added to bulk list. Total links: ' . count($bulk_links));
        }
    }

    private static function extract_article_content_from_url($url)
    {
        error_log('🔗 Extracting content from URL: ' . $url);
        $response = wp_remote_get($url, ['timeout' => 300]); // Mărit timeout-ul la 300 de secunde (5 minute)

        if (is_wp_error($response)) {
            error_log('❌ WP_Remote_Get error: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        error_log('📦 Raw response body: ' . $body);

        if (empty($body)) {
            error_log('⚠️ Extracted body is empty for URL: ' . $url);
            return new WP_Error('empty_body', 'Nu s-a putut extrage conținutul din URL-ul furnizat.');
        }

        // Utilizăm o librărie pentru parsarea HTML (de exemplu, Simple HTML DOM)
        // Aceasta este o dependență externă și trebuie instalată
        // require_once 'simple_html_dom.php'; // Dacă folosiți Simple HTML DOM

        // Exemplu de parsare cu Simple HTML DOM (dacă este instalat)
        // $html = str_get_html($body);
        // if ($html) {
        //     $article_content = $html->find('article', 0)->innertext; // Extrage conținutul articolului
        //     $html->clear(); // Eliberează memoria
        //     return $article_content;
        // } else {
        //     error_log('❌ Simple HTML DOM parsing failed for URL: ' . $url);
        //     return new WP_Error('html_parse_failed', 'Nu s-a putut parsa HTML-ul din URL-ul furnizat.');
        // }

        // Exemplu de parsare simplă (fără librărie)
        // Aceasta este o implementare simplă și poate fi inexactă
        $article_content = '';
        $dom = new DOMDocument();
        @$dom->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
        $xpath = new DOMXPath($dom);

        // Extrage conținutul din elementul <body>
        $body_node = $xpath->query('//body')->item(0);
        if (!$body_node) {
            error_log('⚠️ No <body> tag found. Returning raw body content after basic cleanup.');
            $article_content = preg_replace('/[ \t]+/', ' ', $body);
            $article_content = preg_replace('/(?:\s*\n\s*){2,}/', "\n\n", $article_content);
            $article_content = trim(strip_tags($article_content));
            return $article_content;
        }

        // Extragem 'innerHTML' din elementul <body> pentru a evita reconstruirea <head>
        $body_inner_html = '';
        foreach ($body_node->childNodes as $child_node) {
            $body_inner_html .= $dom->saveHTML($child_node);
        }

        // Reconstruim un DOMDocument doar cu conținutul din <body> (fără head)
        $dom_body_clean = new DOMDocument();
        // Încărcăm HTML-ul în mod explicit într-o structură completă pentru a preveni auto-adăugarea de <head>
        @$dom_body_clean->loadHTML('<html><body>' . $body_inner_html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
        $xpath_body = new DOMXPath($dom_body_clean);

        // Nodul de context pentru căutările ulterioare este acum elementul body din noul document
        $context_node_clean = $xpath_body->query('//body')->item(0);
        if (!$context_node_clean) {
            error_log('❌ Failed to re-parse body content after innerHTML extraction.');
            return new WP_Error('body_reparse_failed', 'Eroare internă la procesarea conținutului articolului.');
        }

        // 1. Eliminăm elementele irelevante din noul document (doar <body>)
        $elements_to_remove = [
            '//script',
            '//style',
            '//header',
            '//footer',
            '//nav',
            '//aside',
            '//form',
            '//iframe',
            '//noscript',
            '//meta',
            '//link',
            '//img[not(@src)]',
            '//svg',
            '//button',
            '//input',
            '//select',
            '//textarea',
            '//comment()',
            '*[contains(@class, "ad")]',
            '*[contains(@class, "ads")]',
            '*[contains(@id, "ad")]',
            '*[contains(@id, "ads")]',
            '*[contains(@class, "sidebar")]',
            '*[contains(@id, "sidebar")]',
            '*[contains(@class, "menu")]',
            '*[contains(@id, "menu")]',
            '*[contains(@class, "widget")]',
            '*[contains(@id, "widget")]',
            '*[contains(@class, "breadcrumb")]',
            '*[contains(@id, "breadcrumb")]',
        ];

        foreach ($elements_to_remove as $selector) {
            $nodes = $xpath_body->query($selector, $context_node_clean); // Căutăm în contextul body curățat
            if ($nodes) {
                foreach ($nodes as $node) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // 2. Caută elementul principal de articol (într-o ordine de prioritate) în contextul curățat
        $selectors = [
            '//article',
            '//main',
            '//div[contains(@class, "entry-content")]',
            '//div[contains(@class, "post-content")]',
            '//div[contains(@class, "article-content")]',
            '//div[contains(@class, "td-post-content")]',
            '//div[contains(@id, "content")]',
            '//div[contains(@class, "content")]',
            '//div[contains(@class, "td-container")]',
            '//div[contains(@class, "tdc-row")]',
            '//div[contains(@class, "tdb-block-inner td-fix-index")]',
            '//div[contains(@class, "td_block_wrap")]',
            '//div[contains(@class, "td-ss-main-content")]',
            '//div[contains(@class, "tdb-block-inner")]',
            '//div[contains(@class, "tdb_single_content")]',
            '//div[contains(@class, "td-post-content tagdiv-type")]',
            "//div[@class='tdb_single_content']",
            "//div[@id='td-outer-wrap']",
            '.', // Fallback: iau conținutul din nodul de context rămas (body)
        ];

        $found_node = null;
        foreach ($selectors as $selector) {
            $nodes = $xpath_body->query($selector, $context_node_clean);
            if ($nodes->length > 0) {
                $best_node = null;
                $max_text_length = 0;
                foreach ($nodes as $node) {
                    $text_length = strlen(trim($node->textContent));
                    if ($text_length > $max_text_length) {
                        $max_text_length = $text_length;
                        $best_node = $node;
                    }
                }
                if ($best_node) {
                    $found_node = $best_node;
                    break;
                }
            }
        }

        if ($found_node) {
            $article_content = $found_node->textContent;
        } else {
            $article_content = $context_node_clean->textContent; // Folosesc textul din body-ul curățat
        }

        // 3. Post-procesare pentru curățarea textului
        $article_content = preg_replace('/[ \t]+/', ' ', $article_content);
        $article_content = preg_replace('/(?:\s*\n\s*){2,}/', "\n\n", $article_content);
        $article_content = trim($article_content);

        error_log('✅ Content extracted. Length: ' . strlen($article_content));
        $max_content_length = 15000;
        if (strlen($article_content) > $max_content_length) {
            $article_content = substr($article_content, 0, $max_content_length);
            error_log('⚠️ Article content truncated to ' . $max_content_length . ' characters.');
        }
        return $article_content;
    }
}

Auto_Ai_News_Poster_Api::init();

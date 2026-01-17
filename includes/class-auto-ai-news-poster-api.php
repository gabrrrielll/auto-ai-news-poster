<?php

require_once 'constants/config.php';
require_once 'class-auto-ai-news-post-manager.php';
require_once 'class-auto-ai-news-poster-parser.php';

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
        
        // Task List Management
        add_action('wp_ajax_auto_ai_run_task_list_item', [self::class, 'ajax_run_task_list_item']);
    }

    /**
     * AJAX handler to process one item from a specific task list.
     */
    public static function ajax_run_task_list_item()
    {
        check_ajax_referer('auto_ai_news_poster_check_settings', 'nonce');
        
        $list_id = isset($_POST['list_id']) ? sanitize_text_field($_POST['list_id']) : '';
        if (empty($list_id)) {
            wp_send_json_error(['message' => 'Missing List ID']);
        }

        $result = self::process_task_list_item($list_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'Articol generat cu succes!', 'post_id' => $result]);
    }

    /**
     * Core logic to process one title from a task list.
     * Can be called from AJAX or Cron.
     */
    public static function process_task_list_item($list_id)
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $task_lists = $options['task_lists'] ?? [];
        $tasks_config = $options['tasks_config'] ?? [];
        
        $list_index = -1;
        foreach ($task_lists as $index => $list) {
            if (($list['id'] ?? '') === $list_id) {
                $list_index = $index;
                break;
            }
        }

        if ($list_index === -1) {
            return new WP_Error('list_not_found', 'Lista de taskuri nu a fost găsită.');
        }

        $list = $task_lists[$list_index];
        $titles_raw = $list['titles'] ?? '';
        $titles = array_filter(array_map('trim', explode("\n", $titles_raw)));

        if (empty($titles)) {
            return new WP_Error('no_titles', 'Nu mai există titluri de procesat în această listă.');
        }

        // 1. Get first title
        $target_title = array_shift($titles);
        $category_id = $list['category'] ?? 0;
        $category_name = ($category_id) ? get_cat_name($category_id) : 'General';
        $author_id = $list['author'] ?? 1;

        // 2. Determine AI Config (GLOBAL - from tasks_config)
        $provider = $tasks_config['api_provider'] ?? 'openai';
        $ai_args = [
            'provider' => $provider,
            'api_key'  => ($provider === 'openai') ? ($tasks_config['chatgpt_api_key'] ?? '') : (($provider === 'gemini') ? ($tasks_config['gemini_api_key'] ?? '') : ($tasks_config['deepseek_api_key'] ?? '')),
            'model'    => ($provider === 'openai') ? ($tasks_config['ai_model'] ?? 'gpt-4o-mini') : (($provider === 'gemini') ? ($tasks_config['gemini_model'] ?? 'gemini-1.5-flash') : ($tasks_config['deepseek_model'] ?? 'deepseek-chat')),
        ];

        // 3. Prepare task-specific article length settings for AI prompt
        $article_length_settings = [
            'article_length_option' => $list['article_length_option'] ?? 'same_as_source',
            'min_length'            => $list['min_length'] ?? '',
            'max_length'            => $list['max_length'] ?? ''
        ];

        // 4. Call AI with task-specific AI instructions and article length
        $ai_instructions = $list['ai_instructions'] ?? '';
        $prompt = Auto_Ai_News_Poster_Prompts::get_task_article_prompt($target_title, $category_name, $ai_instructions, $article_length_settings);
        $response = call_ai_api($prompt, $ai_args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $ai_content_json = $decoded['choices'][0]['message']['content'] ?? null;

        if (empty($ai_content_json)) {
            return new WP_Error('empty_response', 'Răspunsul AI este gol sau invalid.');
        }

        $article_data = json_decode($ai_content_json, true);
        if (!$article_data || empty($article_data['content'])) {
            // Try cleaning markdown if present
            if (preg_match('/```json\s*(.*?)\s*```/s', $ai_content_json, $matches)) {
                $article_data = json_decode($matches[1], true);
            }
        }

        if (!$article_data || empty($article_data['content'])) {
            return new WP_Error('json_error', 'Nu am putut decoda datele articolului din JSON-ul AI.');
        }

        // 5. Save Article - use task-specific publication status
        $post_status = $list['post_status'] ?? 'draft';
        $post_data = [
            'post_title'   => $article_data['title'] ?? $target_title,
            'post_content' => wp_kses_post($article_data['content']),
            'post_status'  => $post_status,
            'post_author'  => $author_id,
            'post_excerpt' => $article_data['summary'] ?? '',
        ];

        $post_id = Post_Manager::insert_or_update_post(null, $post_data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set Category and Tags - use task-specific settings
        if ($category_id) {
            wp_set_post_categories($post_id, [$category_id]);
        }
        
        $gen_tags = $list['generate_tags'] ?? 'yes';
        if ($gen_tags === 'yes') {
            Post_Manager::set_post_tags($post_id, $article_data['tags'] ?? []);
        }

        update_post_meta($post_id, '_generation_mode', 'tasks');
        update_post_meta($post_id, '_task_list_id', $list_id);

        // 6. Update List (Remove processed title)
        $task_lists[$list_index]['titles'] = implode("\n", $titles);
        $options['task_lists'] = $task_lists;
        update_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, $options);

        // 7. Generate Image if enabled - use task-specific setting
        $gen_image = $list['generate_image'] ?? 'no';
        if ($gen_image === 'yes') {
            $image_prompt = $article_data['summary'] ?? $article_data['title'];
            self::generate_image_for_article($post_id, $image_prompt);
        }

        return $post_id;
    }


    public static function get_article_from_sources()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $publication_mode = $options['mode']; // Verificăm dacă este 'manual' sau 'auto'

        if ($publication_mode === 'manual') {
            try {
                check_ajax_referer('get_article_from_sources_nonce', 'security');
            } catch (Exception $e) {
                wp_send_json_error(['message' => 'Nonce verification failed']);
                return;
            }
        }

        // Get generation mode from metabox
        $generation_mode_metabox = isset($_POST['generation_mode_metabox']) ? sanitize_text_field($_POST['generation_mode_metabox']) : 'parse_link';

        return self::process_article_generation($generation_mode_metabox);
    }


    // Funcție pentru a obține categoria următoare
    public static function get_next_category()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);

        // Verificăm dacă rularea automată a categoriilor este activată și modul este automat
        if ($options['auto_rotate_categories'] === 'yes' && $options['mode'] === 'auto') {

            $categories = get_categories(['orderby' => 'name', 'order' => 'ASC', 'hide_empty' => false]);
            $category_ids = wp_list_pluck($categories, 'term_id'); // Obținem ID-urile categoriilor

            // Obținem indexul ultimei categorii utilizate
            $current_index = get_option(AUTO_AI_NEWS_POSTER_CURRENT_CATEGORY_INDEX, 0);

            // Calculăm următoarea categorie
            $next_category_id = $category_ids[$current_index];
            $next_category = get_category($next_category_id);
            $next_category_name = $next_category ? $next_category->name : 'Unknown';

            // Actualizăm indexul pentru următoarea utilizare
            $current_index = ($current_index + 1) % count($category_ids); // Resetăm la 0 când ajungem la finalul listei
            update_option(AUTO_AI_NEWS_POSTER_CURRENT_CATEGORY_INDEX, $current_index);

            return $next_category_name; // Returnăm numele categoriei
        }

        // Dacă rularea automată a categoriilor nu este activată, folosim categoria selectată manual
        $fallback_category = $options['categories'][0] ?? '';
        return $fallback_category; // Folosim prima categorie din listă dacă este setată
    }


    public static function getLastCategoryTitles($selected_category_name = null, $titlesNumber = 3)
    {
        $titles = [];
        if ($selected_category_name === null) {
            // Obține toate categoriile
            $categories = get_categories(['hide_empty' => false]);

            if (empty($categories)) {
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
                return ;
            }
        } else {
            // Obține ID-ul categoriei pe baza numelui
            $category = get_category_by_slug(sanitize_title($selected_category_name));

            if (!$category) {
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
    public static function process_article_generation($generation_mode = 'parse_link')
    {
        $is_ajax_call = wp_doing_ajax();

        // Load settings
        $options = get_option('auto_ai_news_poster_settings');
        if (empty($options['chatgpt_api_key'])) {
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_msg]);
            }
            return;
        }

        // Determinăm modul de generare (relevant mai ales pentru CRON)
        $generation_mode = $generation_mode;

        if ($generation_mode === 'ai_browsing' && !$is_ajax_call) {
            // Pentru CRON în modul AI Browsing, logica este gestionată de Auto_Ai_News_Poster_Cron::trigger_ai_browsing_generation()
            // Această funcție (process_article_generation) este acum dedicată modului parse_link
            return;
        }

        $source_link = '';
        $extracted_content = '';
        $is_bulk_processing = false;
        $additional_instructions = '';
        $log_prefix = $is_ajax_call ? 'MANUAL PROCESS:' : 'CRON PROCESS:';

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

            // Handle generation based on selected mode from metabox
            if ($generation_mode === 'ai_browsing') {
                // For manual AI browsing, we instruct the AI to browse the provided link
                self::generate_article_with_browsing($source_link, null, null, $additional_instructions); // Pass additional instructions
                wp_send_json_success(['message' => 'Article generation via AI Browsing initiated!', 'post_id' => get_the_ID()]);
                return;
            } else {
                // Default to 'parse_link' behavior
                $extracted_content = Auto_AI_News_Poster_Parser::extract_content_from_url($source_link);
            }

        } else {
            // Automatic generation from the bulk list (CRON job)
            $is_bulk_processing = true;
            $bulk_links_raw = isset($options['bulk_custom_source_urls']) ? $options['bulk_custom_source_urls'] : '';

            // Ensure it's handled as string (queue)
            if (is_array($bulk_links_raw)) {
                $urls = array_map(function($item) { return $item['url']; }, $bulk_links_raw);
                $bulk_links_raw = implode("\n", $urls);
            }

            $bulk_links = explode("\n", (string)$bulk_links_raw);
            $bulk_links = array_map('trim', $bulk_links);
            $bulk_links = array_filter($bulk_links);

            error_log($log_prefix . ' Starting bulk processing. Total links in queue: ' . count($bulk_links));

            if (empty($bulk_links)) {
                error_log($log_prefix . ' No links found in bulk list (Queue)');
                if (isset($options['run_until_bulk_exhausted']) && $options['run_until_bulk_exhausted'] === 'yes') {
                    self::force_mode_change_to_manual();
                }
                return;
            }

            // Get the first link from the queue
            $source_link = array_shift($bulk_links);
            error_log($log_prefix . ' Processing link: ' . $source_link);

            // Save the updated queue (with the processed link removed)
            $options['bulk_custom_source_urls'] = implode("\n", $bulk_links);
            update_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, $options);
            set_transient('auto_ai_news_poster_force_refresh', 'yes', MINUTE_IN_SECONDS); // Signal settings to refresh visually if open

            // For CRON jobs, determine mode from settings. The parameter $generation_mode is from manual metabox.
            $cron_generation_mode = $options['generation_mode'] ?? 'parse_link';
            if ($cron_generation_mode === 'ai_browsing') {
                error_log($log_prefix . ' Using AI browsing mode for link: ' . $source_link);
                // In CRON, generate_article_with_browsing will determine categories and titles
                self::generate_article_with_browsing($source_link, null, null, $options['ai_browsing_instructions'] ?? '');
                return;
            } else {
                error_log($log_prefix . ' Extracting content from URL: ' . $source_link);
                $extracted_content = Auto_AI_News_Poster_Parser::extract_content_from_url($source_link);
            }
        }

        // The rest of this function will only execute for 'parse_link' mode.
        // If 'ai_browsing' was selected, the function would have returned already.


        // --- Validate extracted content ---
        if (is_wp_error($extracted_content) || empty(trim($extracted_content))) {
            $error_message = is_wp_error($extracted_content) ? $extracted_content->get_error_message() : 'Extracted content is empty.';
            
            error_log($log_prefix . ' Failed to extract content from: ' . $source_link . ' - Error: ' . $error_message);

            if ($is_bulk_processing) {
                error_log($log_prefix . ' Re-adding link to bulk list: ' . $source_link);
                self::re_add_link_to_bulk($source_link, 'Failed to extract content');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => 'Failed to extract content from URL. Please check the link and try again. Error: ' . $error_message]);
            }
            return;
        }
        
        error_log($log_prefix . ' Content extracted successfully from: ' . $source_link . ' (Length: ' . strlen($extracted_content) . ' chars)');

        // --- Validate extracted content for suspicious patterns ---
        $suspicious_patterns = [

        ];

        $is_suspicious_content = false;
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($extracted_content, $pattern) !== false) {
                $is_suspicious_content = true;
                break;
            }
        }

        if ($is_suspicious_content) {
            error_log($log_prefix . ' Suspicious content detected for: ' . $source_link);
            if ($is_bulk_processing) {
                error_log($log_prefix . ' Re-adding link to bulk list due to suspicious content: ' . $source_link);
                self::re_add_link_to_bulk($source_link, 'Suspicious content detected - possible parsing failure');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => 'Content extraction failed - detected suspicious content pattern. Please check the URL and try again.']);
            }
            return;
        }

        // --- Allow reusing the same link multiple times ---
        // Removed duplicate link prevention to allow regenerating articles from the same source


        // --- Call OpenAI API ---
        error_log($log_prefix . ' Calling AI API for: ' . $source_link);
        $prompt = generate_custom_source_prompt($extracted_content, $additional_instructions, $source_link);
        $response = call_ai_api($prompt);

        if (is_wp_error($response)) {
            $error_message = 'AI API Error: ' . $response->get_error_message(); // Generic error message
            error_log($log_prefix . ' AI API error for: ' . $source_link . ' - ' . $error_message);

            if ($is_bulk_processing) {
                error_log($log_prefix . ' Re-adding link to bulk list due to API error: ' . $source_link);
                self::re_add_link_to_bulk($source_link, 'OpenAI API Error');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message]);
            }
            return;
        }
        
        error_log($log_prefix . ' AI API response received for: ' . $source_link);

        // --- Process API Response ---
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);
        $ai_content_json = $decoded_response['choices'][0]['message']['content'] ?? null;

        if (empty($ai_content_json)) {
            $error_message = '❌ AI response is empty or in an unexpected format.';
            error_log($log_prefix . ' Empty AI response for: ' . $source_link);

            if ($is_bulk_processing) {
                error_log($log_prefix . ' Re-adding link to bulk list due to empty AI response: ' . $source_link);
                self::re_add_link_to_bulk($source_link, 'Empty AI Response');
            }
            if ($is_ajax_call) {
                // Return also the full response for easier debugging in the frontend console
                wp_send_json_error([
                    'message' => $error_message, 
                    'api_response' => $body,
                    'is_error' => true
                ]);
            }
            return;
        }

        $article_data = json_decode($ai_content_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = '❌ Failed to decode article data JSON from AI response. Error: ' . json_last_error_msg();
            error_log($log_prefix . ' JSON decode error for: ' . $source_link . ' - ' . $error_message);

            if ($is_bulk_processing) {
                error_log($log_prefix . ' Re-adding link to bulk list due to JSON decode error: ' . $source_link);
                self::re_add_link_to_bulk($source_link, 'JSON Decode Error');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message]);
            }
            return;
        }

        if (empty($article_data['content']) || empty($article_data['title'])) {
            $error_message = '❌ AI response was valid JSON but missing required "content" or "title".';
            error_log($log_prefix . ' Missing content/title in AI JSON for: ' . $source_link);

            if ($is_bulk_processing) {
                error_log($log_prefix . ' Re-adding link to bulk list due to missing content: ' . $source_link);
                self::re_add_link_to_bulk($source_link, 'Missing Content in AI JSON');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message]);
            }
            return;
        }
        
        error_log($log_prefix . ' Article data extracted successfully. Title: ' . $article_data['title']);

        // --- Prepare and Save Post ---
        $post_id = $is_ajax_call ? (isset($_POST['post_id']) ? intval($_POST['post_id']) : null) : null;

        $post_data = [
            'post_title'    => $article_data['title'],
            'post_content'  => wp_kses_post($article_data['content']),
            'post_status'   => $options['status'] ?? 'draft',
            'post_author'   => $options['author_name'] ?? (get_current_user_id() ? get_current_user_id() : 1),
            'post_excerpt'  => isset($article_data['summary']) ? wp_kses_post($article_data['summary']) : '',
        ];

        if ($post_id) {
            $post_data['ID'] = $post_id;
        }

        error_log($log_prefix . ' Saving post to database for: ' . $source_link);
        $new_post_id = Post_Manager::insert_or_update_post($post_id, $post_data);

        if (is_wp_error($new_post_id)) {
            $error_message = '❌ Failed to save post to database: ' . $new_post_id->get_error_message();
            error_log($log_prefix . ' Failed to save post for: ' . $source_link . ' - ' . $error_message);

            if ($is_bulk_processing) {
                error_log($log_prefix . ' Re-adding link to bulk list due to DB save error: ' . $source_link);
                self::re_add_link_to_bulk($source_link, 'DB Save Error');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message]);
            }
            return;
        }
        
        error_log($log_prefix . ' Post saved successfully! Post ID: ' . $new_post_id . ' for link: ' . $source_link);

        // --- Set Taxonomies and Meta ---
        Post_Manager::set_post_tags($new_post_id, $article_data['tags'] ?? []);

        // Set category based on AI's choice from the parsed content
        if (!empty($article_data['category'])) {
            $category_name = $article_data['category'];
            $category_id = get_cat_ID($category_name);
            if ($category_id) {
                wp_set_post_categories($new_post_id, [$category_id]);
            }
        }

        update_post_meta($new_post_id, '_custom_source_url', $source_link);

        // Actualizează timpul ultimului articol publicat pentru cron (doar pentru procesarea în masă)
        if ($is_bulk_processing && !$is_ajax_call) {
            $post_time = time();
            update_option(AUTO_AI_NEWS_POSTER_LAST_POST_TIME, $post_time);
            error_log($log_prefix . ' Article published successfully! Post ID: ' . $new_post_id . ', Link: ' . $source_link . ', Time: ' . date('Y-m-d H:i:s', $post_time));
        }

        // --- Generate Image if enabled and not already present ---
        if (isset($options['generate_image']) && $options['generate_image'] === 'yes' && !has_post_thumbnail($new_post_id)) {
            $prompt_for_dalle = !empty($post_data['post_excerpt']) ? $post_data['post_excerpt'] : wp_trim_words($post_data['post_content'], 100, '...');
            if (!empty($prompt_for_dalle)) {
                self::generate_image_for_article($new_post_id, $prompt_for_dalle);
            }
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


    /**
     * Generează un articol folosind modul AI Browsing.
     *
     * @param string $news_sources Sursele de știri (separate de newline).
     * @param string $category_name Numele categoriei de interes.
     * @param array $latest_titles Lista cu titlurile ultimelor articole pentru a evita duplicarea.
     */
    public static function generate_article_with_browsing($news_sources, $category_name, $latest_titles, $additional_instructions = '')
    {
        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key'];

        if (empty($api_key)) {
            return;
        }

        // Construim promptul
        $prompt = self::build_ai_browsing_prompt($news_sources, $category_name, $latest_titles, $additional_instructions);

        // Apelăm API-ul OpenAI cu tool calling pentru AI Browsing
        $response = self::call_openai_api_with_browsing($api_key, $prompt);

        if (is_wp_error($response)) {
            return;
        }

        // Procesăm răspunsul
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);
        $message = $decoded_response['choices'][0]['message'] ?? null;

        if (empty($message)) {
            return;
        }

        // Verificăm dacă AI-ul a făcut tool calls
        if (isset($message['tool_calls']) && !empty($message['tool_calls'])) {

            // Continuăm conversația cu tool calls
            $final_response = self::continue_conversation_with_tool_calls($api_key, $prompt, $message['tool_calls']);

            if (is_wp_error($final_response)) {
                return;
            }

            $body = wp_remote_retrieve_body($final_response);
            $decoded_response = json_decode($body, true);
            $message = $decoded_response['choices'][0]['message'] ?? null;
        }

        // Acum căutăm conținutul final
        $ai_content_json = $message['content'] ?? null;

        if (empty($ai_content_json)) {
            return;
        }

        // Încercăm să extragem primul obiect JSON valid din răspuns
        $article_data = self::extract_first_valid_json($ai_content_json);

        if (empty($article_data)) {
            return;
        }

        // Verificăm dacă conținutul este gol sau invalid
        if (empty($article_data['continut']) || empty($article_data['titlu']) ||
            $article_data['continut'] === '' || $article_data['titlu'] === '') {

            // Încercăm să regenerăm cu un prompt mai clar
            $retry_response = self::retry_ai_browsing_with_clearer_prompt($api_key, $news_sources, $category_name, $latest_titles);

            if (!is_wp_error($retry_response)) {
                $body = wp_remote_retrieve_body($retry_response);
                $decoded_response = json_decode($body, true);
                $message = $decoded_response['choices'][0]['message'] ?? null;
                $ai_content_json = $message['content'] ?? null;

                if (!empty($ai_content_json)) {
                    $article_data = self::extract_first_valid_json($ai_content_json);
                    if (!empty($article_data) && !empty($article_data['continut']) && !empty($article_data['titlu'])) {
                    } else {
                        return;
                    }
                } else {
                    return;
                }
            } else {
                return;
            }
        }

        // Preparăm și salvăm postarea
        $post_data = [
            'post_title'    => sanitize_text_field($article_data['titlu']),
            'post_content'  => wp_kses_post($article_data['continut']),
            'post_status'   => $options['status'] ?? 'draft',
            'post_author'   => $options['author_name'] ?? 1,
            'post_excerpt'  => isset($article_data['meta_descriere']) ? sanitize_text_field($article_data['meta_descriere']) : '',
            'post_category' => [get_cat_ID($category_name)]
        ];

        $new_post_id = Post_Manager::insert_or_update_post(null, $post_data);

        if (is_wp_error($new_post_id)) {
            return;
        }

        // Setăm tag-uri și meta
        $tags = $article_data['cuvinte_cheie'] ?? [];
        Post_Manager::set_post_tags($new_post_id, $tags);
        update_post_meta($new_post_id, '_generation_mode', 'ai_browsing');

        // Actualizează timpul ultimului articol publicat pentru cron (doar pentru procesarea automată)
        // Verificăm dacă suntem în modul automat prin verificarea setărilor
        $settings = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, []);
        if (isset($settings['mode']) && $settings['mode'] === 'auto') {
            update_option(AUTO_AI_NEWS_POSTER_LAST_POST_TIME, time());
        }

        // Generăm imaginea dacă este activată opțiunea și nu există deja una
        $prompt_for_dalle_browsing = !empty($article_data['meta_descriere']) ? $article_data['meta_descriere'] : wp_trim_words($article_data['continut'], 100, '...');
        if (!empty($prompt_for_dalle_browsing) && isset($options['generate_image']) && $options['generate_image'] === 'yes' && !has_post_thumbnail($new_post_id)) {
            self::generate_image_for_article($new_post_id, $prompt_for_dalle_browsing);
        }
    }

    /**
     * Construiește promptul pentru modul AI Browsing.
     */
    private static function build_ai_browsing_prompt($news_sources, $category_name, $latest_titles, $additional_instructions = '')
    {
        $options = get_option('auto_ai_news_poster_settings');
        $custom_instructions_from_settings = $options['ai_browsing_instructions'] ?? 'Scrie un articol de știre original, în limba română ca un jurnalist. Articolul trebuie să fie obiectiv, informativ și bine structurat (introducere, cuprins, încheiere).';
        $latest_titles_str = !empty($latest_titles) ? implode("\n- ", $latest_titles) : 'Niciun articol recent.';

        // Obținem setările de lungime a articolului
        $article_length_option = $options['article_length_option'] ?? 'same_as_source';
        $min_length = $options['min_length'] ?? 800; // Default values
        $max_length = $options['max_length'] ?? 1200; // Default values

        $length_instruction = '';
        if ($article_length_option === 'set_limits' && $min_length && $max_length) {
            $length_instruction = "Articolul trebuie să aibă între {$min_length} și {$max_length} de cuvinte.";
        } else {
            $length_instruction = 'Articolul trebuie să aibă o lungime similară cu un articol de știri tipic.';
        }

        // Prioritizăm instrucțiunile suplimentare din metabox, altfel folosim pe cele din setări
        $final_instructions = !empty($additional_instructions) ? $additional_instructions : $custom_instructions_from_settings;

        return generate_ai_browsing_prompt($news_sources, $category_name, $latest_titles_str, $final_instructions, $length_instruction);
    }

    /**
     * Apelăm API-ul OpenAI cu tool calling pentru modul AI Browsing.
     */
    private static function call_openai_api_with_browsing($api_key, $prompt)
    {
        // Obținem modelul selectat din setări
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, []);
        $selected_model = $options['ai_model'] ?? DEFAULT_AI_MODEL;

        // Obținem max_length pentru a seta max_completion_tokens
        $max_length = $options['max_length'] ?? 1200;
        $max_completion_tokens = ceil($max_length * 2); // Estimare: 1 cuvânt ~ 2 tokens

        $request_body = [
            'model' => $selected_model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => Auto_Ai_News_Poster_Prompts::get_ai_browsing_system_message()
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'web_search',
                        'description' => 'Search the web for current information',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => [
                                    'type' => 'string',
                                    'description' => 'The search query'
                                ]
                            ],
                            'required' => ['query']
                        ]
                    ]
                ]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'article_response',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'titlu' => [
                                'type' => 'string',
                                'description' => 'Titlul articolului generat'
                            ],
                            'continut' => [
                                'type' => 'string',
                                'description' => 'Conținutul complet al articolului generat'
                            ],
                            'meta_descriere' => [
                                'type' => 'string',
                                'description' => 'Meta descriere SEO de maximum 160 de caractere'
                            ],
                            'cuvinte_cheie' => [
                                'type' => 'array',
                                'description' => 'Lista de cuvinte cheie pentru SEO',
                                'items' => [
                                    'type' => 'string'
                                ]
                            ]
                        ],
                        'required' => ['titlu', 'continut', 'meta_descriere', 'cuvinte_cheie'],
                        'additionalProperties' => false
                    ]
                ]
            ],
            'max_completion_tokens' => $max_completion_tokens,
        ];

        // Debug logs: model + message payload preview (fără chei API)
        $messages_preview = [];
        foreach (($request_body['messages'] ?? []) as $m) {
            $role = isset($m['role']) ? (string) $m['role'] : 'unknown';
            $content = isset($m['content']) ? (string) $m['content'] : '';
            $messages_preview[] = [
                'role' => $role,
                'len' => strlen($content),
                'preview' => substr($content, 0, 250),
            ];
        }
        $response_format_type = $request_body['response_format']['type'] ?? null;
        $tools_count = isset($request_body['tools']) && is_array($request_body['tools']) ? count($request_body['tools']) : 0;
        error_log('[AUTO_AI_NEWS_POSTER] AI browsing request model=' . $selected_model . ' response_format=' . (string) $response_format_type . ' tools=' . $tools_count . ' messages=' . wp_json_encode($messages_preview));

        $response = wp_remote_post(URL_API_OPENAI_CHAT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($request_body),
            'timeout' => 300, // 5 minute timeout pentru browsing
        ]);

        return $response;
    }

    /**
     * Continuă conversația cu tool calls pentru AI Browsing.
     */
    private static function continue_conversation_with_tool_calls($api_key, $original_prompt, $tool_calls)
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, []);
        $selected_model = $options['ai_model'] ?? DEFAULT_AI_MODEL;

        // Obținem max_length pentru a seta max_completion_tokens
        $max_length = $options['max_length'] ?? 1200;
        $max_completion_tokens = ceil($max_length * 2); // Estimare: 1 cuvânt ~ 2 tokens

        // Construim mesajele pentru conversația continuată
        $messages = [
            [
                'role' => 'system',
                'content' => Auto_Ai_News_Poster_Prompts::get_ai_browsing_system_message()
            ],
            [
                'role' => 'user',
                'content' => $original_prompt
            ],
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => $tool_calls
            ]
        ];

        // Simulăm răspunsurile tool-urilor cu informații concrete
        $tool_responses = [
            'site:antena3.ro' => 'Găsit articol recent: "Nouă descoperire științifică revoluționară în domeniul inteligenței artificiale. Cercetătorii români au dezvoltat un algoritm care poate procesa date 10 ori mai rapid decât sistemele actuale."',
            'site:libertatea.ro' => 'Găsit articol recent: "Tehnologie avansată pentru combaterea schimbărilor climatice. Un nou sistem de monitorizare a emisiilor de CO2 a fost implementat în România."',
            'site:mediafax.ro' => 'Găsit articol recent: "Dezvoltări în domeniul energiei regenerabile. O nouă tehnologie de panouri solare cu eficiență sporită a fost lansată pe piață."',
            'site:agerpres.ro' => 'Găsit articol recent: "Cercetări științifice în domeniul medicinei. O echipă de cercetători români a descoperit o nouă metodă de tratament pentru boli rare."'
        ];

        foreach ($tool_calls as $tool_call) {
            $query = json_decode($tool_call['function']['arguments'], true)['query'] ?? '';
            $site_response = '';

            // Determinăm răspunsul pe baza query-ului
            if (strpos($query, 'antena3.ro') !== false) {
                $site_response = $tool_responses['site:antena3.ro'];
            } elseif (strpos($query, 'libertatea.ro') !== false) {
                $site_response = $tool_responses['site:libertatea.ro'];
            } elseif (strpos($query, 'mediafax.ro') !== false) {
                $site_response = $tool_responses['site:mediafax.ro'];
            } elseif (strpos($query, 'agerpres.ro') !== false) {
                $site_response = $tool_responses['site:agerpres.ro'];
            } else {
                $site_response = 'Găsit articol recent despre știință și tehnologie. Informații relevante pentru categoria specificată.';
            }

            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $tool_call['id'],
                'content' => $site_response . ' Acum scrie un articol complet care să respecte lungimea setată în instrucțiuni și să returneze DOAR obiectul JSON cu titlu, conținut, imagine_prompt, meta_descriere și cuvinte_cheie.'
            ];
        }

        $request_body = [
            'model' => $selected_model,
            'messages' => $messages,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'article_response',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'titlu' => [
                                'type' => 'string',
                                'description' => 'Titlul articolului generat'
                            ],
                            'continut' => [
                                'type' => 'string',
                                'description' => 'Conținutul complet al articolului generat'
                            ],
                            'meta_descriere' => [
                                'type' => 'string',
                                'description' => 'Meta descriere SEO de maximum 160 de caractere'
                            ],
                            'cuvinte_cheie' => [
                                'type' => 'array',
                                'description' => 'Lista de cuvinte cheie pentru SEO',
                                'items' => [
                                    'type' => 'string'
                                ]
                            ]
                        ],
                        'required' => ['titlu', 'continut', 'meta_descriere', 'cuvinte_cheie'],
                        'additionalProperties' => false
                    ]
                ]
            ],
            'max_completion_tokens' => $max_completion_tokens,
        ];

        // Debug logs: model + message payload preview (fără chei API)
        $messages_preview = [];
        foreach (($request_body['messages'] ?? []) as $m) {
            $role = isset($m['role']) ? (string) $m['role'] : 'unknown';
            $content = isset($m['content']) ? (string) $m['content'] : '';
            $messages_preview[] = [
                'role' => $role,
                'len' => strlen($content),
                'preview' => substr($content, 0, 250),
            ];
        }
        $response_format_type = $request_body['response_format']['type'] ?? null;
        $tool_calls_count = is_array($tool_calls) ? count($tool_calls) : 0;
        error_log('[AUTO_AI_NEWS_POSTER] AI browsing continue request model=' . $selected_model . ' response_format=' . (string) $response_format_type . ' tool_calls=' . $tool_calls_count . ' messages=' . wp_json_encode($messages_preview));

        $response = wp_remote_post(URL_API_OPENAI, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($request_body),
            'timeout' => DEFAULT_TIMEOUT_SECONDS,
        ]);

        return $response;
    }

    /**
     * Extrage primul obiect JSON valid din răspunsul AI.
     */
    private static function extract_first_valid_json($content)
    {
        // Încercăm să găsim primul obiect JSON valid
        $json_pattern = '/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/';
        preg_match_all($json_pattern, $content, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $json_string) {
                $decoded = json_decode($json_string, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        // Dacă nu găsim cu regex, încercăm să extragem manual
        $lines = explode("\n", $content);
        $json_lines = [];
        $in_json = false;
        $brace_count = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            // Verificăm dacă linia începe un obiect JSON
            if (strpos($line, '{') === 0) {
                $in_json = true;
                $json_lines = [];
                $brace_count = 0;
            }

            if ($in_json) {
                $json_lines[] = $line;
                $brace_count += substr_count($line, '{') - substr_count($line, '}');

                // Dacă am închis toate parantezele, încercăm să decodăm
                if ($brace_count === 0) {
                    $json_string = implode('', $json_lines);
                    $decoded = json_decode($json_string, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }

                    $in_json = false;
                }
            }
        }

        return null;
    }

    /**
     * Retry AI Browsing cu prompt mai clar și simplu.
     */
    private static function retry_ai_browsing_with_clearer_prompt($api_key, $news_sources, $category_name, $latest_titles)
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, []);
        $selected_model = $options['ai_model'] ?? DEFAULT_AI_MODEL;

        $simple_prompt = generate_retry_browsing_prompt($category_name);

        // Obținem max_length pentru a seta max_completion_tokens
        $max_length = $options['max_length'] ?? 1200;
        $max_completion_tokens = ceil($max_length * 2);
        // Estimare: 1 cuvânt ~ 2 tokens

        $request_body = [
            'model' => $selected_model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $simple_prompt
                ]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'article_response',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'titlu' => ['type' => 'string'],
                            'continut' => ['type' => 'string'],
                            'meta_descriere' => ['type' => 'string'],
                            'cuvinte_cheie' => [
                                'type' => 'array',
                                'items' => ['type' => 'string']
                            ]
                        ],
                        'required' => ['titlu', 'continut', 'meta_descriere', 'cuvinte_cheie'],
                        'additionalProperties' => false
                    ]
                ]
            ],
            'max_completion_tokens' => $max_completion_tokens,
        ];

        $response = wp_remote_post(URL_API_OPENAI, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($request_body),
            'timeout' => DEFAULT_IMAGE_TIMEOUT_SECONDS,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Generează un prompt sigur și abstract pentru DALL-E, evitând conținutul sensibil.
     *
     * @param string $original_prompt Promptul generat inițial.
     * @param string $api_key Cheia API OpenAI.
     * @return string Promptul abstractizat pentru DALL-E.
     */
    private static function generate_safe_dalle_prompt(string $original_prompt, string $api_key): string
    {
        $system_message = generate_dalle_abstraction_system_message();
        $user_message = generate_dalle_abstraction_user_message($original_prompt);

        $prompt_for_ai = generate_simple_text_prompt($system_message, $user_message);
        $response = call_openai_api($api_key, $prompt_for_ai);

        if (is_wp_error($response)) {
            return 'Abstract representation of news events.'; // Fallback safe prompt
        }

        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);
        $safe_prompt = $decoded_response['choices'][0]['message']['content'] ?? $original_prompt;

        return $safe_prompt;
    }

    public static function generate_image_for_article($post_id = null, $imagine_prompt = '')
    {
        // Folosim var_export pentru a vedea exact tipul variabilei (null, '', etc.)
        // Corectăm detecția apelului AJAX. empty() va trata corect atât null cât și string-urile goale.
        $is_ajax = empty($post_id);

        if ($is_ajax) {
            // This is an AJAX call, get post_id from $_POST
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

            try {
                check_ajax_referer('generate_image_nonce', 'security');
            } catch (Exception $e) {
                wp_send_json_error(['message' => 'Nonce verification failed: ' . $e->getMessage()]);
                return;
            }
        }

        if (empty($post_id) || !is_numeric($post_id) || $post_id <= 0) {
            wp_send_json_error(['message' => 'ID-ul postării lipsește sau este invalid.']);
            return;
        }

        $feedback = sanitize_text_field($_POST['feedback'] ?? '');
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(['message' => 'Articolul nu a fost găsit.']);
            return;
        }

        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        // Temporar: doar OpenAI activ (Gemini dezactivat chiar dacă există în setări).
        $use_gemini = false;
        
        // Verificăm cheia API în funcție de provider
        if ($use_gemini) {
            $api_key = $options['gemini_api_key'] ?? '';
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'Cheia API Gemini lipsește pentru generarea imaginii.']);
                return;
            }
        } else {
            $api_key = $options['chatgpt_api_key'] ?? '';
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'Cheia API OpenAI lipsește pentru generarea imaginii.']);
                return;
            }
        }

        // Use imagine_prompt if provided, otherwise fall back to summary and tags
        $summary = get_the_excerpt($post_id);
        $initial_prompt = !empty($imagine_prompt) ? $imagine_prompt : (
            $summary ?: wp_trim_words($post->post_content, 100, '...')
        );

        error_log('=== GENERATE_IMAGE_FOR_ARTICLE AJAX ===');
        error_log('Post ID: ' . $post_id);
        error_log('Use Gemini: ' . ($use_gemini ? 'YES' : 'NO'));
        error_log('Feedback: ' . (!empty($feedback) ? $feedback : 'NONE'));
        
        // Generează promptul inițial
        $openai_api_key = $options['chatgpt_api_key'] ?? '';
        $max_retries = 3;
        $retry_count = 0;
        $image_response = null;
        $prompt_for_image = $initial_prompt;
        
        // Funcție helper pentru a adăuga instrucțiuni de fotorealism la prompt
        $add_photorealism_instructions = function($prompt) {
            $instructions = get_photorealism_instructions();
            $photorealism_prefix = $instructions['prefix'];
            $photorealism_suffix = $instructions['suffix'];
            
            // Verificăm dacă promptul nu conține deja instrucțiuni similare
            $has_instructions = (
                stripos($prompt, 'fotografie') !== false ||
                stripos($prompt, 'fotorealist') !== false ||
                stripos($prompt, 'jurnalistic') !== false
            );
            
            if (!$has_instructions) {
                return $photorealism_prefix . $prompt . $photorealism_suffix;
            }
            
            return $prompt;
        };
        
        // Pentru Gemini, generăm promptul sigur folosind OpenAI dacă este disponibil
        if ($use_gemini && !empty($openai_api_key)) {
            $prompt_for_image = self::generate_safe_dalle_prompt($initial_prompt, $openai_api_key);
            // Asigurăm că promptul generat include instrucțiuni de fotorealism
            $prompt_for_image = $add_photorealism_instructions($prompt_for_image);
        } elseif (!$use_gemini && !empty($openai_api_key)) {
            // Pentru OpenAI/DALL-E, generăm un prompt sigur optimizat pentru DALL-E
            $prompt_for_image = self::generate_safe_dalle_prompt($initial_prompt, $openai_api_key);
            // Asigurăm că promptul generat include instrucțiuni de fotorealism
            $prompt_for_image = $add_photorealism_instructions($prompt_for_image);
        } else {
            // Dacă nu avem OpenAI key, adăugăm instrucțiuni de fotorealism direct la prompt
            $prompt_for_image = $add_photorealism_instructions($initial_prompt);
        }
        
        // Încercăm generarea imaginii cu retry logic
        while ($retry_count < $max_retries) {
            $retry_count++;
            error_log('Attempt ' . $retry_count . '/' . $max_retries . ' - Prompt: ' . substr($prompt_for_image, 0, 100) . '...');
            
            // Folosim wrapper-ul care alege automat provider-ul corect
            $image_response = call_ai_image_api($prompt_for_image, $feedback);
            
            error_log('Image response type: ' . gettype($image_response));
            if (is_wp_error($image_response)) {
                $error_code = $image_response->get_error_code();
                $error_message = $image_response->get_error_message();
                
                error_log('Image response is WP_Error: ' . $error_message);
                error_log('Error code: ' . $error_code);
                
                // Verificăm dacă eroarea este legată de safety filters
                $is_safety_error = (
                    strpos(strtolower($error_message), 'safety') !== false ||
                    strpos(strtolower($error_message), 'blocked') !== false ||
                    strpos(strtolower($error_message), 'harm') !== false ||
                    strpos(strtolower($error_message), 'content policy') !== false ||
                    strpos($error_message, '[SAFETY_BLOCK]') !== false ||
                    $error_code === 'generation_stopped'
                );
                
                // Dacă este eroare de safety și mai avem încercări, regenerăm promptul
                if ($is_safety_error && $retry_count < $max_retries && !empty($openai_api_key)) {
                    error_log('Safety filter detected. Regenerating prompt for retry ' . ($retry_count + 1));
                    // Regenerăm promptul folosind OpenAI pentru a evita filtrele de safety
                    $prompt_for_image = self::generate_safe_dalle_prompt($initial_prompt, $openai_api_key);
                    // Adăugăm un delay mic între încercări
                    sleep(1);
                    continue;
                } elseif ($is_safety_error && $retry_count < $max_retries && empty($openai_api_key)) {
                    // Dacă nu avem OpenAI key dar avem eroare de safety, adăugăm instrucțiuni de fotorealism
                    error_log('Safety filter detected but no OpenAI key. Adding photorealism instructions for retry ' . ($retry_count + 1));
                    $prompt_for_image = $add_photorealism_instructions($initial_prompt);
                    sleep(1);
                    continue;
                } else {
                    // Dacă nu mai avem încercări sau nu este eroare de safety, returnăm eroarea
                    break;
                }
            } elseif (is_array($image_response)) {
                error_log('Image response is array, keys: ' . implode(', ', array_keys($image_response)));
                if (isset($image_response['data'])) {
                    error_log('Image response data structure: ' . json_encode([
                        'data_is_array' => is_array($image_response['data']),
                        'data_count' => is_array($image_response['data']) ? count($image_response['data']) : 0,
                        'first_data_keys' => is_array($image_response['data']) && isset($image_response['data'][0]) ? array_keys($image_response['data'][0]) : [],
                    ]));
                }
                // Dacă avem răspuns valid, ieșim din loop
                break;
            }
        }

        // Verificăm dacă răspunsul este un WP_Error sau un array direct
        if (is_wp_error($image_response)) {
            $provider_name = $use_gemini ? 'Gemini' : 'OpenAI';
            $error_message = $image_response->get_error_message();
            if ($retry_count >= $max_retries) {
                $error_message .= ' (Am încercat ' . $max_retries . ' ori cu prompturi diferite)';
            }
            error_log('Sending JSON error to frontend after ' . $retry_count . ' attempts');
            wp_send_json_error(['message' => 'Eroare la apelul ' . $provider_name . ' API: ' . $error_message]);
            return;
        }

        // Procesăm răspunsul în funcție de tip
        $image_url = '';
        error_log('Processing image response...');
        
        if (is_array($image_response) && isset($image_response['data'][0]['url'])) {
            // Răspuns direct de la Gemini (array)
            $image_url = $image_response['data'][0]['url'];
            error_log('Image URL extracted from array: ' . $image_url);
        } elseif (is_array($image_response) && is_array($image_response['data']) && !empty($image_response['data'][0])) {
            // Verificăm structura alternativă
            $first_data = $image_response['data'][0];
            if (isset($first_data['url'])) {
                $image_url = $first_data['url'];
                error_log('Image URL extracted from nested structure: ' . $image_url);
            } else {
                error_log('No URL found in data structure. First data item keys: ' . implode(', ', array_keys($first_data)));
            }
        } else {
            // Răspuns HTTP de la OpenAI (wp_remote_post response)
            error_log('Processing as HTTP response...');
            $response_code = wp_remote_retrieve_response_code($image_response);
            $image_body = wp_remote_retrieve_body($image_response);
            $image_json = json_decode($image_body, true);
            
            error_log('HTTP Response code: ' . $response_code);
            error_log('HTTP Response body (first 200 chars): ' . substr($image_body, 0, 200));
            
            if ($response_code === 200 && isset($image_json['data'][0]['url'])) {
                $image_url = $image_json['data'][0]['url'];
                error_log('Image URL extracted from HTTP response: ' . $image_url);
            } else {
                $error_message = 'Eroare necunoscută la generarea imaginii.';
                if (isset($image_json['error']['message'])) {
                    $error_message = $image_json['error']['message'];
                } elseif (isset($image_json['error'])) {
                    $error_message = print_r($image_json['error'], true);
                }
                error_log('Error extracting image URL: ' . $error_message);
                wp_send_json_error(['message' => 'Eroare la generarea imaginii: ' . $error_message]);
                return;
            }
        }
        
        error_log('Final image_url: ' . ($image_url ? $image_url : 'EMPTY'));
        
        if (empty($image_url)) {
            error_log('ERROR: Image URL is empty after processing');
            wp_send_json_error(['message' => 'Eroare: URL-ul imaginii este gol după procesare.']);
            return;
        }
        
        error_log('Getting post title and tags...');
        $title = get_the_title($post_id);
        $post_tags = get_the_terms($post_id, 'post_tag');
        $tags = !empty($post_tags) ? wp_list_pluck($post_tags, 'name') : [];

        // Trimitem răspunsul AJAX imediat pentru a evita timeout-ul
        // Procesarea imaginii va continua în background
        error_log('Sending JSON success response immediately...');
        
        // Trimitem răspunsul manual pentru a putea continua procesarea
        status_header(200);
        header('Content-Type: application/json; charset=utf-8');
        nocache_headers();
        
        $response_data = [
            'success' => true,
            'data' => [
                'post_id' => $post_id,
                'tags' => $tags,
                'summary' => $summary,
                'image_url' => $image_url,
                'message' => 'Imaginea a fost generată! Se procesează...'
            ]
        ];
        
        echo wp_json_encode($response_data);
        
        // Trimitem răspunsul imediat clientului și continuăm procesarea în background
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // Pentru alte configurații, închidem output buffering
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
        }
        
        error_log('Setting featured image in background...');
        try {
            $set_image_result = Post_Manager::set_featured_image($post_id, $image_url, $title, $summary);
            error_log('set_featured_image result: ' . (is_wp_error($set_image_result) ? 'ERROR: ' . $set_image_result->get_error_message() : 'SUCCESS'));
            
            if (is_wp_error($set_image_result)) {
                error_log('Error setting featured image: ' . $set_image_result->get_error_message());
                wp_die();
            }
            
            update_post_meta($post_id, '_external_image_source', 'Imagine generată AI');
            error_log('Post meta updated');

            $post_status = $options['status'];
            if ($post_status == 'publish') {
                error_log('Updating post status to publish...');
                $update_result = Post_Manager::insert_or_update_post($post_id, ['post_status' => $post_status]);

                if (is_wp_error($update_result)) {
                    error_log('Error updating post: ' . $update_result->get_error_message());
                    wp_die();
                }
                error_log('Post status updated successfully');
            }

            error_log('Image processing completed successfully');
        } catch (Exception $e) {
            error_log('Stack trace: ' . $e->getTraceAsString());
        }
        wp_die();
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
    public static function check_settings_changes()
    {
        check_ajax_referer('auto_ai_news_poster_check_settings', 'security');

        $force_refresh = get_transient('auto_ai_news_poster_force_refresh');

        if ($force_refresh === 'yes') {
            delete_transient('auto_ai_news_poster_force_refresh'); // Consume the transient
            wp_send_json_success(['needs_refresh' => true, 'reason' => 'A bulk link was processed or mode changed.']);
        } else {
            wp_send_json_success(['needs_refresh' => false, 'reason' => 'No change detected.']);
        }
    }

    /**
     * Debugging function to test the refresh mechanism.
     */
    public static function force_refresh_test()
    {
        check_ajax_referer('auto_ai_news_poster_check_settings', 'security');
        set_transient('auto_ai_news_poster_force_refresh', 'yes', MINUTE_IN_SECONDS);
        wp_send_json_success(['message' => 'Refresh transient set!']);
    }

    public static function clear_transient()
    {
        // Verificăm nonce-ul pentru securitate
        check_ajax_referer('clear_transient_nonce', 'security');

        // Ștergem transient-ul
        delete_transient('auto_ai_news_poster_last_bulk_check');

        wp_send_json_success(['message' => 'Transient cleared successfully']);
    }

    private static function force_mode_change_to_manual()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $options['mode'] = 'manual';
        // Uncheck the "run until exhausted" checkbox
        if (isset($options['run_until_bulk_exhausted'])) {
            $options['run_until_bulk_exhausted'] = 0;
        }
        update_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, $options);

        // Set a transient to notify the frontend to refresh
        set_transient('auto_ai_news_poster_force_refresh', 'yes', MINUTE_IN_SECONDS);
    }

    public static function force_refresh_now()
    {
        // Verificăm nonce-ul pentru securitate
        check_ajax_referer('force_refresh_now_nonce', 'security');

        // Forțăm un refresh imediat
        set_transient('auto_ai_news_poster_force_refresh', true, 60);

        wp_send_json_success(['message' => 'Force refresh triggered']);
    }

    /**
     * Re-adds a failed link to the end of the bulk list for a later retry.
     *
     * @param string $link The URL to re-add.
     * @param string $reason The reason for the failure.
     */
    private static function re_add_link_to_bulk($link, $reason = 'Unknown Error')
    {
        if (empty($link)) {
            error_log('CRON PROCESS: Cannot re-add empty link to bulk list');
            return;
        }

        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        // Ensure the array key exists and is an array. The links are stored as a string, so we need to convert.
        $bulk_links_str = $options['bulk_custom_source_urls'] ?? '';
        $bulk_links = array_filter(explode("\n", trim($bulk_links_str)), 'trim');

        // To be safe, don't add duplicates
        if (!in_array($link, $bulk_links)) {
            $bulk_links[] = $link;
            $options['bulk_custom_source_urls'] = implode("\n", $bulk_links);
            update_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, $options);
            error_log('CRON PROCESS: Link re-added to bulk list: ' . $link . ' (Reason: ' . $reason . ', Total links now: ' . count($bulk_links) . ')');
        } else {
            error_log('CRON PROCESS: Link already exists in bulk list, skipping re-add: ' . $link . ' (Reason: ' . $reason . ')');
        }
    }

}

Auto_Ai_News_Poster_Api::init();

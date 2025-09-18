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





    public static function process_article_generation()
    {
        error_log('🎯 PROCESS_ARTICLE_GENERATION() STARTED');

        // Preluăm datele
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
        $additional_instructions = sanitize_text_field($_POST['instructions'] ?? '');
        $custom_source_url = isset($_POST['custom_source_url']) ? sanitize_text_field($_POST['custom_source_url']) : null;

        error_log('📋 INPUT DATA:');
        error_log('   - Post ID: ' . ($post_id ?: 'NULL'));
        error_log('   - Additional Instructions: ' . ($additional_instructions ?: 'EMPTY'));
        error_log('   - Custom Source URL: ' . ($custom_source_url ?: 'EMPTY'));

        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key'];
        $sources = explode("\n", trim($options['news_sources'])); // Sursele din setări

        error_log('⚙️ CONFIGURATION:');
        error_log('   - API Key length: ' . strlen($api_key));
        error_log('   - Sources from settings: ' . print_r($sources, true));
        error_log('   - Sources count: ' . count($sources));

        if (empty($api_key)) {
            error_log('❌ API key is empty - stopping execution');
            wp_send_json_error(['message' => 'Cheia API lipsește']);
            return;
        }

        // Inițializăm $bulk_links ca un array gol
        $bulk_links = [];
        $run_until_bulk_exhausted = false;

        // Logica pentru link-ul personalizat vs. bulk links
        if (!empty($custom_source_url)) {
            error_log('📝 Using custom source URL: ' . $custom_source_url);
            // Nu este necesară logica pentru bulk links dacă avem un URL personalizat.
        } else {
            // Procesăm bulk links dacă nu există un custom_source_url
            error_log('🔄 No custom source URL, processing bulk links...');
            $run_until_bulk_exhausted = $options['run_until_bulk_exhausted'] === 'yes';
            $bulk_links = explode("\n", trim($options['bulk_custom_source_urls'] ?? ''));
            $bulk_links = array_filter($bulk_links, 'trim'); // Eliminăm rândurile goale

            error_log('DEBUG: $run_until_bulk_exhausted:'.($run_until_bulk_exhausted ? 'true' : 'false').' count($bulk_links):'. count($bulk_links).' $bulk_links:'. print_r($bulk_links, true));

            if ($run_until_bulk_exhausted && empty($bulk_links)) {
                error_log('⚠️ Bulk links exhausted in auto mode. Stopping.');
                if (wp_next_scheduled('auto_ai_news_poster_cron_hook')) {
                    wp_clear_scheduled_hook('auto_ai_news_poster_cron_hook');
                }
                self::force_mode_change_to_manual();
                if (isset($_POST['action'])) {
                    wp_send_json_error(['message' => 'Lista de linkuri s-a epuizat. Generarea automată a fost oprită.']);
                }
                return;
            }

            // Preluăm primul link din lista bulk dacă nu există un link personalizat trimis prin AJAX
            if (!empty($bulk_links)) {
                $custom_source_url = array_shift($bulk_links); // Preluăm primul link
                error_log('🔗 Taken first bulk link: ' . $custom_source_url);
            } else {
                // Dacă nici bulk links nu există, și nu am avut custom_source_url, e eroare.
                error_log('❌ No custom URL and bulk links are empty - stopping execution');
                wp_send_json_error(['message' => 'Sursele lipsesc']);
                return;
            }
        }

        // După ce am stabilit $custom_source_url (fie din input, fie din bulk), verificăm duplicatele
        if (empty($custom_source_url)) {
            error_log('❌ No custom_source_url determined, cannot proceed.');
            wp_send_json_error(['message' => 'Nu s-a putut determina un link sursă pentru generare.']);
            return;
        }

        error_log('✅ Proceeding with custom_source_url: ' . $custom_source_url);

        // Verificăm dacă acest link a fost deja folosit pentru a evita duplicatele
        if ($custom_source_url) {
            $existing_posts = get_posts([
                'meta_key' => '_custom_source_url',
                'meta_value' => $custom_source_url,
                'post_type' => 'post',
                'post_status' => ['publish', 'draft'],
                'numberposts' => 1
            ]);

            if (!empty($existing_posts)) {
                error_log('Link already used: ' . $custom_source_url . '. Skipping to next link.');

                // Actualizăm lista de linkuri în opțiuni (eliminăm rândurile goale)
                $bulk_links = array_filter($bulk_links, 'trim');
                update_option('auto_ai_news_poster_settings', array_merge($options, ['bulk_custom_source_urls' => implode("\n", $bulk_links)]));

                // Actualizăm transient-ul pentru verificarea schimbărilor
                if ($run_until_bulk_exhausted) {
                    set_transient('auto_ai_news_poster_last_bulk_check', count($bulk_links), 300);
                }

                // Pentru cron job, nu trimitem răspuns JSON
                if (isset($_POST['action'])) {
                    wp_send_json_error(['message' => 'Link already used. Skipping to next link.']);
                }
                return;
            }
        }

        // Actualizăm lista de linkuri în opțiuni (eliminăm rândurile goale)
        $bulk_links = array_filter($bulk_links, 'trim');
        update_option('auto_ai_news_poster_settings', array_merge($options, ['bulk_custom_source_urls' => implode("\n", $bulk_links)]));

        // Actualizăm transient-ul pentru verificarea schimbărilor
        if ($run_until_bulk_exhausted) {
            set_transient('auto_ai_news_poster_last_bulk_check', count($bulk_links), 300);
        }

        // Generăm promptul din config.php
        error_log('🧠 GENERATING PROMPT...');
        $article_text_content = '';

        if (!empty($custom_source_url)) {
            error_log('📝 Using custom source URL: ' . $custom_source_url . ' for content extraction.');
            $article_text_content = self::extract_article_content_from_url($custom_source_url);

            if (is_wp_error($article_text_content)) {
                error_log('❌ Error extracting content: ' . $article_text_content->get_error_message());
                wp_send_json_error(['message' => 'Eroare la extragerea conținutului articolului: ' . $article_text_content->get_error_message()]);
                return;
            }
            if (empty($article_text_content)) {
                error_log('⚠️ Extracted content is empty for URL: ' . $custom_source_url);
                wp_send_json_error(['message' => 'Nu s-a putut extrage conținutul articolului de la URL-ul furnizat.']);
                return;
            }
            error_log('✅ Content extracted. Length: ' . strlen($article_text_content));
            $prompt = generate_custom_source_prompt($article_text_content, $additional_instructions);
        } else {
            error_log('📰 Using news sources for prompt generation (no custom URL).');
            $prompt = generate_prompt($sources, $additional_instructions, []);
        }

        error_log('📨 GENERATED PROMPT:');
        error_log('   - Length: ' . strlen($prompt) . ' characters');
        error_log('   - Content: ' . substr($prompt, 0, 500) . '...');
        error_log('   - Full prompt: ' . $prompt);

        // Apelăm OpenAI API din config.php
        error_log('🤖 CALLING OPENAI API...');
        error_log('   - API Key: ' . substr($api_key, 0, 10) . '...');
        error_log('   - Post ID: ' . $post_id);
        error_log('   - Custom Source URL: ' . $custom_source_url);

        $response = call_openai_api($api_key, $prompt);

        if (is_wp_error($response)) {
            error_log('❌ API ERROR: ' . $response->get_error_message());
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        error_log('✅ API RESPONSE RECEIVED');
        $body = wp_remote_retrieve_body($response);
        error_log('📦 Raw response body: ' . $body);

        $body = json_decode($body, true);
        error_log('🔍 Decoded response: ' . print_r($body, true));

        if (isset($body['choices'][0]['message']['content'])) {
            error_log('💬 AI message content found');
            $ai_message_content = $body['choices'][0]['message']['content'];
            error_log('🤖 AI Message Content: ' . $ai_message_content);

            $content_json = json_decode($ai_message_content, true);
            error_log('🔄 Parsing AI content as JSON...');
            error_log('📊 Parsed JSON: ' . print_r($content_json, true));

            if ($content_json) {
                error_log('✅ JSON parsing successful - processing article data...');
                // Extragem datele din răspunsul structurat
                $title = $content_json['title'] ?? '';
                $content = wp_kses_post($content_json['content'] ?? '');
                $summary = wp_kses_post($content_json['summary'] ?? '');
                $category = $content_json['category'] ?? '';
                $tags = $content_json['tags'] ?? [];
                $images = $content_json['images'] ?? [];
                $sources = $content_json['sources'] ?? [];
                $source_titles = $content_json['source_titles'] ?? [];

                error_log('📄 EXTRACTED ARTICLE DATA:');
                error_log('   - Title: ' . $title);
                error_log('   - Content length: ' . strlen($content));
                error_log('   - Summary length: ' . strlen($summary));
                error_log('   - Category: ' . $category);
                error_log('   - Tags: ' . print_r($tags, true));
                error_log('   - Images: ' . print_r($images, true));

                $author_id = $options['author_name'] ?? get_current_user_id();
                error_log('👤 Author ID: ' . $author_id);

                // Construim array-ul de post_data
                $post_data = [
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'draft',
                    'post_type' => 'post',
                    'post_excerpt' => $summary,
                    'post_author' => $author_id
                ];

                error_log('💾 SAVING ARTICLE...');
                error_log('   - Post data: ' . print_r($post_data, true));

                // Folosim Post_Manager pentru a crea sau actualiza articolul
                $post_id = Post_Manager::insert_or_update_post($post_id, $post_data);

                if (isset($post_id['error'])) {
                    error_log('❌ Error saving post: ' . $post_id['error']);
                    wp_send_json_error(['message' => $post_id['error']]);
                }

                error_log('✅ Article saved with ID: ' . $post_id);

                // Setăm etichetele articolului
                error_log('🏷️ Setting tags...');
                Post_Manager::set_post_tags($post_id, $tags);

                // Setăm categoriile articolului
                error_log('📁 Setting categories...');
                Post_Manager::set_post_categories($post_id, $category);

                // Setăm linkul personalizat în metadate
                if ($custom_source_url) {
                    error_log('🔗 Setting custom source URL: ' . $custom_source_url);
                    update_post_meta($post_id, '_custom_source_url', $custom_source_url);
                }

                // În modul automat, generăm imaginea automat
                if ($options['mode'] === 'auto') {
                    error_log('🖼️ Auto mode - generating image...');
                    self::generate_image_for_article($post_id);
                }

                error_log('🎉 ARTICLE GENERATION COMPLETED SUCCESSFULLY!');
                error_log('   - Final post ID: ' . $post_id);

                wp_send_json_success([
                    'post_id' => $post_id,
                    'title' => $title,
                    'tags' => $tags,
                    'category' => $category,
                    'images' => $images,
                    'summary' => $summary,
                    'sources ' => $sources,
                    'article_content' => $content,
                    'source_titles' => $source_titles
                ]);
            } else {
                error_log('❌ JSON parsing failed - invalid AI response format');
                error_log('   - AI content was: ' . $ai_message_content);
                wp_send_json_error(['message' => 'Datele primite nu sunt în format JSON structurat.']);
            }
        } else {
            error_log('❌ No AI message content in response');
            error_log('   - Response body was: ' . print_r($body, true));
            wp_send_json_error(['message' => 'A apărut o eroare la generarea articolului.']);
        }
    }





    public static function generate_image_for_article($post_id = null)
    {
        error_log('🖼️ GENERATE_IMAGE_FOR_ARTICLE() STARTED');
        error_log('📥 Received parameters: post_id=' . $post_id . ', $_POST=' . print_r($_POST, true));

        // Preluăm post_id dacă apelul nu vine dintr-un context în care este deja setat (ex. cron)
        if ($post_id === null) {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

            // Verificăm nonce-ul pentru securitate doar pentru apelurile AJAX
            try {
                check_ajax_referer('generate_image_nonce', 'security');
                error_log('✅ Nonce verification successful for image generation.');
            } catch (Exception $e) {
                error_log('❌ Nonce verification failed for image generation: ' . $e->getMessage());
                wp_send_json_error(['message' => 'Nonce verification failed for image generation.']);
                return;
            }
        }

        if ($post_id === 0) {
            error_log('❌ No valid post ID provided for image generation.');
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

    public static function check_settings_changes()
    {
        // Verificăm nonce-ul pentru securitate
        check_ajax_referer('check_settings_changes_nonce', 'security');

        error_log('check_settings_changes() called');

        // Obținem setările curente
        $current_settings = get_option('auto_ai_news_poster_settings', []);

        // Verificăm dacă opțiunea run_until_bulk_exhausted este activată
        $run_until_bulk_exhausted = $current_settings['run_until_bulk_exhausted'] === 'yes';

        $needs_refresh = false;

        error_log('check_settings_changes: run_until_bulk_exhausted = ' . ($run_until_bulk_exhausted ? 'yes' : 'no'));

        // Verificăm dacă există un transient de forțare a refresh-ului
        $force_refresh = get_transient('auto_ai_news_poster_force_refresh');
        if ($force_refresh) {
            $needs_refresh = true;
            delete_transient('auto_ai_news_poster_force_refresh');
            error_log('check_settings_changes: FORCED refresh detected');
        }

        if ($run_until_bulk_exhausted) {
            // Verificăm dacă lista de linkuri s-a epuizat
            $bulk_links = explode("\n", trim($current_settings['bulk_custom_source_urls'] ?? ''));
            $bulk_links = array_filter($bulk_links, 'trim');

            $current_count = count($bulk_links);
            $last_check = get_transient('auto_ai_news_poster_last_bulk_check');

            error_log('check_settings_changes: current_count = ' . $current_count . ', last_check = ' . ($last_check !== false ? $last_check : 'false'));

            // Dacă lista este goală și modul este încă automat, trebuie refresh
            if (empty($bulk_links) && $current_settings['mode'] === 'auto') {
                $needs_refresh = true;
                error_log('check_settings_changes: needs_refresh = true (empty list, auto mode)');
            }

            // Dacă lista nu este goală, verificăm dacă s-a consumat cel puțin un link
            if (!empty($bulk_links)) {
                // Comparăm cu ultima verificare (stocată în transient)
                if ($last_check !== false && $current_count < $last_check) {
                    $needs_refresh = true;
                    error_log('check_settings_changes: needs_refresh = true (links consumed: ' . $last_check . ' -> ' . $current_count . ')');
                }

                // Salvăm numărul curent de linkuri pentru următoarea verificare
                set_transient('auto_ai_news_poster_last_bulk_check', $current_count, 300); // 5 minute
            }

            // FORȚARE REFRESH pentru testare - dacă transient-ul nu există, inițializăm și forțăm refresh
            if ($last_check === false && !empty($bulk_links)) {
                set_transient('auto_ai_news_poster_last_bulk_check', $current_count, 300);
                // Forțăm un refresh pentru a inițializa sistemul
                $needs_refresh = true;
                error_log('check_settings_changes: FORCED refresh for initialization');
            }
        }

        // FORȚARE REFRESH când lista este goală și modul este automat
        if ($run_until_bulk_exhausted && empty($bulk_links) && $current_settings['mode'] === 'auto') {
            $needs_refresh = true;
            error_log('check_settings_changes: FORCED refresh - empty list with auto mode');
        }

        error_log('check_settings_changes: final needs_refresh = ' . ($needs_refresh ? 'true' : 'false'));

        wp_send_json_success(['needs_refresh' => $needs_refresh]);
    }

    public static function force_refresh_test()
    {
        // Verificăm nonce-ul pentru securitate
        check_ajax_referer('force_refresh_test_nonce', 'security');

        error_log('force_refresh_test() called');

        // Obținem setările curente
        $current_settings = get_option('auto_ai_news_poster_settings', []);

        // Verificăm dacă opțiunea run_until_bulk_exhausted este activată
        $run_until_bulk_exhausted = $current_settings['run_until_bulk_exhausted'] === 'yes';

        $needs_refresh = false;

        error_log('force_refresh_test: run_until_bulk_exhausted = ' . ($run_until_bulk_exhausted ? 'yes' : 'no'));

        if ($run_until_bulk_exhausted) {
            // Verificăm dacă lista de linkuri s-a epuizat
            $bulk_links = explode("\n", trim($current_settings['bulk_custom_source_urls'] ?? ''));
            $bulk_links = array_filter($bulk_links, 'trim');

            $current_count = count($bulk_links);
            $last_check = get_transient('auto_ai_news_poster_last_bulk_check');

            error_log('force_refresh_test: current_count = ' . $current_count . ', last_check = ' . ($last_check !== false ? $last_check : 'false'));

            // Dacă lista este goală și modul este încă automat, trebuie refresh
            if (empty($bulk_links) && $current_settings['mode'] === 'auto') {
                $needs_refresh = true;
                error_log('force_refresh_test: needs_refresh = true (empty list, auto mode)');
            }

            // Dacă lista nu este goală, verificăm dacă s-a consumat cel puțin un link
            if (!empty($bulk_links)) {
                // Comparăm cu ultima verificare (stocată în transient)
                if ($last_check !== false && $current_count < $last_check) {
                    $needs_refresh = true;
                    error_log('force_refresh_test: needs_refresh = true (links consumed: ' . $last_check . ' -> ' . $current_count . ')');
                }

                // Salvăm numărul curent de linkuri pentru următoarea verificare
                set_transient('auto_ai_news_poster_last_bulk_check', $current_count, 300); // 5 minute
            }

            // FORȚARE REFRESH pentru testare - dacă transient-ul nu există, inițializăm și forțăm refresh
            if ($last_check === false && !empty($bulk_links)) {
                set_transient('auto_ai_news_poster_last_bulk_check', $current_count, 300);
                // Forțăm un refresh pentru a inițializa sistemul
                $needs_refresh = true;
                error_log('force_refresh_test: FORCED refresh for initialization');
            }
        }

        error_log('force_refresh_test: final needs_refresh = ' . ($needs_refresh ? 'true' : 'false'));

        wp_send_json_success(['needs_refresh' => $needs_refresh]);
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
        // Modificam din automatic pe manual
        $options_settings = get_option('auto_ai_news_poster_settings', []); // Preia toate opțiunile existente
        $options_settings['mode'] = 'manual'; // Setează valoarea pentru 'mode'
        update_option('auto_ai_news_poster_settings', $options_settings); // Salvează toate opțiunile în baza de date

        // Actualizăm transient-ul pentru refresh automat
        set_transient('auto_ai_news_poster_last_bulk_check', 0, 300);

        // Forțăm un refresh pentru a actualiza interfața
        set_transient('auto_ai_news_poster_force_refresh', true, 60);

        error_log('force_mode_change_to_manual: Mode changed to manual, force refresh set');
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

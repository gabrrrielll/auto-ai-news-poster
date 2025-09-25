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

    }


    public static function get_article_from_sources()
    {
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');

        // Verificăm dacă rularea automată a categoriilor este activată și modul este automat
        if ($options['auto_rotate_categories'] === 'yes' && $options['mode'] === 'auto') {

            $categories = get_categories(['orderby' => 'name', 'order' => 'ASC', 'hide_empty' => false]);
            $category_ids = wp_list_pluck($categories, 'term_id'); // Obținem ID-urile categoriilor

            // Obținem indexul ultimei categorii utilizate
            $current_index = get_option('auto_ai_news_poster_current_category_index', 0);

            // Calculăm următoarea categorie
            $next_category_id = $category_ids[$current_index];
            $next_category = get_category($next_category_id);
            $next_category_name = $next_category ? $next_category->name : 'Unknown';

            // Actualizăm indexul pentru următoarea utilizare
            $current_index = ($current_index + 1) % count($category_ids); // Resetăm la 0 când ajungem la finalul listei
            update_option('auto_ai_news_poster_current_category_index', $current_index);

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
            $bulk_links_str = $options['bulk_custom_source_urls'] ?? '';
            $bulk_links = array_filter(explode("\n", trim($bulk_links_str)), 'trim');

            if (empty($bulk_links)) {
                if (isset($options['run_until_bulk_exhausted']) && $options['run_until_bulk_exhausted']) {
                    self::force_mode_change_to_manual();
                }
                return;
            }

            // Take the first link from the list
            $source_link = array_shift($bulk_links);

            // Immediately update the option with the shortened list to prevent race conditions
            $options['bulk_custom_source_urls'] = implode("\n", $bulk_links);
            update_option('auto_ai_news_poster_settings', $options);
            set_transient('auto_ai_news_poster_force_refresh', 'yes', MINUTE_IN_SECONDS); // Signal frontend to refresh

            // For CRON jobs, determine mode from settings. The parameter $generation_mode is from manual metabox.
            $cron_generation_mode = $options['generation_mode'] ?? 'parse_link';
            if ($cron_generation_mode === 'ai_browsing') {
                // In CRON, generate_article_with_browsing will determine categories and titles
                self::generate_article_with_browsing($source_link, null, null, $options['ai_browsing_instructions'] ?? '');
                return;
            } else {
                $extracted_content = Auto_AI_News_Poster_Parser::extract_content_from_url($source_link);
            }
        }

        // The rest of this function will only execute for 'parse_link' mode.
        // If 'ai_browsing' was selected, the function would have returned already.


        // --- Validate extracted content ---
        if (is_wp_error($extracted_content) || empty(trim($extracted_content))) {
            $error_message = is_wp_error($extracted_content) ? $extracted_content->get_error_message() : 'Extracted content is empty.';

            if ($is_bulk_processing) {
                self::re_add_link_to_bulk($source_link, 'Failed to extract content');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => 'Failed to extract content from URL. Please check the link and try again. Error: ' . $error_message]);
            }
            return;
        }

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
            if ($is_bulk_processing) {
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
        $prompt = generate_custom_source_prompt($extracted_content, $additional_instructions);
        $response = call_openai_api($options['chatgpt_api_key'], $prompt);

        if (is_wp_error($response)) {
            $error_message = 'OpenAI API Error: ' . $response->get_error_message();

            if ($is_bulk_processing) {
                self::re_add_link_to_bulk($source_link, 'OpenAI API Error');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message]);
            }
            return;
        }

        // --- Process API Response ---
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);
        $ai_content_json = $decoded_response['choices'][0]['message']['content'] ?? null;

        if (empty($ai_content_json)) {
            $error_message = '❌ AI response is empty or in an unexpected format.';

            if ($is_bulk_processing) {
                self::re_add_link_to_bulk($source_link, 'Empty AI Response');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message, 'response' => json_encode($decoded_response, JSON_UNESCAPED_UNICODE)]);
            }
            return;
        }

        $article_data = json_decode($ai_content_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = '❌ Failed to decode article data JSON from AI response. Error: ' . json_last_error_msg();

            if ($is_bulk_processing) {
                self::re_add_link_to_bulk($source_link, 'JSON Decode Error');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message]);
            }
            return;
        }

        if (empty($article_data['content']) || empty($article_data['title'])) {
            $error_message = '❌ AI response was valid JSON but missing required "content" or "title".';

            if ($is_bulk_processing) {
                self::re_add_link_to_bulk($source_link, 'Missing Content in AI JSON');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message]);
            }
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

        if ($post_id) {
            $post_data['ID'] = $post_id;
        }

        $new_post_id = Post_Manager::insert_or_update_post($post_id, $post_data);

        if (is_wp_error($new_post_id)) {
            $error_message = '❌ Failed to save post to database: ' . $new_post_id->get_error_message();

            if ($is_bulk_processing) {
                self::re_add_link_to_bulk($source_link, 'DB Save Error');
            }
            if ($is_ajax_call) {
                wp_send_json_error(['message' => $error_message]);
            }
            return;
        }

        // --- Set Taxonomies and Meta ---
        Post_Manager::set_post_tags($new_post_id, $article_data['tags'] ?? []);
        Post_Manager::set_post_categories($new_post_id, $article_data['category'] ?? '');
        update_post_meta($new_post_id, '_custom_source_url', $source_link);


        // --- Generate Image if enabled ---
        if (isset($options['generate_image']) && $options['generate_image'] === 'yes') {
            $prompt_for_dalle = !empty($post_data['post_excerpt']) ? $post_data['post_excerpt'] : wp_trim_words($post_data['post_content'], 100, '...');
            if (!empty($prompt_for_dalle)) {
                self::generate_image_for_article($new_post_id, $prompt_for_dalle);
            } else {
            }
        } else {
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

        // Generăm imaginea dacă este activată opțiunea
        $prompt_for_dalle_browsing = !empty($article_data['meta_descriere']) ? $article_data['meta_descriere'] : wp_trim_words($article_data['continut'], 100, '...');
        if (!empty($prompt_for_dalle_browsing) && isset($options['generate_image']) && $options['generate_image'] === 'yes') {
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

        $prompt = "
        **Rol:** Ești un redactor de știri expert în domeniul **{$category_name}**, specializat în găsirea celor mai recente și relevante subiecte.

        **Context:** Ai la dispoziție următoarele resurse și constrângeri:
        1. **Surse de informare preferate:**
        {$news_sources}
        2. **Categorie de interes:** {$category_name}
        3. **Ultimele articole publicate pe site-ul nostru în această categorie (EVITĂ ACESTE SUBIECTE):**
        - {$latest_titles_str}

        **IMPORTANT - Folosește web browsing:**
        Pentru a găsi știri recente, FOLOSEȘTE OBLIGATORIU funcția de web browsing pentru a căuta pe site-urile specificate. Nu inventa informații - accesează direct sursele pentru a găsi știri reale din ultimele 24-48 de ore.

        **Sarcina ta:**
        1. **Cercetare:** Folosește web browsing pentru a accesa și citi articole din sursele specificate. Caută subiecte foarte recente (din ultimele 24-48 de ore), importante și relevante pentru categoria **{$category_name}**.
        2. **Verificarea unicității:** Asigură-te că subiectul ales NU este similar cu niciunul dintre titlurile deja publicate. Dacă este, alege alt subiect din browsing.
        3. **Scrierea articolului:** {$final_instructions} {$length_instruction}
        4. **Generare titlu:** Creează un titlu concis și atractiv pentru articol.
        5. **Generare etichete:** Generează între 1 și 3 etichete relevante (cuvinte_cheie) pentru articol. Fiecare cuvânt trebuie să înceapă cu majusculă.
        6. **Generare prompt pentru imagine:** Propune o descriere detaliată (un prompt) pentru o imagine reprezentativă pentru acest articol.

        **IMPORTANT - Formatarea articolului:**
        - NU folosi titluri explicite precum \"Introducere\", \"Dezvoltare\", \"Concluzie\" în text
        - Articolul trebuie să fie un text fluent și natural, fără secțiuni marcate explicit
        - Folosește formatare HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly
        - Subtitlurile H2/H3 trebuie să fie descriptive și relevante pentru conținut, nu generice
        - Fiecare paragraf să aibă sens complet și să fie bine conectat cu următorul

        **Format de răspuns OBLIGATORIU:**
        Răspunsul tău trebuie să fie EXACT UN OBIECT JSON, fără niciun alt text înainte sau după. NU adăuga mai multe obiecte JSON. NU adăuga text explicativ. Structura trebuie să fie următoarea:
        {
          \"titlu\": \"Titlul articolului generat de tine\",
          \"continut\": \"Conținutul complet al articolului, formatat în HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly. NU folosi titluri explicite precum Introducere/Dezvoltare/Concluzie.\",
          \"imagine_prompt\": \"Descrierea detaliată pentru imaginea reprezentativă.\",
          \"meta_descriere\": \"O meta descriere de maximum 160 de caractere, optimizată SEO.\",
          \"cuvinte_cheie\": [\"intre_1_si_3_etichete_relevante\"]
        }

        **PASUL 1:** Începe prin a folosi web browsing pentru a căuta pe site-urile specificate și găsi știri recente din categoria {$category_name}.
        ";

        return $prompt;
    }

    /**
     * Apelăm API-ul OpenAI cu tool calling pentru modul AI Browsing.
     */
    private static function call_openai_api_with_browsing($api_key, $prompt)
    {
        // Obținem modelul selectat din setări
        $options = get_option('auto_ai_news_poster_settings', []);
        $selected_model = $options['ai_model'] ?? 'gpt-4o';

        // Obținem max_length pentru a seta max_completion_tokens
        $max_length = $options['max_length'] ?? 1200;
        $max_completion_tokens = ceil($max_length * 2); // Estimare: 1 cuvânt ~ 2 tokens

        $request_body = [
            'model' => $selected_model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a precise news article generator. NEVER invent information. Use ONLY the exact information provided in sources. If sources mention specific lists (movies, people, events), copy them EXACTLY without modification. Always respect the required word count.'
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

        $response = wp_remote_post(URL_API_OPENAI, [
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
        $options = get_option('auto_ai_news_poster_settings', []);
        $selected_model = $options['ai_model'] ?? 'gpt-4o';

        // Obținem max_length pentru a seta max_completion_tokens
        $max_length = $options['max_length'] ?? 1200;
        $max_completion_tokens = ceil($max_length * 2); // Estimare: 1 cuvânt ~ 2 tokens

        // Construim mesajele pentru conversația continuată
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a precise news article generator. NEVER invent information. Use ONLY the exact information provided in sources. If sources mention specific lists (movies, people, events), copy them EXACTLY without modification. Always respect the required word count.'
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

        $response = wp_remote_post(URL_API_OPENAI, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($request_body),
            'timeout' => 300,
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
        $options = get_option('auto_ai_news_poster_settings', []);
        $selected_model = $options['ai_model'] ?? 'gpt-4o';

        $simple_prompt = "Scrie un articol de știri ca un jurnalist profesionist. \r\n\r\nCategoria: {$category_name}\r\n\r\nCerințe:\r\n- Titlu atractiv și descriptiv\r\n- Conținut fluent și natural, fără secțiuni marcate explicit\r\n- NU folosi titluri precum \"Introducere\", \"Dezvoltare\", \"Concluzie\"\r\n- Formatare HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly\r\n- Generează între 1 și 3 etichete relevante (cuvinte_cheie)\r\n- Limbă română\r\n- Stil jurnalistic obiectiv și informativ\r\n\r\nReturnează DOAR acest JSON:\r\n{\r\n  \"titlu\": \"Titlul articolului\",\r\n  \"continut\": \"Conținutul complet al articolului formatat în HTML, fără titluri explicite precum Introducere/Dezvoltare/Concluzie\",\r\n  \"meta_descriere\": \"Meta descriere SEO\",\r\n  \"cuvinte_cheie\": [\"intre_1_si_3_etichete_relevante\"]\r\n}";

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
            'timeout' => 120,
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
        $system_message = "Ești un asistent AI specializat în transformarea descrierilor de text în concepte vizuale sigure și abstracte, potrivite pentru generarea de imagini. Elimină orice referință directă la evenimente politice, conflicte militare, violență explicită, sau orice conținut sensibil din promptul furnizat. Concentrează-te pe crearea unei descrieri vizuale simbolice, care să evoce tema sau emoția centrală a textului, fără a fi literală sau a încălca politicile de siguranță ale generatoarelor de imagini. Folosește un limbaj poetic și metaforic. NU menționa nume de persoane, țări sau termeni militari.";
        $user_message = "Transformă următoarea descriere într-un prompt vizual sigur și abstract pentru DALL-E: \"{$original_prompt}\"";

        $prompt_for_ai = generate_simple_text_prompt($system_message, $user_message);
        $response = call_openai_api($api_key, $prompt_for_ai);

        if (is_wp_error($response)) {
            return "Abstract representation of news events."; // Fallback safe prompt
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

        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key'];

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'Cheia API lipsește pentru generarea imaginii.']);
            return;
        }

        // Use imagine_prompt if provided, otherwise fall back to summary and tags
        $summary = get_the_excerpt($post_id);
        $initial_dalle_prompt = !empty($imagine_prompt) ? $imagine_prompt : (
            $summary ?: wp_trim_words($post->post_content, 100, '...')
        );

        // Generează un prompt sigur pentru DALL-E
        $prompt_for_dalle = self::generate_safe_dalle_prompt($initial_dalle_prompt, $api_key);

        $image_response = call_openai_image_api($api_key, $prompt_for_dalle, $feedback);

        if (is_wp_error($image_response)) {
            wp_send_json_error(['message' => 'Eroare la apelul DALL-E API: ' . $image_response->get_error_message()]);
            return;
        }

        $response_code = wp_remote_retrieve_response_code($image_response);

        $image_body = wp_remote_retrieve_body($image_response);

        $image_json = json_decode($image_body, true);

        $image_url = $image_json['data'][0]['url'] ?? '';
        $title = get_the_title($post_id);

        $post_tags = get_the_terms($post_id, 'post_tag');
        $tags = !empty($post_tags) ? wp_list_pluck($post_tags, 'name') : [];

        if (!empty($image_url)) {
            Post_Manager::set_featured_image($post_id, $image_url, $title, $summary);
            update_post_meta($post_id, '_external_image_source', 'Imagine generată AI');

            $post_status = $options['status'];
            if ($post_status == 'publish') {
                $update_result = Post_Manager::insert_or_update_post($post_id, ['post_status' => $post_status]);

                if (is_wp_error($update_result)) {
                    wp_send_json_error(['message' => $update_result->get_error_message()]);
                    return;
                }
            }

            wp_send_json_success([
                    'post_id' => $post_id,
                    'tags' => $tags, // Variabila $tags va fi definită mai sus
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
        $options = get_option('auto_ai_news_poster_settings');
        $options['mode'] = 'manual';
        // Uncheck the "run until exhausted" checkbox
        if (isset($options['run_until_bulk_exhausted'])) {
            $options['run_until_bulk_exhausted'] = 0;
        }
        update_option('auto_ai_news_poster_settings', $options);

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
            return;
        }

        $options = get_option('auto_ai_news_poster_settings');
        // Ensure the array key exists and is an array. The links are stored as a string, so we need to convert.
        $bulk_links_str = $options['bulk_custom_source_urls'] ?? '';
        $bulk_links = array_filter(explode("\n", trim($bulk_links_str)), 'trim');

        // To be safe, don't add duplicates
        if (!in_array($link, $bulk_links)) {
            $bulk_links[] = $link;
            $options['bulk_custom_source_urls'] = implode("\n", $bulk_links);
            update_option('auto_ai_news_poster_settings', $options);
        }
    }

}

Auto_Ai_News_Poster_Api::init();

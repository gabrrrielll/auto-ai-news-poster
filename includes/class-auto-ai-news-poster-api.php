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
        $options = get_option('auto_ai_news_poster_settings');
        $publication_mode = $options['mode']; // Verificăm dacă este 'manual' sau 'auto'

        if ($publication_mode === 'manual') {
            check_ajax_referer('get_article_from_sources_nonce', 'security');
        }

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
        // Preluăm datele
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;

        $additional_instructions = sanitize_text_field($_POST['instructions'] ?? '');
        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key'];
        $sources = explode("\n", trim($options['news_sources'])); // Sursele din setări

        // Verificăm dacă există un link personalizat
        $custom_source_url = isset($_POST['custom_source_url']) ? sanitize_text_field($_POST['custom_source_url']) : null;
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'Cheia API lipsește']);
        }
        
        // Pentru link personalizat, nu avem nevoie de sursele din setări
        if (empty($custom_source_url) && empty($sources)) {
            wp_send_json_error(['message' => 'Sursele lipsesc']);
        }

        // Dacă avem un link personalizat, îl folosim direct
        if (!empty($custom_source_url)) {
            // Link personalizat - continuăm cu procesarea
        } else {
            // Verificăm dacă există opțiunea "Rulează automat doar până la epuizarea listei de linkuri"
            $run_until_bulk_exhausted = $options['run_until_bulk_exhausted'] === 'yes';
            $bulk_links = explode("\n", trim($options['bulk_custom_source_urls'] ?? ''));
            $bulk_links = array_filter($bulk_links, 'trim'); // Eliminăm rândurile goale

            error_log('DEBUG: $run_until_bulk_exhausted:'.$run_until_bulk_exhausted.' count($bulk_links):'. count($bulk_links).' $bulk_links:'. print_r($bulk_links, true));
            // Dacă este activată opțiunea și lista de linkuri este goală, oprim procesul
            if ($run_until_bulk_exhausted && empty($bulk_links)) {
                // Dezactivăm cron job-ul
                if (wp_next_scheduled('auto_ai_news_poster_cron_hook')) {
                    wp_clear_scheduled_hook('auto_ai_news_poster_cron_hook');
                }

                // Forțăm schimbarea modului pe manual
                self::force_mode_change_to_manual();

                error_log('Lista de linkuri personalizate a fost epuizată. Oprirea generării automate.');

                // Pentru cron job, nu trimitem răspuns JSON
                if (isset($_POST['action'])) {
                    wp_send_json_error(['message' => 'Lista de linkuri s-a epuizat. Generarea automată a fost oprită.']);
                }
                return;
            }

            // Preluăm primul link din lista bulk dacă nu există un link personalizat trimis prin AJAX
            if (!$custom_source_url && !empty($bulk_links)) {
                $custom_source_url = array_shift($bulk_links); // Preluăm primul link
            }
        }

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
        if (!empty($custom_source_url)) {
            $prompt = generate_custom_source_prompt($custom_source_url, $additional_instructions);
        } else {
            $prompt = generate_prompt($sources, $additional_instructions, []);
        }

        // Debugging
        error_log('get_article_from_sources() triggered for post ID: ' . $post_id . ' prompt: ' . $prompt . ' $custom_source_url:' . $custom_source_url);

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
                $sources = $content_json['sources'] ?? [];
                $source_titles = $content_json['source_titles'] ?? [];

                $author_id = $options['author_name'] ?? get_current_user_id();

                // Construim array-ul de post_data
                $post_data = [
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'draft',
                    'post_type' => 'post',
                    'post_excerpt' => $summary,
                    'post_author' => $author_id
                ];

                // Folosim Post_Manager pentru a crea sau actualiza articolul
                $post_id = Post_Manager::insert_or_update_post($post_id, $post_data);

                if (isset($post_id['error'])) {
                    wp_send_json_error(['message' => $post_id['error']]);
                }

                // Setăm etichetele articolului
                Post_Manager::set_post_tags($post_id, $tags);

                // Setăm categoriile articolului
                Post_Manager::set_post_categories($post_id, $category);

                // Setăm linkul personalizat în metadate
                if ($custom_source_url) {
                    update_post_meta($post_id, '_custom_source_url', $custom_source_url);
                }

                // În modul automat, generăm imaginea automat
                if ($options['mode'] === 'auto') {
                    self::generate_image_for_article($post_id);
                }

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
                wp_send_json_error(['message' => 'Datele primite nu sunt în format JSON structurat.']);
            }
        } else {
            wp_send_json_error(['message' => 'A apărut o eroare la generarea articolului.']);
        }
    }





    public static function generate_image_for_article($post_id)
    {

        if ($post_id == null) {
            $post_id = intval($_POST['post_id']);
        }
        $feedback = sanitize_text_field($_POST['feedback'] ?? ''); // Preluăm feedback-ul
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(['message' => 'Articolul nu a fost găsit.']);
        }

        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key'];

        // Extragem rezumatul pentru a-l folosi în generarea imaginii
        // $summary = get_post_meta($post_id, '_wp_excerpt', true) ?: wp_trim_words($post->post_content, 400, '...'); // nu gaseste rezumatul
        $summary = get_the_excerpt($post_id) ?: wp_trim_words($post->post_content, 100, '...');
        // Preluăm etichetele postării
        $tags = wp_get_post_tags($post_id, ['fields' => 'names']); // Extragem doar numele etichetelor


        $image_response = call_openai_image_api($api_key, $summary, $tags, $feedback);
        // $image_response = call_openai_image_api($api_key, "covid-19", $tags, $feedback); // ca sa testam raspuns negativ pt generarea imaginii
        $image_body = wp_remote_retrieve_body($image_response);
        $image_json = json_decode($image_body, true);
        $image_url = $image_json['data'][0]['url'] ?? '';
        $title = get_the_title($post_id);

        error_log('!! --> generate_image_for_article() triggered for post ID: ' . $post_id . '   $summary = ' . $summary . '    $tags:' . implode(', ', $tags) . '   $image_body:'.  $image_body);

        if (!empty($image_url)) {
            Post_Manager::set_featured_image($post_id, $image_url, $title, $summary);

            // Adaugam text cu sursa imaginii
            update_post_meta($post_id, '_external_image_source', 'Imagine generată AI');

            // Folosim Post_Manager pentru a actualiza statusului  articolul
            $post_status = $options['status'];
            if ($post_status == 'publish') {
                Post_Manager::insert_or_update_post($post_id, ['post_status' => $post_status]);
            }

            if (isset($post_id['error'])) {
                wp_send_json_error(['message' => $post_id['error']]);
            }
            // wp_send_json_success(['message' => 'Imaginea a fost generată și setată!.']);
            wp_send_json_success([
                    'post_id' => $post_id,
                    'tags' => $tags,
                    'summary' => $summary,
                    'image_json' => $image_json,
                    'body' => $image_body
                ]);
        } else {
            wp_send_json_error(['message' => $image_json['error']]);
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
}

Auto_Ai_News_Poster_Api::init();

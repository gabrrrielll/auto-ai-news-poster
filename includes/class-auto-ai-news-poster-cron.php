<?php

class Auto_Ai_News_Poster_Cron
{
    public static function init()
    {
        // Activare cron la activarea pluginului
        register_activation_hook(__FILE__, [self::class, 'activate']);
        // Dezactivare cron la dezactivarea pluginului
        register_deactivation_hook(__FILE__, [self::class, 'deactivate']);
        
        // Acțiune cron principală (Parsare/Browsing)
        add_action('auto_ai_news_poster_cron_hook', [self::class, 'auto_post']);
        
        // Acțiune cron separată pentru TASKURI
        add_action('auto_ai_news_poster_tasks_cron_hook', [self::class, 'tasks_worker']);
        
        // Resetează cron job-urile atunci când se actualizează setările
        add_action('update_option_auto_ai_news_poster_settings', [self::class, 'reset_cron']);
        
        // Adaugă un nou interval de cron bazat pe setările utilizatorului
        add_filter('cron_schedules', [self::class, 'custom_cron_interval']);
    }

    public static function activate()
    {
        add_filter('cron_schedules', [self::class, 'custom_cron_interval']);

        // Main Cron
        if (!wp_next_scheduled('auto_ai_news_poster_cron_hook')) {
            wp_schedule_event(time(), 'custom_interval', 'auto_ai_news_poster_cron_hook');
        }

        // Tasks Cron (Separate)
        if (!wp_next_scheduled('auto_ai_news_poster_tasks_cron_hook')) {
            wp_schedule_event(time() + 60, 'hourly', 'auto_ai_news_poster_tasks_cron_hook');
        }
    }


    public static function deactivate()
    {
        wp_clear_scheduled_hook('auto_ai_news_poster_cron_hook');
        wp_clear_scheduled_hook('auto_ai_news_poster_tasks_cron_hook');
    }

    public static function reset_cron()
    {
        // Dezactivează cronurile existente
        wp_clear_scheduled_hook('auto_ai_news_poster_cron_hook');
        wp_clear_scheduled_hook('auto_ai_news_poster_tasks_cron_hook');

        // Obține setările pentru a verifica dacă modul automat este activat
        $settings = get_option('auto_ai_news_poster_settings', []);
        
        // Reprogramează cronul principal cu noul interval doar dacă modul automat este activat
        if (isset($settings['mode']) && $settings['mode'] === 'auto') {
            if (!wp_next_scheduled('auto_ai_news_poster_cron_hook')) {
                $scheduled_time = time();
                wp_schedule_event($scheduled_time, 'custom_interval', 'auto_ai_news_poster_cron_hook');
                
                // Resetează timpul ultimului articol pentru a permite publicarea imediată a primului articol
                delete_option('auto_ai_news_poster_last_post_time');
            }

            // Reprogramează cronul de TASKURI (independent)
            if (!wp_next_scheduled('auto_ai_news_poster_tasks_cron_hook')) {
                wp_schedule_event(time() + 120, 'hourly', 'auto_ai_news_poster_tasks_cron_hook');
            }
        }
    }

    public static function auto_post()
    {
        // Verifică dacă există deja o procesare în curs (lock mechanism)
        $lock_key = 'auto_ai_news_poster_processing_lock';
        $lock_timeout = 300; // 5 minute timeout pentru lock
        
        // Verifică dacă există un lock activ
        $lock_time = get_transient($lock_key);
        if ($lock_time !== false) {
            return; // Oprește execuția dacă există deja o procesare în curs
        }
        
        // Setează lock-ul pentru a preveni procesarea simultană
        set_transient($lock_key, time(), $lock_timeout);
        
        try {
            $settings = get_option('auto_ai_news_poster_settings');
            $generation_mode = $settings['generation_mode'] ?? 'parse_link'; // Mod implicit: parse_link

            if ($settings['mode'] === 'auto') {
                // Verifică dacă a trecut suficient timp de la ultimul articol publicat
                $last_post_time = get_option('auto_ai_news_poster_last_post_time', 0);
                $current_time = time();
                
                // Calculează intervalul necesar
                $hours = isset($settings['cron_interval_hours']) ? (int)$settings['cron_interval_hours'] : 1;
                $minutes = isset($settings['cron_interval_minutes']) ? (int)$settings['cron_interval_minutes'] : 0;
                $required_interval = ($hours * 3600) + ($minutes * 60);
                
                // Asigură-te că intervalul este cel puțin 1 minut
                if ($required_interval < 60) {
                    $required_interval = 60;
                }
                
                // Verifică dacă a trecut suficient timp
                // Adăugăm o toleranță de 2 minute (120s) pentru a compensa timpul de execuție al AI-ului.
                // WP-Cron programează următoarea rulare de la START-ul celei curente, dar last_post_time 
                // se actualizează la FINAL. Fără toleranță, următoarea rulare ar fi refuzată de acest IF, 
                // dublând efectiv intervalul.
                $time_since_last_post = $current_time - $last_post_time;
                $tolerance = 120; // 2 minute buffer
                if ($last_post_time > 0 && $time_since_last_post < ($required_interval - $tolerance)) {
                    delete_transient($lock_key); // Eliberează lock-ul
                    return; // Oprește execuția dacă nu a trecut suficient timp
                }
                
                if ($generation_mode === 'parse_link') {
                    // Verificăm dacă opțiunea "Rulează automat doar până la epuizarea listei de linkuri" este activată
                    $run_until_bulk_exhausted = $settings['run_until_bulk_exhausted'] === 'yes';

                    if ($run_until_bulk_exhausted) {
                        // Verificăm dacă lista de linkuri este goală
                        $bulk_links = explode("\n", trim($settings['bulk_custom_source_urls'] ?? ''));
                        $bulk_links = array_filter($bulk_links, 'trim'); // Eliminăm rândurile goale

                        if (empty($bulk_links)) {
                            // Lista de linkuri s-a epuizat, oprim cron job-ul și schimbăm modul pe manual

                            // Dezactivăm cron job-ul
                            wp_clear_scheduled_hook('auto_ai_news_poster_cron_hook');

                            // Schimbăm modul din automat pe manual
                            $settings['mode'] = 'manual';
                            update_option('auto_ai_news_poster_settings', $settings);

                            // Actualizăm transient-ul pentru refresh automat
                            set_transient('auto_ai_news_poster_last_bulk_check', 0, 300);
                            
                            delete_transient($lock_key); // Eliberează lock-ul
                            return; // Oprim execuția
                        }

                        // Actualizăm transient-ul pentru verificarea schimbărilor
                        set_transient('auto_ai_news_poster_last_bulk_check', count($bulk_links), 300);
                    }

                    try {
                        // Apelează direct process_article_generation() în loc de get_article_from_sources()
                        // Timpul ultimului articol va fi actualizat în API după crearea cu succes a articolului
                        Auto_Ai_News_Poster_Api::process_article_generation();
                    } catch (Exception $e) {
                        error_log('CRON: Error during article generation: ' . $e->getMessage());
                    }
                } elseif ($generation_mode === 'ai_browsing') {
                    try {
                        // Timpul ultimului articol va fi actualizat în API după crearea cu succes a articolului
                        self::trigger_ai_browsing_generation();
                    } catch (Exception $e) {
                        error_log('CRON: Error during AI browsing generation: ' . $e->getMessage());
                    }
                }
            }
        } finally {
            delete_transient($lock_key);
        }
    }

    /**
     * Cron Job separat pentru Taskuri - cu scheduling individual per listă
     */
    public static function tasks_worker()
    {
        $settings = get_option('auto_ai_news_poster_settings', []);
        
        // Verificăm dacă modul automat este pornit
        if (!isset($settings['mode']) || $settings['mode'] !== 'auto') {
            return;
        }

        // Lock mechanism pentru taskuri
        $lock_key = 'auto_ai_news_poster_tasks_lock';
        if (get_transient($lock_key) !== false) {
            return;
        }
        set_transient($lock_key, time(), 600); // 10 minute lock

        try {
            $task_lists = $settings['task_lists'] ?? [];
            if (empty($task_lists)) {
                return;
            }

            $current_time = time();
            error_log('AANP Tasks Worker: Verificare ' . count($task_lists) . ' liste pentru procesare...');

            // Process each list based on its individual Cron interval
            foreach ($task_lists as $list) {
                $list_id = $list['id'] ?? '';
                $titles = array_filter(array_map('trim', explode("\n", $list['titles'] ?? '')));

                if (empty($titles) || empty($list_id)) {
                    continue;
                }

                // Get individual Cron settings for this list
                $cron_hours = $list['cron_interval_hours'] ?? 0;
                $cron_minutes = $list['cron_interval_minutes'] ?? 30;
                $cron_interval_seconds = ($cron_hours * 3600) + ($cron_minutes * 60);

                // Check last run time for this specific list
                $last_run_key = 'task_list_last_run_' . $list_id;
                $last_run = get_option($last_run_key, 0);

                // Should this list run now?
                if (($current_time - $last_run) >= $cron_interval_seconds) {
                    error_log('AANP Tasks Worker: Procesare titlu din lista "' . ($list['name'] ?? 'Neținută') . '" (ID: ' . $list_id . ', Interval: ' . $cron_hours . 'h ' . $cron_minutes . 'm)');
                    $result = Auto_Ai_News_Poster_Api::process_task_list_item($list_id);

                    if (is_wp_error($result)) {
                        error_log('AANP Tasks Worker ERROR: ' . $result->get_error_message());
                    } else {
                        error_log('AANP Tasks Worker: Articol generat cu succes! (Post ID: ' . $result . ')');
                        // Update last run time for this list ONLY on success
                        update_option($last_run_key, $current_time);
                    }
                } else {
                    $time_until_next = $cron_interval_seconds - ($current_time - $last_run);
                    error_log('AANP Tasks Worker: Lista "' . ($list['name'] ?? 'Neținută') . '" nu este gata încă. Timp rămas: ' . round($time_until_next / 60) . ' minute');
                }
            }

        } finally {
            delete_transient($lock_key);
        }
    }

    public static function trigger_ai_browsing_generation()
    {
        $settings = get_option('auto_ai_news_poster_settings');
        $news_sources = $settings['news_sources'] ?? '';

        if (empty($news_sources)) {
            return;
        }

        // Determinăm categoria care trebuie folosită
        $category_name = '';
        $category_id = '';

        // Verificăm dacă rotația automată a categoriilor este activată
        if (isset($settings['auto_rotate_categories']) && $settings['auto_rotate_categories'] === 'yes' &&
            isset($settings['mode']) && $settings['mode'] === 'auto') {
            // Folosim rotația automată a categoriilor
            $category_name = Auto_Ai_News_Poster_Api::get_next_category();

            // Găsim ID-ul categoriei pe baza numelui
            $category = get_category_by_slug(sanitize_title($category_name));
            $category_id = $category ? $category->term_id : '';

        } else {
            // Folosim categoria specificată
            $category_id = $settings['specific_search_category'] ?? '';
            if (empty($category_id)) {
                return;
            }

            $category = get_category($category_id);
            $category_name = $category ? $category->name : 'Diverse';
        }

        // Obține ultimele 5 titluri din categoria selectată
        $latest_posts_args = [
            'posts_per_page' => 5,
            'cat' => $category_id,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
            'fields' => 'ids'
        ];
        $latest_post_ids = get_posts($latest_posts_args);
        $latest_titles = array_map('get_the_title', $latest_post_ids);

        // Apelează funcția API pentru generare
        Auto_Ai_News_Poster_Api::generate_article_with_browsing($news_sources, $category_name, $latest_titles);
    }


    public static function custom_cron_interval($schedules)
    {
        $options = get_option('auto_ai_news_poster_settings');

        // 1. Regular Generation Interval
        $hours = isset($options['cron_interval_hours']) ? (int)$options['cron_interval_hours'] : 1;
        $minutes = isset($options['cron_interval_minutes']) ? (int)$options['cron_interval_minutes'] : 0;
        $interval = max(60, ($hours * 3600) + ($minutes * 60));

        $schedules['custom_interval'] = [
            'interval' => $interval,
            'display' => "Once every $hours hours and $minutes minutes",
        ];

        // 2. Tasks Specific Interval
        $tc = $options['tasks_config'] ?? [];
        $t_hours = isset($tc['cron_interval_hours']) ? (int)$tc['cron_interval_hours'] : 1;
        $t_minutes = isset($tc['cron_interval_minutes']) ? (int)$tc['cron_interval_minutes'] : 0;
        $t_interval = max(60, ($t_hours * 3600) + ($t_minutes * 60));

        $schedules['tasks_custom_interval'] = [
            'interval' => $t_interval,
            'display' => "Tasks: Once every $t_hours hours and $t_minutes minutes",
        ];

        return $schedules;
    }
}

Auto_Ai_News_Poster_Cron::init();

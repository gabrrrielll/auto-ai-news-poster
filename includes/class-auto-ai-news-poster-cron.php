<?php

class Auto_Ai_News_Poster_Cron
{
    public static function init()
    {
        // Activare cron la activarea pluginului
        register_activation_hook(__FILE__, [self::class, 'activate']);
        // Dezactivare cron la dezactivarea pluginului
        register_deactivation_hook(__FILE__, [self::class, 'deactivate']);
        // Acțiune cron
        add_action('auto_ai_news_poster_cron_hook', [self::class, 'auto_post']);
        // Resetează cron job-ul atunci când se actualizează setările
        add_action('update_option_auto_ai_news_poster_settings', [self::class, 'reset_cron']);
        // Adaugă un nou interval de cron bazat pe setările utilizatorului
        add_filter('cron_schedules', [self::class, 'custom_cron_interval']);
    }

    public static function activate()
    {
        add_filter('cron_schedules', [self::class, 'custom_cron_interval']);

        if (!wp_next_scheduled('auto_ai_news_poster_cron_hook')) {
            wp_schedule_event(time(), 'custom_interval', 'auto_ai_news_poster_cron_hook');
        }
    }


    public static function deactivate()
    {
        wp_clear_scheduled_hook('auto_ai_news_poster_cron_hook');
    }

    public static function reset_cron()
    {
        // Dezactivează cronul existent
        wp_clear_scheduled_hook('auto_ai_news_poster_cron_hook');
        // Reprogramează cronul cu noul interval
        if (!wp_next_scheduled('auto_ai_news_poster_cron_hook')) {
            wp_schedule_event(time(), 'custom_interval', 'auto_ai_news_poster_cron_hook');
        }
    }

    public static function auto_post()
    {
        $settings = get_option('auto_ai_news_poster_settings');
        $generation_mode = $settings['generation_mode'] ?? 'parse_link'; // Mod implicit: parse_link

        if ($settings['mode'] === 'auto') {
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

                        return; // Oprim execuția
                    }

                    // Actualizăm transient-ul pentru verificarea schimbărilor
                    set_transient('auto_ai_news_poster_last_bulk_check', count($bulk_links), 300);
                }

                // Log the auto post execution

                try {
                    // Apelează direct process_article_generation() în loc de get_article_from_sources()
                    Auto_Ai_News_Poster_Api::process_article_generation();
                } catch (Exception $e) {
                    // Log any errors that occur during posting
                }
            } elseif ($generation_mode === 'ai_browsing') {
                try {
                    self::trigger_ai_browsing_generation();
                } catch (Exception $e) {
                }
            }
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

        // Validate hours and minutes
        $hours = isset($options['cron_interval_hours']) ? (int)$options['cron_interval_hours'] : 1;
        $minutes = isset($options['cron_interval_minutes']) ? (int)$options['cron_interval_minutes'] : 0;

        // Ensure valid range for hours and minutes
        if ($hours < 0 || $hours > 24) {
            $hours = 1; // Default to 1 hour if invalid
        }
        if ($minutes < 0 || $minutes >= 60) {
            $minutes = 0; // Default to 0 minutes if invalid
        }

        // Calculate interval in seconds
        $interval = ($hours * 3600) + ($minutes * 60);

        // Ensure the interval is at least 1 minute
        if ($interval < 60) {
            $interval = 60;
        }

        // Add custom interval
        $schedules['custom_interval'] = [
            'interval' => $interval,
            'display' => "Once every $hours hours and $minutes minutes",
        ];

        return $schedules;
    }
}

Auto_Ai_News_Poster_Cron::init();

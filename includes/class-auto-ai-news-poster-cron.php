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

        if ($settings['mode'] === 'auto') {
            // Verificăm dacă opțiunea "Rulează automat doar până la epuizarea listei de linkuri" este activată
            $run_until_bulk_exhausted = $settings['run_until_bulk_exhausted'] === 'yes';

            if ($run_until_bulk_exhausted) {
                // Verificăm dacă lista de linkuri este goală
                $bulk_links = explode("\n", trim($settings['bulk_custom_source_urls'] ?? ''));
                $bulk_links = array_filter($bulk_links, 'trim'); // Eliminăm rândurile goale

                error_log('CRON DEBUG: $run_until_bulk_exhausted:'.$run_until_bulk_exhausted.' count($bulk_links):'. count($bulk_links).' $bulk_links:'. print_r($bulk_links, true));

                if (empty($bulk_links)) {
                    // Lista de linkuri s-a epuizat, oprim cron job-ul și schimbăm modul pe manual
                    error_log('Lista de linkuri personalizate a fost epuizată. Oprirea cron job-ului.');
                    
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
            error_log('Auto post cron triggered.');

            try {
                // Apelează direct process_article_generation() în loc de get_article_from_sources()
                Auto_Ai_News_Poster_Api::process_article_generation();
            } catch (Exception $e) {
                // Log any errors that occur during posting
                error_log('Error during auto post: ' . $e->getMessage());
            }
        }
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

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
            Auto_Ai_News_Poster_Parser::generate_and_publish_article();
        }
    }

    public static function custom_cron_interval($schedules)
    {
        $options = get_option('auto_ai_news_poster_settings');

        // Preia valorile salvate pentru ore și minute
        $hours = isset($options['cron_interval_hours']) ? (int)$options['cron_interval_hours'] : 1;
        $minutes = isset($options['cron_interval_minutes']) ? (int)$options['cron_interval_minutes'] : 0;

        // Calculează intervalul în secunde
        $interval = ($hours * 3600) + ($minutes * 60);

        // Asigură-te că intervalul este cel puțin de un minut
        if ($interval < 60) {
            $interval = 60;
        }

        // Adaugă intervalul personalizat
        $schedules['custom_interval'] = array(
            'interval' => $interval,
            'display' => "O dată la fiecare $hours ore și $minutes minute",
        );

        return $schedules;
    }
}

Auto_Ai_News_Poster_Cron::init();

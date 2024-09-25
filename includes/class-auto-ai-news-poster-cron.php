<?php

class Auto_Ai_News_Poster_Cron {

    public static function init() {
        add_filter('cron_schedules', [self::class, 'add_custom_cron_schedule']);
    }

    public static function add_custom_cron_schedule($schedules) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every Five Minutes'),
        ];
        return $schedules;
    }
}

Auto_Ai_News_Poster_Cron::init();

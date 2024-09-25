<?php

class Auto_Ai_News_Poster_Settings {

    public static function display_settings_page() {
        ?>
        <div class="wrap">
            <h1>Auto AI News Poster Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('auto_ai_news_poster_settings_group');
                do_settings_sections('auto_ai_news_poster_settings_page');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function register_settings() {
        register_setting('auto_ai_news_poster_settings_group', 'auto_ai_news_poster_settings');

        add_settings_section('main_section', 'Main Settings', null, 'auto_ai_news_poster_settings_page');

        add_settings_field(
            'mode',
            'Mod de publicare',
            [self::class, 'mode_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        add_settings_field(
            'categories',
            'Categorii de publicare',
            [self::class, 'categories_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        add_settings_field(
            'news_sources',
            'Surse de știri',
            [self::class, 'news_sources_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
    }

    public static function mode_callback() {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <select name="auto_ai_news_poster_settings[mode]">
            <option value="manual" <?php selected($options['mode'], 'manual'); ?>>Manual</option>
            <option value="auto" <?php selected($options['mode'], 'auto'); ?>>Automat</option>
        </select>
        <?php
    }

    public static function categories_callback() {
        $options = get_option('auto_ai_news_poster_settings');

        // Inițializează $options['categories'] ca array dacă este null
        $selected_categories = isset($options['categories']) ? (array) $options['categories'] : [];

        $categories = get_categories();
        ?>
        <select name="auto_ai_news_poster_settings[categories][]" multiple>
            <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category->term_id); ?>" <?php if (in_array($category->term_id, $selected_categories)) echo 'selected'; ?>>
                    <?php echo esc_html($category->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }


    public static function news_sources_callback() {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <textarea name="auto_ai_news_poster_settings[news_sources]" rows="10" cols="50"><?php echo esc_textarea($options['news_sources']); ?></textarea>
        <p>Adăugați câte un URL de sursă pe fiecare linie.</p>
        <?php
    }
}

add_action('admin_init', [Auto_Ai_News_Poster_Settings::class, 'register_settings']);

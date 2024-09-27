<?php

class Auto_Ai_News_Poster_Settings
{

    public static function init()
    {
        // Înregistrăm setările și meniul
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    // Adăugare meniu în zona articolelor din admin
    public static function add_menu()
    {
        add_submenu_page(
            'edit.php',
            'Auto AI News Poster Settings',
            'Auto AI News Poster',
            'manage_options',
            'auto-ai-news-poster',
            [self::class, 'settings_page']
        );
    }

    // Afișare pagina de setări
    public static function settings_page()
    {
        self::display_settings_page();
    }

    public static function display_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>Auto AI News Poster Settings</h1>
            <form method="post" action="options.php" class="form-horizontal">
                <?php
                settings_fields('auto_ai_news_poster_settings_group');
                do_settings_sections('auto_ai_news_poster_settings_page');
                submit_button('Salvează setările', 'primary', '', true, ['class' => 'btn btn-primary']);
                ?>
            </form>
        </div>
        <?php
    }

    public static function register_settings()
    {
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
        add_settings_field(
            'chatgpt_api_key',
            'Cheia API ChatGPT',
            [self::class, 'chatgpt_api_key_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        add_settings_field(
            'cron_interval',
            'Intervalul pentru cron job',
            [self::class, 'cron_interval_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
    }

    public static function mode_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <div class="form-group">
            <label for="mode" class="control-label">Mod de publicare</label>
            <select name="auto_ai_news_poster_settings[mode]" class="form-control" id="mode">
                <option value="manual" <?php selected($options['mode'], 'manual'); ?>>Manual</option>
                <option value="auto" <?php selected($options['mode'], 'auto'); ?>>Automat</option>
            </select>
        </div>
        <?php
    }

    public static function categories_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $selected_categories = isset($options['categories']) ? (array)$options['categories'] : [];

        $categories = get_categories(['hide_empty' => false]);
        ?>
        <div class="form-group">
            <label for="categories" class="control-label">Categorii de publicare</label>
            <select name="auto_ai_news_poster_settings[categories][]" multiple class="form-control" id="categories">
                <?php foreach ($categories as $category) : ?>
                    <option value="<?php echo esc_attr($category->term_id); ?>" <?php if (in_array($category->term_id, $selected_categories)) echo 'selected'; ?>>
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    public static function news_sources_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <div class="form-group">
            <label for="news_sources" class="control-label">Surse de știri</label>
            <textarea name="auto_ai_news_poster_settings[news_sources]" class="form-control" id="news_sources"
                      rows="6"><?php echo esc_textarea($options['news_sources']); ?></textarea>
            <small class="form-text text-muted">Adăugați câte un URL de sursă pe fiecare linie.</small>
        </div>
        <?php
    }

    public static function chatgpt_api_key_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <div class="form-group">
            <label for="chatgpt_api_key" class="control-label">Cheia API ChatGPT</label>
            <input type="text" name="auto_ai_news_poster_settings[chatgpt_api_key]"
                   value="<?php echo esc_attr($options['chatgpt_api_key']); ?>" class="form-control"
                   id="chatgpt_api_key">
            <span class="info-icon dashicons dashicons-info"
                  title="Pentru a obține cheia API OpenAI, accesați https://beta.openai.com/signup/. După ce v-ați înregistrat și ați confirmat contul, accesați pagina de API Keys și generați o cheie nouă. Această cheie trebuie introdusă aici pentru a permite generarea automată a articolelor."></span>
            <small class="form-text text-muted">Introduceți cheia API pentru ChatGPT.</small>
        </div>
        <?php
    }

    public static function cron_interval_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $hours = isset($options['cron_interval_hours']) ? $options['cron_interval_hours'] : 1;
        $minutes = isset($options['cron_interval_minutes']) ? $options['cron_interval_minutes'] : 0;
        ?>
        <div class="form-group">
            <label for="cron_interval_hours" class="control-label">Ore</label>
            <select name="auto_ai_news_poster_settings[cron_interval_hours]" class="form-control">
                <?php for ($i = 0; $i <= 23; $i++) : ?>
                    <option value="<?php echo $i; ?>" <?php selected($hours, $i); ?>>
                        <?php echo $i; ?> ore
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="cron_interval_minutes" class="control-label">Minute</label>
            <select name="auto_ai_news_poster_settings[cron_interval_minutes]" class="form-control">
                <?php for ($i = 0; $i <= 59; $i++) : ?>
                    <option value="<?php echo $i; ?>" <?php selected($minutes, $i); ?>>
                        <?php echo $i; ?> minute
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <?php
    }
}

Auto_Ai_News_Poster_Settings::init();

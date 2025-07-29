<?php

class Auto_Ai_News_Poster_Settings
{

    public static function init()
    {
        // Înregistrăm setările și meniul
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        
                // Setare inițială pentru indexul categoriei curente (dacă nu există deja)
        if (false === get_option('auto_ai_news_poster_current_category_index')) {
            add_option('auto_ai_news_poster_current_category_index', 0);
        }

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

        // Camp pentru selectarea modului de publicare
        add_settings_field(
            'mode',
            'Mod de publicare',
            [self::class, 'mode_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        
        // Camp pentru selectarea statusului de publicare
        add_settings_field(
            'status',
            'Satus publicare',
            [self::class, 'post_status_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru selectarea categoriilor de publicare
        add_settings_field(
            'categories',
            'Categorii de publicare',
            [self::class, 'specific_search_category_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        
        // In modul automat, se poate seta rularea automata a categoriilor
        add_settings_field(
            'auto_rotate_categories',
            'Rulează automat categoriile',
            [self::class, 'auto_rotate_categories_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru sursele de stiri
        add_settings_field(
            'news_sources',
            'Surse de știri',
            [self::class, 'news_sources_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru cheia API OpenAI
        add_settings_field(
            'chatgpt_api_key',
            'Cheia API ChatGPT',
            [self::class, 'chatgpt_api_key_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru setarea intervalului cron
        add_settings_field(
            'cron_interval',
            'Intervalul pentru cron job',
            [self::class, 'cron_interval_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru numele autorului de articole generate
        add_settings_field(
            'author_name',
            'Nume autor articole generate',
            [self::class, 'author_name_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru instructiuni AI (textarea)
        add_settings_field(
            'default_ai_instructions',
            'Instrucțiuni AI pentru generarea articolelor',
            [self::class, 'ai_instructions_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru numarul maxim de caractere al rezumatului
        add_settings_field(
            'max_summary_length',
            'Numărul maxim de caractere al rezumatului',
            [self::class, 'max_summary_length_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        
        // În funcția register_settings()
        add_settings_field(
            'article_length_option',
            'Selectează dimensiunea articolului',
            [self::class, 'article_length_option_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        
        add_settings_field(
            'min_length',
            'Valoare minimă',
            [self::class, 'min_length_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        
        add_settings_field(
            'max_length',
            'Valoare maximă',
            [self::class, 'max_length_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

         add_settings_field(
            'generate_image',
            'Generare automată imagine',
            [self::class, 'generate_image_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        
        // Camp pentru selectarea modului de imagine (externă/importată)
        add_settings_field(
            'use_external_images',
            'Mod imagini',
            [self::class, 'use_external_images_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        
        // Înregistrăm un nou câmp în setările pluginului pentru lista de linkuri sursă
        add_settings_field(
            'bulk_custom_source_urls',
            'Lista de linkuri sursă personalizate',
            [self::class, 'bulk_custom_source_urls_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );
        
        // În funcția register_settings()
        add_settings_field(
            'run_until_bulk_exhausted',
            'Rulează automat doar până la epuizarea listei de linkuri',
            [self::class, 'run_until_bulk_exhausted_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );


    }

    // Callback pentru campul Mod de publicare
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
    
        // Callback pentru campul Mod de publicare status
    public static function post_status_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <div class="form-group">
            <label for="status" class="control-label">Status publicare articol</label>
            <select name="auto_ai_news_poster_settings[status]" class="form-control" id="status">
                <option value="draft" <?php selected($options['status'], 'draft'); ?>>Draft</option>
                <option value="publish" <?php selected($options['status'], 'publish'); ?>>Publicat</option>
            </select>
        </div>
        <?php
    }

    // Callback pentru selectarea categoriei specifice pentru căutare
    public static function specific_search_category_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $selected_category = $options['specific_search_category'] ?? '';
    
        $categories = get_categories(['hide_empty' => false]);
        ?>
        <div class="form-group">
            <label for="specific_search_category" class="control-label">Categorie specifică pentru căutare</label>
            <select name="auto_ai_news_poster_settings[specific_search_category]" class="form-control" id="specific_search_category">
                <option value="">Selectează o categorie</option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($selected_category, $category->term_id); ?>>
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
    
    
    // Callback pentru opțiunea de rulare automată a categoriilor
    public static function auto_rotate_categories_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <label>
            <input type="checkbox" name="auto_ai_news_poster_settings[auto_rotate_categories]" value="yes" <?php checked($options['auto_rotate_categories'], 'yes'); ?> />
            Da, rulează automat categoriile în ordine
        </label>
        <?php
    }


    // Callback pentru sursele de stiri
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

    // Callback pentru cheia API
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
                  title="Pentru a obține cheia API OpenAI, accesați https://platform.openai.com/settings/organization/api-keys. După ce v-ați înregistrat și ați confirmat contul, accesați pagina de API Keys și generați o cheie nouă."></span>
            <small class="form-text text-muted">Introduceți cheia API pentru ChatGPT.</small>
        </div>
        <?php
    }

    // Callback pentru setarea intervalului cron
    public static function cron_interval_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $hours = $options['cron_interval_hours'] ?? 1;
        $minutes = $options['cron_interval_minutes'] ?? 0;
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

    // Callback pentru selectarea autorului
    public static function author_name_callback() {
        $options = get_option('auto_ai_news_poster_settings');
        $selected_author = $options['author_name'] ?? get_current_user_id();
    
        // Obținem lista de utilizatori cu rolul 'Author' sau 'Administrator'
        $users = get_users([
            'role__in' => ['Author', 'Administrator'],
            'orderby' => 'display_name'
        ]);
        ?>
        <div class="form-group">
            <label for="author_name" class="control-label">Autor articole generate</label>
            <select name="auto_ai_news_poster_settings[author_name]" class="form-control" id="author_name">
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($selected_author, $user->ID); ?>>
                        <?php echo esc_html($user->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }


    // Callback pentru instrucțiunile AI (textarea)
    public static function ai_instructions_callback() {
        $options = get_option('auto_ai_news_poster_settings');
        $default_instructions = $options['default_ai_instructions'] ?? "Creează un articol unic pe baza următoarelor surse de știri, respectă structura titlu, etichete și conținut. Sugerează imagini și include rezumatul.";

        ?>
        <div class="form-group">
            <textarea name="auto_ai_news_poster_settings[default_ai_instructions]" class="form-control" rows="6"
                      placeholder="Introdu instrucțiunile implicite pentru AI"><?php echo esc_textarea($default_instructions); ?></textarea>
        </div>
        <?php
    }

    // Callback pentru numărul maxim de caractere pentru rezumat
    public static function max_summary_length_callback() {
        $options = get_option('auto_ai_news_poster_settings');
        $max_summary_length = $options['max_summary_length'] ?? 100;
        ?>
        <div class="form-group">
            <input type="number" name="auto_ai_news_poster_settings[max_summary_length]"
                   value="<?php echo esc_attr($max_summary_length); ?>" class="form-control" placeholder="Maxim 100 caractere">
        </div>
        <?php
    }
    
    
    // Select pentru dimensiunea articolului
    public static function article_length_option_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $selected_option = $options['article_length_option'] ?? 'same_as_source';
    
        ?>
        <select name="auto_ai_news_poster_settings[article_length_option]" class="form-control">
            <option value="same_as_source" <?php selected($selected_option, 'same_as_source'); ?>>Aceiași dimensiune cu articolul preluat</option>
            <option value="set_limits" <?php selected($selected_option, 'set_limits'); ?>>Setează limite</option>
        </select>
        <?php
    }
    
    // Input pentru valoarea minimă
    public static function min_length_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $min_length = $options['min_length'] ?? '';
    
        ?>
        <input type="number" name="auto_ai_news_poster_settings[min_length]" class="form-control"
               value="<?php echo esc_attr($min_length); ?>" placeholder="Valoare minimă">
        <?php
    }
    
    // Input pentru valoarea maximă
    public static function max_length_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $max_length = $options['max_length'] ?? '';
    
        ?>
        <input type="number" name="auto_ai_news_poster_settings[max_length]" class="form-control"
               value="<?php echo esc_attr($max_length); ?>" placeholder="Valoare maximă">
        <?php
    }


    // Callback pentru selectarea modului de imagine (externă/importată)
    public static function use_external_images_callback() {
        $options = get_option('auto_ai_news_poster_settings');
        $use_external_images = $options['use_external_images'] ?? 'external';
        ?>
        <div class="form-group">
            <label for="use_external_images" class="control-label">Folosire imagini:</label>
            <select name="auto_ai_news_poster_settings[use_external_images]" class="form-control" id="use_external_images">
                <option value="external" <?php selected($use_external_images, 'external'); ?>>Folosește imagini externe</option>
                <option value="import" <?php selected($use_external_images, 'import'); ?>>Importă imagini în WordPress</option>
            </select>
        </div>
        <?php
    }
    
    
    // Callback pentru opțiunea de generare automată a imaginii
    public static function generate_image_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <label>
            <input type="checkbox" name="auto_ai_news_poster_settings[generate_image]" value="yes" <?php checked($options['generate_image'], 'yes'); ?> />
            Da, generează automat imaginea
        </label>
        <?php
    }
    
    public static function bulk_custom_source_urls_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $bulk_links = $options['bulk_custom_source_urls'] ?? '';
        ?>
        <textarea name="auto_ai_news_poster_settings[bulk_custom_source_urls]" class="widefat" rows="6" placeholder="Introduceți câte un link pe fiecare rând"><?php echo esc_textarea($bulk_links); ?></textarea>
        <small class="form-text text-muted">Introduceți o listă de linkuri sursă. Acestea vor fi folosite automat sau manual pentru generarea articolelor.</small>
        <?php
    }
    
    public static function run_until_bulk_exhausted_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $is_auto_mode = isset($options['mode']) && $options['mode'] === 'auto'; // Verificăm dacă modul este "auto"
        $run_until_bulk_exhausted = $options['run_until_bulk_exhausted'] ?? ''; // Valoare implicită pentru cheie
        ?>
        <label>
            <input type="checkbox" name="auto_ai_news_poster_settings[run_until_bulk_exhausted]" 
                   value="yes" <?php checked($run_until_bulk_exhausted, 'yes'); ?>
                   <?php echo $is_auto_mode ? '' : 'disabled'; ?> />
            Da, rulează doar până la epuizarea listei de linkuri
        </label>
        <small class="form-text text-muted">Această opțiune este disponibilă doar în modul automat.</small>
        <script>
            // Script JavaScript pentru a dezactiva checkbox-ul dacă modul este schimbat
            document.getElementById('mode').addEventListener('change', function () {
                const checkbox = document.querySelector('input[name="auto_ai_news_poster_settings[run_until_bulk_exhausted]"]');
                checkbox.disabled = this.value !== 'auto';
            });
        </script>
        <?php
    }



}

Auto_Ai_News_Poster_Settings::init();
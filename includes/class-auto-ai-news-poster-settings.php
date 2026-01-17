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

        // Handler AJAX pentru actualizarea listei de modele
        add_action('wp_ajax_refresh_openai_models', [self::class, 'ajax_refresh_openai_models']);
        add_action('wp_ajax_refresh_gemini_models', [self::class, 'ajax_refresh_gemini_models']);
        add_action('wp_ajax_refresh_deepseek_models', [self::class, 'ajax_refresh_deepseek_models']);

        // Site Analyzer AJAX
        add_action('wp_ajax_auto_ai_scan_site', [self::class, 'ajax_scan_site']);
        add_action('wp_ajax_auto_ai_import_selected', [self::class, 'ajax_import_selected']);
    }



    // Adăugare meniu în zona articolelor din admin
    public static function add_menu()
    {
        add_menu_page(
            'Auto AI News Poster Settings', // Titlul paginii
            'Auto AI News Poster', // Titlul din meniu
            'manage_options', // Capacitatea necesară
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE, // Slug-ul meniului
            [self::class, 'settings_page_html'], // Funcția callback
            'dashicons-robot', // Iconiță
            2 // Poziția
        );
    }

    // Afișare pagina de setări
    public static function settings_page_html()
    {
        self::display_settings_page();
    }

    public static function display_settings_page()
    {
        ?>
        <div class="auto-ai-news-poster-admin">
            <div class="wrap">
                <!-- Header modern -->
                <div class="auto-ai-news-poster-header">
                    <div class="header-content">
                        <div class="header-text">
                            <h1>🤖 Auto AI News Poster</h1>
                            <p>Configurează-ți plugin-ul pentru publicarea automată de articole AI</p>
                        </div>
                        <div class="header-actions">
                            <button type="submit" form="auto-ai-news-poster-settings-form" class="btn btn-primary btn-save-header">
                                💾 Salvează setările
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Formular modern -->
                <div class="auto-ai-news-poster-form">
                    <form method="post" action="options.php" id="auto-ai-news-poster-settings-form">
                        <?php
                        settings_fields(AUTO_AI_NEWS_POSTER_SETTINGS_GROUP);
        do_settings_sections(AUTO_AI_NEWS_POSTER_SETTINGS_PAGE);
        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public static function enqueue_admin_scripts($hook_suffix)
    {
        // Verificăm dacă suntem pe pagina de setări
        if ($hook_suffix != 'toplevel_page_' . AUTO_AI_NEWS_POSTER_SETTINGS_PAGE) {
            return;
        }

        // Adăugăm Google Fonts (Inter)
        wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null);

        // Bootstrap (opțional, dacă e necesar)
        // wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');

        // FontAwesome
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css');

        // Select2
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

        // Stilurile personalizate ale plugin-ului
        wp_enqueue_style('auto-ai-news-poster-admin-style', plugin_dir_url(__FILE__) . 'css/auto-ai-news-poster.css', [], '1.17.0');

        // Scripturile personalizate ale plugin-ului
        wp_enqueue_script('auto-ai-news-poster-admin-script', plugin_dir_url(__FILE__) . 'js/auto-ai-news-poster-settings.js', ['jquery', 'select2-js'], '1.17.0', true);

        // Localizare script pentru AJAX
        wp_localize_script('auto-ai-news-poster-admin-script', 'auto_ai_news_poster_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'force_refresh_now_nonce' => wp_create_nonce('force_refresh_now_nonce'),
            'check_settings_nonce' => wp_create_nonce('auto_ai_news_poster_check_settings'),
            'clear_transient_nonce' => wp_create_nonce('clear_transient_nonce'),
            'refresh_models_nonce' => wp_create_nonce('refresh_models_nonce'),
            'refresh_gemini_nonce' => wp_create_nonce('refresh_gemini_models_nonce'),
            'refresh_deepseek_nonce' => wp_create_nonce('refresh_deepseek_models_nonce'),
        ]);
    }

    public static function register_settings()
    {
        register_setting(AUTO_AI_NEWS_POSTER_SETTINGS_GROUP, AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, [
            'sanitize_callback' => [self::class, 'sanitize_checkbox_settings']
        ]);

        add_settings_section('auto_ai_news_poster_main_section', 'Setări Principale', [self::class, 'section_callback'], AUTO_AI_NEWS_POSTER_SETTINGS_PAGE);

        // Camp pentru selectarea modului de generare (AI Browsing vs. Parsare Link)
        add_settings_field(
            'generation_mode',
            'Mod de generare',
            [self::class, 'generation_mode_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // Camp pentru selectarea modului de publicare
        add_settings_field(
            'mode',
            'Mod de publicare',
            [self::class, 'mode_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // Camp pentru selectarea categoriilor de publicare
        add_settings_field(
            'categories',
            'Categorii de publicare',
            [self::class, 'specific_search_category_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // In modul automat, se poate seta rularea automata a categoriilor
        add_settings_field(
            'auto_rotate_categories',
            'Rulează automat categoriile',
            [self::class, 'auto_rotate_categories_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // Camp pentru sursele de stiri
        add_settings_field(
            'news_sources',
            'Surse de știri',
            [self::class, 'news_sources_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // Configurare API AI (OpenAI + Gemini + selector provider)
        add_settings_field(
            'ai_providers',
            'Configurare API AI',
            [self::class, 'chatgpt_api_key_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );


        // Camp pentru setarea intervalului cron
        add_settings_field(
            'cron_interval',
            'Intervalul pentru cron job',
            [self::class, 'cron_interval_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // Camp pentru numele autorului de articole generate
        add_settings_field(
            'author_name',
            'Nume autor articole generate',
            [self::class, 'author_name_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // Camp pentru instructiuni AI (textarea) - Mod Parsare Link
        add_settings_field(
            'parse_link_ai_instructions',
            'Instrucțiuni AI (Parsare Link)',
            [self::class, 'parse_link_ai_instructions_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // 1. Scanning Sources (New Dynamic Table)
        add_settings_field(
            'scanning_source_urls',
            'Surse principale de scanat',
            [self::class, 'scanning_source_urls_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // 2. Site Analyzer Tool
        add_settings_field(
            'site_analyzer_ui',
            'Site Analyzer & Cleaner',
            [self::class, 'site_analyzer_ui_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // 3. Article Queue (Restored Textarea)
        add_settings_field(
            'bulk_custom_source_urls',
            'Lista de Linkuri pentru Parsare (Queue)',
            [self::class, 'bulk_custom_source_urls_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // 4. Tasks Placeholder
        add_settings_field(
            'tasks_placeholder',
            'Gestionare Taskuri',
            [self::class, 'tasks_management_placeholder_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // Camp pentru opțiunea de oprire la epuizarea listei
        add_settings_field(
            'run_until_bulk_exhausted',
            'Oprește la epuizare',
            [self::class, 'run_until_bulk_exhausted_callback'],
            AUTO_AI_NEWS_POSTER_SETTINGS_PAGE,
            'auto_ai_news_poster_main_section'
        );

        // Camp pentru instructiuni AI (textarea) - Mod AI Browsing
        add_settings_field(
            'ai_browsing_instructions',
            'Instrucțiuni AI (AI Browsing)',
            [self::class, 'ai_browsing_instructions_callback'],
            'auto_ai_news_poster_settings_page',
            'auto_ai_news_poster_main_section'
        );

        // Camp pentru controlul generării etichetelor
        add_settings_field(
            'generate_tags',
            'Generează etichete',
            [self::class, 'generate_tags_callback'],
            'auto_ai_news_poster_settings_page',
            'auto_ai_news_poster_main_section'
        );

        // În funcția register_settings()
        add_settings_field(
            'article_length_option',
            'Selectează dimensiunea articolului',
            [self::class, 'article_length_option_callback'],
            'auto_ai_news_poster_settings_page',
            'auto_ai_news_poster_main_section'
        );

        add_settings_field(
            'image_configuration',
            'Configurare Imagini',
            [self::class, 'image_configuration_callback'],
            'auto_ai_news_poster_settings_page',
            'auto_ai_news_poster_main_section'
        );


    }

    // Callback unificat pentru Configurare Imagini
    public static function image_configuration_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        // Retrieve values
        $use_external_images = $options['use_external_images'] ?? 'external';
        $generate_image = $options['generate_image'] ?? 'no';
        $extract_image_from_source = $options['extract_image_from_source'] ?? 'yes'; // Default: enabled
        $position = $options['source_photo_position'] ?? 'before';
        ?>
        <div class="settings-group settings-group-parse_link settings-group-ai_browsing">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">🖼️</div>
                <h3 class="settings-card-title">Configurare Imagini</h3>
            </div>
            <div class="settings-card-content">
                <!-- 1. Mod Imagini -->
                <div class="form-group" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <label for="use_external_images" class="control-label">Folosire imagini:</label>
                    <select name="auto_ai_news_poster_settings[use_external_images]" class="form-control" id="use_external_images">
                        <option value="external" <?php selected($use_external_images, 'external'); ?>>Folosește imagini externe</option>
                        <option value="import" <?php selected($use_external_images, 'import'); ?>>Importă imagini în WordPress</option>
                    </select>
                </div>

                <!-- 2. Extragere Imagine din Sursă -->
                <div class="form-group" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <div class="checkbox-modern">
                        <input type="checkbox" name="auto_ai_news_poster_settings[extract_image_from_source]" value="yes" <?php checked($extract_image_from_source, 'yes'); ?> />
                        <label>Extrage automat imaginea din articolul sursă (Parse Link, AI Browsing, Taskuri)</label>
                        <p class="form-text text-muted" style="margin-top: 5px; font-size: 12px;">Dacă este activată, plugin-ul va încerca să extragă imaginea principală din sursa externă înainte de a genera una cu AI.</p>
                    </div>
                </div>

                <!-- 3. Generare Automată -->
                <div class="form-group" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <div class="checkbox-modern">
                        <input type="checkbox" name="auto_ai_news_poster_settings[generate_image]" value="yes" <?php checked($generate_image, 'yes'); ?> />
                        <label>Da, generează automat imaginea cu AI (dacă nu există)</label>
                        <p class="form-text text-muted" style="margin-top: 5px; font-size: 12px;">Dacă nu se găsește imagine în sursă sau extragerea este dezactivată, se va genera o imagine cu AI.</p>
                    </div>
                </div>

                <!-- 4. Poziție Sursă Foto -->
                <div class="form-group">
                    <label class="control-label">Poziție afișare „Sursa foto”</label>
                    <div class="mode-switch">
                        <input type="radio" id="source_pos_before" name="auto_ai_news_poster_settings[source_photo_position]" value="before" <?php checked($position, 'before'); ?>>
                        <label for="source_pos_before">Înainte de articol</label>

                        <input type="radio" id="source_pos_after" name="auto_ai_news_poster_settings[source_photo_position]" value="after" <?php checked($position, 'after'); ?>>
                        <label for="source_pos_after">După articol</label>
                    </div>
                </div>
            </div>
        </div>
        </div>
        <?php
    }

    // Callback pentru noul camp "Mod de generare"
    public static function generation_mode_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $generation_mode = $options['generation_mode'] ?? 'parse_link';
        ?>
        <div class="settings-card no-padding" style="box-shadow: none; border: none; background: transparent;">
            <div class="mode-tabs-container">
                <ul class="mode-tabs">
                    <li class="mode-tab <?php echo ($generation_mode === 'parse_link') ? 'active' : ''; ?>" data-mode="parse_link">
                        <span class="tab-icon">🔗</span> Parsare Link
                    </li>
                    <li class="mode-tab <?php echo ($generation_mode === 'ai_browsing') ? 'active' : ''; ?>" data-mode="ai_browsing">
                        <span class="tab-icon">🌐</span> Generare AI
                    </li>
                    <li class="mode-tab <?php echo ($generation_mode === 'tasks') ? 'active' : ''; ?>" data-mode="tasks">
                        <span class="tab-icon">📋</span> Taskuri
                    </li>
                </ul>
            </div>
            <input type="hidden" id="generation_mode_hidden" name="auto_ai_news_poster_settings[generation_mode]" value="<?php echo esc_attr($generation_mode); ?>">
            
            <div class="settings-card-content" style="padding: 10px 0 20px 0;">
                <div id="tab-description-parse_link" class="tab-description" style="display: <?php echo ($generation_mode === 'parse_link') ? 'block' : 'none'; ?>;">
                    <p class="form-text text-muted"><b>Parsare Link:</b> Plugin-ul va prelua conținut de la un link specific din lista de surse sau din coada de parsare.</p>
                </div>
                <div id="tab-description-ai_browsing" class="tab-description" style="display: <?php echo ($generation_mode === 'ai_browsing') ? 'block' : 'none'; ?>;">
                    <p class="form-text text-muted"><b>Generare AI:</b> AI-ul va căuta o știre nouă pe internet, folosind sursele de informare și categoria specificată.</p>
                </div>
                <div id="tab-description-tasks" class="tab-description" style="display: <?php echo ($generation_mode === 'tasks') ? 'block' : 'none'; ?>;">
                    <p class="form-text text-muted"><b>Taskuri:</b> Gestionarea și monitorizarea proceselor de fundal și a sarcinilor programate.</p>
                </div>
            </div>
        </div>
    <?php
    }

    // Callback pentru placeholder-ul de Taskuri
    public static function tasks_management_placeholder_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $generation_mode = $options['generation_mode'] ?? 'parse_link';
        $tasks_config = $options['tasks_config'] ?? [];
        $task_lists = $options['task_lists'] ?? [];

        // Helper data for dropdowns
        $users = get_users(['fields' => ['ID', 'display_name']]);
        $categories = get_categories(['hide_empty' => false]);

        // Current Tasks AI Config
        $current_provider = $tasks_config['api_provider'] ?? 'openai';
        $openai_key = $tasks_config['chatgpt_api_key'] ?? '';
        $openai_model = $tasks_config['ai_model'] ?? 'gpt-4o-mini';
        $gemini_key = $tasks_config['gemini_api_key'] ?? '';
        $gemini_model = $tasks_config['gemini_model'] ?? '';
        $deepseek_key = $tasks_config['deepseek_api_key'] ?? '';
        $deepseek_model = $tasks_config['deepseek_model'] ?? '';

        // Cron & Control
        $cron_hours = $tasks_config['cron_interval_hours'] ?? 0;
        $cron_minutes = $tasks_config['cron_interval_minutes'] ?? 30;
        $gen_image = $tasks_config['generate_image'] ?? 'no';
        $gen_tags = $tasks_config['generate_tags'] ?? 'yes';

        ?>
    <div class="settings-group settings-group-tasks <?php echo ($generation_mode === 'tasks') ? 'active' : ''; ?>">
        
        <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
            <?php
                    // Only global AI API configuration - all other settings are now per-task-list
                    self::render_ai_config_component('tasks');
        ?>
        </div>

        <!-- 3. Dynamic Task Lists -->
        <div class="settings-card">
            <div class="settings-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center;">
                    <div class="settings-card-icon">📋</div>
                    <h3 class="settings-card-title">Liste de Taskuri (Cozi de Titluri)</h3>
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="add-task-list-btn">
                    <i class="fas fa-plus"></i> Adaugă Listă Nouă
                </button>
            </div>
            <div class="settings-card-content">
                <div id="task-lists-container">
                    <?php
                if (!empty($task_lists)) :
                    foreach ($task_lists as $index => $list) :
                        $list_id = $list['id'] ?? uniqid();
                        ?>
                            <div class="task-list-item settings-card" style="background: #fcfcfc; border: 1px solid #eee; margin-bottom: 20px; box-shadow: none;" data-id="<?php echo esc_attr($list_id); ?>">
                                <div class="settings-card-header" style="background: rgba(0,0,0,0.02); padding: 10px 15px;">
                                    <input type="text" name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][name]" value="<?php echo esc_attr($list['name'] ?? 'Listă fără nume'); ?>" class="form-control" style="font-weight: 600; border:none; background:transparent; padding:0;" placeholder="Nume Listă (ex: Știri Sport)">
                                    <input type="hidden" name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][id]" value="<?php echo esc_attr($list_id); ?>">
                                    <button type="button" class="btn btn-link text-danger remove-task-list" style="padding:0;"><i class="fas fa-trash"></i></button>
                                </div>
                                <div class="settings-card-content" style="padding: 15px;">
                                    <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label class="control-label">Lista de Titluri (unul pe rând)</label>
                                            <textarea name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][titles]" class="form-control" rows="8" placeholder="Titlu 1&#10;Titlu 2&#10;Titlu 3..."><?php echo esc_textarea($list['titles'] ?? ''); ?></textarea>
                                        </div>
                                        <div>
                                            <div class="form-group">
                                                <label class="control-label">Autor Articole</label>
                                                <select name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][author]" class="form-control">
                                                    <?php foreach ($users as $user) : ?>
                                                        <option value="<?php echo $user->ID; ?>" <?php selected($list['author'] ?? '', $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label class="control-label">Categorie Destinație</label>
                                                <select name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][category]" class="form-control">
                                                    <?php foreach ($categories as $cat) : ?>
                                                        <option value="<?php echo $cat->term_id; ?>" <?php selected($list['category'] ?? '', $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <!-- Individual Task List Settings -->
                                            <div class="form-group" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                                                <h4 style="margin-bottom: 15px; font-size: 13px; font-weight: 600; color: #555;">⚙️ Setări Individuale</h4>
                                                
                                                <!-- Cron Interval -->
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label class="control-label" style="font-size: 12px;">Ore Cron</label>
                                                        <select name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][cron_interval_hours]" class="form-control form-control-sm">
                                                            <?php for ($i = 0; $i <= 23; $i++) : ?>
                                                                <option value="<?php echo $i; ?>" <?php selected($list['cron_interval_hours'] ?? 0, $i); ?>><?php echo $i; ?> ore</option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label class="control-label" style="font-size: 12px;">Minute Cron</label>
                                                        <select name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][cron_interval_minutes]" class="form-control form-control-sm">
                                                            <?php foreach ([0, 1, 2, 5, 10, 15, 20, 30, 45] as $m) : ?>
                                                                <option value="<?php echo $m; ?>" <?php selected($list['cron_interval_minutes'] ?? 30, $m); ?>><?php echo $m; ?> min</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <!-- Article Length -->
                                                <div class="form-group" style="margin-bottom: 12px;">
                                                    <label class="control-label" style="font-size: 12px;">Dimensiune Articol</label>
                                                    <select name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][article_length_option]" class="form-control form-control-sm">
                                                        <option value="same_as_source" <?php selected($list['article_length_option'] ?? 'same_as_source', 'same_as_source'); ?>>Dimensiune variabilă (AI)</option>
                                                        <option value="set_limits" <?php selected($list['article_length_option'] ?? 'same_as_source', 'set_limits'); ?>>Setează limite (cuvinte)</option>
                                                    </select>
                                                </div>
                                                
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label class="control-label" style="font-size: 12px;">Min Cuvinte</label>
                                                        <input type="number" name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][min_length]" class="form-control form-control-sm" value="<?php echo esc_attr($list['min_length'] ?? ''); ?>" placeholder="ex: 500">
                                                    </div>
                                                    <div class="form-group" style="margin-bottom: 0;">
                                                        <label class="control-label" style="font-size: 12px;">Max Cuvinte</label>
                                                        <input type="number" name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][max_length]" class="form-control form-control-sm" value="<?php echo esc_attr($list['max_length'] ?? ''); ?>" placeholder="ex: 1000">
                                                    </div>
                                                </div>
                                                
                                                <!-- Tags & Images -->
                                                <div class="form-group" style="margin-bottom: 12px;">
                                                    <div class="checkbox-modern" style="margin-bottom: 8px;">
                                                        <input type="checkbox" id="task_<?php echo $index; ?>_tags" name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][generate_tags]" value="yes" <?php checked($list['generate_tags'] ?? 'yes', 'yes'); ?> />
                                                        <label for="task_<?php echo $index; ?>_tags" style="font-size: 12px;">Generează etichete automate</label>
                                                    </div>
                                                    <div class="checkbox-modern" style="margin-bottom: 8px;">
                                                        <input type="checkbox" id="task_<?php echo $index; ?>_extract_img" name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][extract_image_from_source]" value="yes" <?php checked($list['extract_image_from_source'] ?? 'yes', 'yes'); ?> />
                                                        <label for="task_<?php echo $index; ?>_extract_img" style="font-size: 12px;">Extrage automat imaginea din articolul sursă (Parse Link, AI Browsing, Taskuri)</label>
                                                    </div>
                                                    <div class="checkbox-modern">
                                                        <input type="checkbox" id="task_<?php echo $index; ?>_images" name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][generate_image]" value="yes" <?php checked($list['generate_image'] ?? 'no', 'yes'); ?> />
                                                        <label for="task_<?php echo $index; ?>_images" style="font-size: 12px;">Da, generează automat imaginea cu AI (dacă nu există)</label>
                                                    </div>
                                                </div>
                                                
                                                <!-- Publication Status -->
                                                <div class="form-group" style="margin-bottom: 12px;">
                                                    <label class="control-label" style="font-size: 12px;">Status Publicare</label>
                                                    <select name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][post_status]" class="form-control form-control-sm">
                                                        <option value="draft" <?php selected($list['post_status'] ?? 'draft', 'draft'); ?>>Draft</option>
                                                        <option value="publish" <?php selected($list['post_status'] ?? 'draft', 'publish'); ?>>Publicat</option>
                                                    </select>
                                                </div>
                                                
                                                <!-- AI Instructions -->
                                                <div class="form-group" style="margin-bottom: 0;">
                                                    <label class="control-label" style="font-size: 12px;">Instrucțiuni AI specifice</label>
                                                    <textarea name="auto_ai_news_poster_settings[task_lists][<?php echo $index; ?>][ai_instructions]" class="form-control form-control-sm" rows="2" placeholder="Instrucțiuni opționale pentru AI..."><?php echo esc_textarea($list['ai_instructions'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                            
                                            <div style="margin-top: 20px;">
                                                <button type="button" class="btn btn-primary btn-block run-task-list-now" data-id="<?php echo esc_attr($list_id); ?>">
                                                    <i class="fas fa-magic"></i> Generează acum
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach;
                else : ?>
                        <div class="no-task-lists alert alert-light" style="text-align: center; border: 1px dashed #ccc; padding: 40px;">
                            Nu ai nicio listă de taskuri creată. Apasă butonul de mai sus pentru a începe.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Template pentru o nouă listă de task-uri (folosit de JS) -->
    <script type="text/template" id="task-list-template">
        <div class="task-list-item settings-card" style="background: #fcfcfc; border: 1px solid #eee; margin-bottom: 20px; box-shadow: none;" data-id="{{ID}}">
            <div class="settings-card-header" style="background: rgba(0,0,0,0.02); padding: 10px 15px;">
                <input type="text" name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][name]" value="Listă Nouă" class="form-control" style="font-weight: 600; border:none; background:transparent; padding:0;" placeholder="Nume Listă (ex: Știri Sport)">
                <input type="hidden" name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][id]" value="{{ID}}">
                <button type="button" class="btn btn-link text-danger remove-task-list" style="padding:0;"><i class="fas fa-trash"></i></button>
            </div>
            <div class="settings-card-content" style="padding: 15px;">
                <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="control-label">Lista de Titluri (unul pe rând)</label>
                        <textarea name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][titles]" class="form-control" rows="8" placeholder="Titlu 1&#10;Titlu 2&#10;Titlu 3..."></textarea>
                    </div>
                    <div>
                        <div class="form-group">
                            <label class="control-label">Autor Articole</label>
                            <select name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][author]" class="form-control">
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Categorie Destinație</label>
                            <select name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][category]" class="form-control">
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Individual Task List Settings -->
                        <div class="form-group" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <h4 style="margin-bottom: 15px; font-size: 13px; font-weight: 600; color: #555;">⚙️ Setări Individuale</h4>
                            
                            <!-- Cron Interval -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="control-label" style="font-size: 12px;">Ore Cron</label>
                                    <select name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][cron_interval_hours]" class="form-control form-control-sm">
                                        <?php for ($i = 0; $i <= 23; $i++) : ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($i === 0) ? 'selected' : ''; ?>><?php echo $i; ?> ore</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="control-label" style="font-size: 12px;">Minute Cron</label>
                                    <select name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][cron_interval_minutes]" class="form-control form-control-sm">
                                        <?php foreach ([0, 1, 2, 5, 10, 15, 20, 30, 45] as $m) : ?>
                                            <option value="<?php echo $m; ?>" <?php echo ($m === 30) ? 'selected' : ''; ?>><?php echo $m; ?> min</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Article Length -->
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="control-label" style="font-size: 12px;">Dimensiune Articol</label>
                                <select name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][article_length_option]" class="form-control form-control-sm">
                                    <option value="same_as_source" selected>Dimensiune variabilă (AI)</option>
                                    <option value="set_limits">Setează limite (cuvinte)</option>
                                </select>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="control-label" style="font-size: 12px;">Min Cuvinte</label>
                                    <input type="number" name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][min_length]" class="form-control form-control-sm" value="" placeholder="ex: 500">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="control-label" style="font-size: 12px;">Max Cuvinte</label>
                                    <input type="number" name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][max_length]" class="form-control form-control-sm" value="" placeholder="ex: 1000">
                                </div>
                            </div>
                            
                            <!-- Tags & Images -->
                            <div class="form-group" style="margin-bottom: 12px;">
                                <div class="checkbox-modern" style="margin-bottom: 8px;">
                                    <input type="checkbox" id="task_{{INDEX}}_tags" name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][generate_tags]" value="yes" checked />
                                    <label for="task_{{INDEX}}_tags" style="font-size: 12px;">Generează etichete automate</label>
                                </div>
                                <div class="checkbox-modern" style="margin-bottom: 8px;">
                                    <input type="checkbox" id="task_{{INDEX}}_extract_img" name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][extract_image_from_source]" value="yes" checked />
                                    <label for="task_{{INDEX}}_extract_img" style="font-size: 12px;">Extrage automat imaginea din articolul sursă (Parse Link, AI Browsing, Taskuri)</label>
                                </div>
                                <div class="checkbox-modern">
                                    <input type="checkbox" id="task_{{INDEX}}_images" name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][generate_image]" value="yes" />
                                    <label for="task_{{INDEX}}_images" style="font-size: 12px;">Da, generează automat imaginea cu AI (dacă nu există)</label>
                                </div>
                            </div>
                            
                            <!-- Publication Status -->
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="control-label" style="font-size: 12px;">Status Publicare</label>
                                <select name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][post_status]" class="form-control form-control-sm">
                                    <option value="draft" selected>Draft</option>
                                    <option value="publish">Publicat</option>
                                </select>
                            </div>
                            
                            <!-- AI Instructions -->
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="control-label" style="font-size: 12px;">Instrucțiuni AI specifice</label>
                                <textarea name="auto_ai_news_poster_settings[task_lists][{{INDEX}}][ai_instructions]" class="form-control form-control-sm" rows="2" placeholder="Instrucțiuni opționale pentru AI..."></textarea>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button type="button" class="btn btn-primary btn-block run-task-list-now" data-id="{{ID}}">
                                <i class="fas fa-magic"></i> Generează acum
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </script>
    <?php
    }

    // Callback pentru campul Mod de publicare
    public static function mode_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        ?>
        <div class="settings-group settings-group-parse_link settings-group-ai_browsing">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">⚙️</div>
                <h3 class="settings-card-title">Configurare Publicare</h3>
            </div>
            <div class="settings-card-content">
                <div class="form-group">
                    <label for="mode" class="control-label">Mod de publicare</label>
                    <select name="auto_ai_news_poster_settings[mode]" class="form-control" id="mode">
                        <option value="manual" <?php selected($options['mode'], 'manual'); ?>>Manual</option>
                        <option value="auto" <?php selected($options['mode'], 'auto'); ?>>Automat</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status" class="control-label">Status publicare articol</label>
                    <select name="auto_ai_news_poster_settings[status]" class="form-control" id="status">
                        <option value="draft" <?php selected($options['status'], 'draft'); ?>>Draft</option>
                        <option value="publish" <?php selected($options['status'], 'publish'); ?>>Publicat</option>
                    </select>
                </div>
            </div>
        </div>
        </div>
        <?php
    }

    // Callback pentru selectarea categoriei specifice pentru căutare
    public static function specific_search_category_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $selected_category = $options['specific_search_category'] ?? '';

        $categories = get_categories(['hide_empty' => false]);
        ?>
        <div class="settings-group settings-group-ai_browsing">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">📂</div>
                    <h3 class="settings-card-title">Configurare Categorii</h3>
                </div>
                <div class="settings-card-content">
                    <div class="form-group">
                        <label for="specific_search_category" class="control-label">Categorie specifică pentru căutare AI</label>
                        <select name="auto_ai_news_poster_settings[specific_search_category]" class="form-control" id="specific_search_category">
                            <option value="">Selectează o categorie</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($selected_category, $category->term_id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }


    // Callback pentru opțiunea de rulare automată a categoriilor
    public static function auto_rotate_categories_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        ?>
        <div class="settings-group settings-group-ai_browsing">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">🔄</div>
                    <h3 class="settings-card-title">Rotire Automată Categorii</h3>
                </div>
                <div class="settings-card-content">
                    <div class="checkbox-modern">
                        <input type="checkbox" name="auto_ai_news_poster_settings[auto_rotate_categories]" value="yes" <?php checked($options['auto_rotate_categories'], 'yes'); ?> />
                        <label>Da, rulează automat categoriile în ordine</label>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }


    // Callback pentru sursele de stiri
    public static function news_sources_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        ?>
        <div class="settings-group settings-group-ai_browsing">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">📰</div>
                    <h3 class="settings-card-title">Surse de Informare AI</h3>
                </div>
                <div class="settings-card-content">
                    <div class="form-group">
                        <label for="news_sources" class="control-label">Surse de știri pentru informare AI</label>
                        <textarea name="auto_ai_news_poster_settings[news_sources]" class="form-control" id="news_sources"
                                  rows="6"><?php echo esc_textarea($options['news_sources']); ?></textarea>
                        <small class="form-text text-muted">Adăugați câte un URL de sursă pe fiecare linie. AI-ul le va folosi ca punct de plecare pentru a găsi știri noi.</small>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // Callback pentru cheia API
    public static function chatgpt_api_key_callback()
    {
        ?>
        <div class="settings-group settings-group-parse_link settings-group-ai_browsing active">
            <?php self::render_ai_config_component('main'); ?>
        </div>
        <?php
    }

    // Callback pentru setarea intervalului cron
    public static function cron_interval_callback()
    {
        ?>
        <div class="settings-group settings-group-parse_link settings-group-ai_browsing active">
            <?php self::render_cron_config_component('main'); ?>
        </div>
        <?php
    }

    // Callback pentru selectarea autorului
    public static function author_name_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $selected_author = $options['author_name'] ?? get_current_user_id();

        // Obținem lista de utilizatori cu rolul 'Author' sau 'Administrator'
        $users = get_users([
            'role__in' => ['Author', 'Administrator'],
            'orderby' => 'display_name'
        ]);
        ?>
        <div class="settings-group settings-group-parse_link settings-group-ai_browsing">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">👤</div>
                <h3 class="settings-card-title">Configurare Autor</h3>
            </div>
            <div class="settings-card-content">
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
            </div>
        </div>
        </div>
        <?php
    }


    // Callback pentru instrucțiunile AI (textarea) - Mod Parsare Link
    public static function parse_link_ai_instructions_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $instructions = $options['parse_link_ai_instructions'] ?? 'Creează un articol unic pe baza textului extras. Respectă structura JSON cu titlu, conținut, etichete, și rezumat. Asigură-te că articolul este obiectiv și bine formatat.';
        ?>
        <div class="settings-group settings-group-parse_link">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">✍️</div>
                    <h3 class="settings-card-title">Instrucțiuni AI pentru Parsare Link</h3>
                </div>
                <div class="settings-card-content">
                    <div class="form-group">
                        <label class="control-label">Instrucțiuni pentru AI (când se parsează un link specific)</label>
                        <textarea name="auto_ai_news_poster_settings[parse_link_ai_instructions]" class="form-control" rows="6"
                                  placeholder="Introdu instrucțiunile suplimentare pentru AI"><?php echo esc_textarea($instructions); ?></textarea>
                        <small class="form-text text-muted">Aceste instrucțiuni sunt adăugate la prompt atunci când generați un articol dintr-un link specific.</small>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // Callback pentru instrucțiunile AI (textarea) - Mod AI Browsing
    public static function ai_browsing_instructions_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $instructions = $options['ai_browsing_instructions'] ?? 'Scrie un articol de știre original, în limba română ca un jurnalist. Articolul trebuie să fie obiectiv, informativ și bine structurat (introducere, cuprins, încheiere).';
        ?>
        <div class="settings-group settings-group-ai_browsing">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">🤖</div>
                    <h3 class="settings-card-title">Instrucțiuni AI pentru Generare Știre</h3>
                </div>
                <div class="settings-card-content">
                    <div class="form-group">
                        <label class="control-label">Instrucțiuni pentru AI (când AI-ul caută o știre nouă)</label>
                        <textarea name="auto_ai_news_poster_settings[ai_browsing_instructions]" class="form-control" rows="6"
                                  placeholder="Introdu instrucțiunile suplimentare pentru AI"><?php echo esc_textarea($instructions); ?></textarea>
                        <small class="form-text text-muted">Aceste instrucțiuni sunt adăugate la promptul complex de generare, în secțiunea "Sarcina ta".</small>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // Callback pentru controlul generării etichetelor
    public static function generate_tags_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $generate_tags = $options['generate_tags'] ?? 'yes';
        ?>
        <div class="settings-group settings-group-parse_link settings-group-ai_browsing">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">🏷️</div>
                <h3 class="settings-card-title">Control Etichete</h3>
            </div>
            <div class="settings-card-content">
                <div class="form-group">
                    <div class="custom-checkbox">
                        <input type="checkbox" name="auto_ai_news_poster_settings[generate_tags]" id="generate_tags" 
                               value="yes" <?php checked($generate_tags, 'yes'); ?>>
                        <label for="generate_tags" class="checkbox-label">
                            <span class="checkbox-icon">🏷️</span>
                            Generează și utilizează etichete în articole
                        </label>
                        <div class="checkbox-description">
                            Dacă este bifat, AI-ul va genera etichete pentru articole și le va folosi pentru optimizare SEO. 
                            Dacă nu este bifat, articolele vor fi generate fără etichete.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        <?php
    }

    // Select pentru dimensiunea articolului
    public static function article_length_option_callback()
    {
        ?>
        <div class="settings-group settings-group-parse_link settings-group-ai_browsing active">
            <?php self::render_article_length_component('main'); ?>
        </div>
        <?php
    }




    public static function bulk_custom_source_urls_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $bulk_links = $options['bulk_custom_source_urls'] ?? '';

        // Safety: If legacy data is an array, convert back to string
        if (is_array($bulk_links)) {
            $urls = array_map(function ($item) { return $item['url']; }, $bulk_links);
            $bulk_links = implode("\n", $urls);
        }
        ?>
        <div class="settings-group settings-group-parse_link">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">🗒️</div>
                    <h3 class="settings-card-title">Coada de Parsare (Bulk List)</h3>
                </div>
                <div class="settings-card-content">
                    <div class="form-group">
                        <label class="control-label">Lista de linkuri de articole (Queue)</label>
                        <textarea name="auto_ai_news_poster_settings[bulk_custom_source_urls]" id="bulk_custom_source_urls" class="form-control" rows="8" placeholder="Introduceți câte un link de articol pe fiecare rând"><?php echo esc_textarea($bulk_links); ?></textarea>
                        <small class="form-text text-muted">Aceste linkuri vor fi parsite automat de către cron. Linkurile importate din Site Analyzer vor ajunge aici.</small>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function scanning_source_urls_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $scan_links = $options['scanning_source_urls'] ?? [];

        // Migration: if scanning_source_urls is empty but bulk_custom_source_urls contains an array, move it
        if (empty($scan_links) && isset($options['bulk_custom_source_urls']) && is_array($options['bulk_custom_source_urls'])) {
            $scan_links = $options['bulk_custom_source_urls'];
        }
        ?>
        <div class="settings-group settings-group-parse_link">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">🔗</div>
                    <h3 class="settings-card-title">Surse Principale de Scanat</h3>
                </div>
                <div class="settings-card-content">
                    <div id="scanning-sources-wrapper">
                        <table class="wp-list-table widefat fixed striped" id="scanning-sources-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px; text-align: center;">Activ</th>
                                    <th>URL Sursă (Site-uri de știri)</th>
                                    <th style="width: 50px;">Acțiuni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($scan_links)): ?>
                                    <?php foreach ($scan_links as $index => $item): ?>
                                        <tr class="scan-link-row">
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="auto_ai_news_poster_settings[scanning_source_urls][<?php echo $index; ?>][active]" value="yes" <?php checked($item['active'] ?? 'yes', 'yes'); ?>>
                                            </td>
                                            <td>
                                                <input type="text" name="auto_ai_news_poster_settings[scanning_source_urls][<?php echo $index; ?>][url]" value="<?php echo esc_url($item['url']); ?>" class="form-control" style="width: 100%;" placeholder="https://example.com/">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger remove-scan-link">×</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 15px;">
                            <button type="button" class="btn btn-primary" id="add-scan-link">+ Adaugă sursă</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#add-scan-link').on('click', function() {
                    var index = $('#scanning-sources-table tbody tr').length;
                    var row = '<tr class="scan-link-row">' +
                        '<td style="text-align: center;"><input type="checkbox" name="auto_ai_news_poster_settings[scanning_source_urls][' + index + '][active]" value="yes" checked></td>' +
                        '<td><input type="text" name="auto_ai_news_poster_settings[scanning_source_urls][' + index + '][url]" value="" class="form-control" style="width: 100%;"></td>' +
                        '<td><button type="button" class="btn btn-sm btn-danger remove-scan-link">×</button></td>' +
                        '</tr>';
                    $('#scanning-sources-table tbody').append(row);
                });

                $(document).on('click', '.remove-scan-link', function() {
                    $(this).closest('tr').remove();
                    $('#scanning-sources-table tbody tr').each(function(i) {
                        $(this).find('input[type="checkbox"]').attr('name', 'auto_ai_news_poster_settings[scanning_source_urls][' + i + '][active]');
                        $(this).find('input[type="text"]').attr('name', 'auto_ai_news_poster_settings[scanning_source_urls][' + i + '][url]');
                    });
                });
            });
        </script>
        <?php
    }

    public static function run_until_bulk_exhausted_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $is_auto_mode = isset($options['mode']) && $options['mode'] === 'auto'; // Verificăm dacă modul este "auto"
        $run_until_bulk_exhausted = $options['run_until_bulk_exhausted'] ?? ''; // Valoare implicită pentru cheie
        ?>
        <div class="settings-group settings-group-parse_link">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">⚡</div>
                    <h3 class="settings-card-title">Configurare Avansată Parsare</h3>
                </div>
                <div class="settings-card-content">
                    <div class="checkbox-modern">
                        <input type="checkbox" name="auto_ai_news_poster_settings[run_until_bulk_exhausted]" 
                               value="yes" <?php checked($run_until_bulk_exhausted, 'yes'); ?>
                               <?php echo $is_auto_mode ? '' : 'disabled'; ?> />
                        <label>Da, rulează doar până la epuizarea listei de linkuri</label>
                    </div>
                    <small class="form-text text-muted">Această opțiune este disponibilă doar în modul automat.</small>
                    <script>
                        // Script JavaScript pentru a dezactiva checkbox-ul dacă modul este schimbat
                        document.getElementById('mode').addEventListener('change', function () {
                            const checkbox = document.querySelector('input[name="auto_ai_news_poster_settings[run_until_bulk_exhausted]"]');
                            checkbox.disabled = this.value !== 'auto';
                        });
                    </script>
                </div>
            </div>
        </div>
        <?php
    }

    // Funcție pentru obținerea modelelor OpenAI cu cache
    public static function get_cached_openai_models($api_key)
    {
        // Verificăm cache-ul (24 ore)
        $cached_models = get_transient('openai_models_cache');

        if ($cached_models !== false && !empty($cached_models)) {
            return $cached_models;
        }

        // Dacă nu avem API key, returnăm eroare
        if (empty($api_key)) {
            return ['error' => 'API key is required', 'error_type' => 'missing_api_key'];
        }

        // Facem apel API pentru a obține modelele
        $models = self::get_available_openai_models($api_key);

        if ($models && !empty($models)) {
            // Salvăm în cache pentru 24 ore
            set_transient('openai_models_cache', $models, 24 * HOUR_IN_SECONDS);
            return $models;
        }

        // Returnăm eroare dacă API-ul nu răspunde
        return ['error' => 'Failed to load models from OpenAI API', 'error_type' => 'api_error'];
    }

    // Funcție pentru apelarea API-ului OpenAI pentru modele
    public static function get_available_openai_models($api_key)
    {
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [
                'error' => 'Network error: ' . $response->get_error_message(),
                'error_type' => 'network_error'
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Verificăm codul de răspuns
        if ($response_code !== 200) {
            $error_message = 'API Error (HTTP ' . $response_code . ')';
            if (isset($data['error']['message'])) {
                $error_message .= ': ' . $data['error']['message'];
            }
            return [
                'error' => $error_message,
                'error_type' => 'api_error',
                'response_code' => $response_code
            ];
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            return [
                'error' => 'Invalid API response format',
                'error_type' => 'invalid_response'
            ];
        }

        // Filtrează doar modelele cu output structurat
        $structured_models = self::filter_structured_output_models($data['data']);

        if (empty($structured_models)) {
            return [
                'error' => 'No structured output models found in API response',
                'error_type' => 'no_models'
            ];
        }

        // Organizează modelele într-un array asociativ
        $models_array = [];
        foreach ($structured_models as $model) {
            $models_array[$model['id']] = $model;
        }

        return $models_array;
    }

    // Funcție pentru filtrarea modelelor cu output structurat
    public static function filter_structured_output_models($models)
    {
        $structured_models = [
            // GPT-5 Series (Latest)
            'gpt-5', 'gpt-5-nano', 'gpt-5-mini',
            // GPT-4 Series
            'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4',
            'gpt-4o-2024-08-06', 'gpt-4-turbo-2024-04-09', 'gpt-4-0613', 'gpt-4-0314',
            // GPT-3.5 Series
            'gpt-3.5-turbo', 'gpt-3.5-turbo-1106', 'gpt-3.5-turbo-0613', 'gpt-3.5-turbo-0301'
        ];

        return array_filter($models, function ($model) use ($structured_models) {
            // Verificăm dacă modelul este în lista noastră sau dacă începe cu gpt-5, gpt-4 sau gpt-3.5
            return in_array($model['id'], $structured_models) ||
                   strpos($model['id'], 'gpt-5') === 0 ||
                   strpos($model['id'], 'gpt-4') === 0 ||
                   strpos($model['id'], 'gpt-3.5') === 0;
        });
    }

    // Lista statică de modele (fallback)
    public static function get_static_models_list()
    {
        return [
            // GPT-5 Series (Latest)
            'gpt-5' => ['id' => 'gpt-5', 'object' => 'model'],
            'gpt-5-nano' => ['id' => 'gpt-5-nano', 'object' => 'model'],
            'gpt-5-mini' => ['id' => 'gpt-5-mini', 'object' => 'model'],
            // GPT-4 Series
            'gpt-4o' => ['id' => 'gpt-4o', 'object' => 'model'],
            'gpt-4o-mini' => ['id' => 'gpt-4o-mini', 'object' => 'model'],
            'gpt-4-turbo' => ['id' => 'gpt-4-turbo', 'object' => 'model'],
        ];
    }

    // Funcție pentru descrierile modelelor
    public static function get_model_description($model_id)
    {
        $descriptions = [
            // GPT-5 Series (Latest and most advanced)
            'gpt-5' => 'GPT-5 - Cel mai bun model pentru coding și task-uri agentice',
            'gpt-5-nano' => 'GPT-5 Nano - Cel mai rapid și economic GPT-5',
            'gpt-5-mini' => 'GPT-5 Mini - Versiune rapidă și economică pentru task-uri bine definite',
            // GPT-4 Series
            'gpt-4o' => 'GPT-4o - Acuratețe înaltă, cost moderat',
            'gpt-4o-mini' => 'GPT-4o Mini - Optimizat pentru precizie, cost redus',
            'gpt-4-turbo' => 'GPT-4 Turbo - Acuratețe maximă, cost ridicat',
            'gpt-4' => 'GPT-4 - Model clasic, performanță înaltă',
            // GPT-3.5 Series
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo - Rapid și economic',
        ];

        // Dacă nu avem descriere specifică, generăm una dinamică
        if (!isset($descriptions[$model_id])) {
            if (strpos($model_id, 'gpt-5') === 0) {
                return $model_id . ' - Model GPT-5 de ultimă generație';
            } elseif (strpos($model_id, 'gpt-4') === 0) {
                return $model_id . ' - Model GPT-4 avansat';
            } elseif (strpos($model_id, 'gpt-3.5') === 0) {
                return $model_id . ' - Model GPT-3.5 rapid';
            } else {
                return $model_id;
            }
        }

        return $descriptions[$model_id];
    }

    // Funcție pentru obținerea modelelor Gemini cu cache
    public static function get_cached_gemini_models($api_key)
    {
        // Verificăm cache-ul (24 ore)
        $cached_models = get_transient('gemini_models_cache');

        if ($cached_models !== false && !empty($cached_models)) {
            return $cached_models;
        }

        // Dacă nu avem API key, returnăm eroare
        if (empty($api_key)) {
            return ['error' => 'API key is required', 'error_type' => 'missing_api_key'];
        }

        // Facem apel API pentru a obține modelele
        $models = self::get_available_gemini_models($api_key);

        if ($models && !empty($models) && !isset($models['error'])) {
            // Salvăm în cache pentru 24 ore
            set_transient('gemini_models_cache', $models, 24 * HOUR_IN_SECONDS);
            return $models;
        }

        // Returnăm eroare dacă API-ul nu răspunde
        return $models ?: ['error' => 'Failed to load models from Gemini API', 'error_type' => 'api_error'];
    }

    // Funcție pentru apelarea API-ului Gemini pentru modele
    public static function get_available_gemini_models($api_key)
    {
        // Use constant if available, otherwise fallback (though constant should be there)
        $base_url = defined('URL_API_GEMINI_MODELS') ? URL_API_GEMINI_MODELS : 'https://generativelanguage.googleapis.com/v1beta/models';
        $endpoint = $base_url . '?key=' . urlencode($api_key) . '&pageSize=1000';

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [
                'error' => 'Network error: ' . $response->get_error_message(),
                'error_type' => 'network_error'
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Verificăm codul de răspuns
        if ($response_code !== 200) {
            $error_message = 'API Error (HTTP ' . $response_code . ')';
            if (isset($data['error']['message'])) {
                $error_message .= ': ' . $data['error']['message'];
            }
            return [
                'error' => $error_message,
                'error_type' => 'api_error',
                'response_code' => $response_code
            ];
        }

        if (!isset($data['models']) || !is_array($data['models'])) {
            return [
                'error' => 'Invalid API response format',
                'error_type' => 'invalid_response'
            ];
        }



        // Log raw models for debugging
        error_log('AUTO AI NEWS POSTER - Gemini Models Raw Count: ' . count($data['models']));

        // Filtrează doar modelele Gemini relevante (exclude Imagen și alte modele non-text)
        $filtered_models = self::filter_gemini_models($data['models']);

        error_log('AUTO AI NEWS POSTER - Gemini Models Filtered Count: ' . count($filtered_models));
        error_log('AUTO AI NEWS POSTER - Gemini Filtered Names: ' . print_r(array_column($filtered_models, 'name'), true));

        if (empty($filtered_models)) {
            return [
                'error' => 'No Gemini models found in API response',
                'error_type' => 'no_models'
            ];
        }

        // Initialize descriptions
        //$descriptions = self::get_gemini_model_descriptions_map(); // We can rely on API display names now

        $models_array = [];
        foreach ($filtered_models as $model) {
            $model_name = $model['name'] ?? ''; // e.g., models/gemini-1.5-flash
            $display_name = $model['displayName'] ?? $model_name;
            $description = $model['description'] ?? '';

            // Construct a readable label
            $label = $display_name;
            if ($model_name !== $display_name) {
                $label .= " ({$model_name})";
            }

            // Use the full model name as key (including models/ prefix)
            $models_array[$model_name] = $label;
        }

        return $models_array;
    }

    // Funcție pentru filtrarea modelelor Gemini
    public static function filter_gemini_models($models)
    {
        return array_filter($models, function ($model) {
            $name = $model['name'] ?? '';
            // Păstrăm tot ce începe cu "models/", dar excludem embeddings, aqa și imagen
            return strpos($name, 'models/') === 0 &&
                   strpos($name, 'embedding') === false &&
                   strpos($name, 'aqa') === false &&
                   strpos($name, 'imagen') === false;
        });
    }

    // Funcție pentru descrierile modelelor Gemini
    public static function get_gemini_model_description($model_id)
    {
        // Extragem numele modelului din formatul "models/gemini-1.5-pro" sau "gemini-1.5-pro"
        $clean_id = str_replace('models/', '', $model_id);

        $descriptions = [
            // Gemini 2.0 Series
            'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental) - Cel mai nou model experimental',
            'models/gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental) - Cel mai nou model experimental',

            // Gemini 1.5 Series (Latest)
            'gemini-1.5-pro-latest' => 'Gemini 1.5 Pro (Latest) - Versiunea cea mai recentă',
            'models/gemini-1.5-pro-latest' => 'Gemini 1.5 Pro (Latest) - Versiunea cea mai recentă',
            'gemini-1.5-flash-latest' => 'Gemini 1.5 Flash (Latest) - Versiunea cea mai recentă, rapidă',
            'models/gemini-1.5-flash-latest' => 'Gemini 1.5 Flash (Latest) - Versiunea cea mai recentă, rapidă',

            // Gemini 1.5 Series (Stable)
            'gemini-1.5-pro' => 'Gemini 1.5 Pro - Model avansat pentru task-uri complexe',
            'models/gemini-1.5-pro' => 'Gemini 1.5 Pro - Model avansat pentru task-uri complexe',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash - Model rapid și eficient',
            'models/gemini-1.5-flash' => 'Gemini 1.5 Flash - Model rapid și eficient',

            // Gemini 1.0 Series
            'gemini-1.0-pro' => 'Gemini 1.0 Pro - Model clasic, performanță stabilă',
            'models/gemini-1.0-pro' => 'Gemini 1.0 Pro - Model clasic, performanță stabilă',

            // Experimental
            'gemini-exp-1206' => 'Gemini Experimental (1206) - Model experimental',
            'models/gemini-exp-1206' => 'Gemini Experimental (1206) - Model experimental',
        ];

        // Dacă avem descriere specifică, o returnăm
        if (isset($descriptions[$model_id])) {
            return $descriptions[$model_id];
        }
        if (isset($descriptions[$clean_id])) {
            return $descriptions[$clean_id];
        }

        // Generăm descriere dinamică pe baza numelui modelului
        if (strpos($clean_id, 'gemini-2.0') === 0) {
            return $clean_id . ' - Model Gemini 2.0 de ultimă generație';
        } elseif (strpos($clean_id, 'gemini-1.5') === 0) {
            if (strpos($clean_id, 'flash') !== false) {
                return $clean_id . ' - Model Gemini 1.5 Flash rapid';
            } else {
                return $clean_id . ' - Model Gemini 1.5 Pro avansat';
            }
        } elseif (strpos($clean_id, 'gemini-1.0') === 0) {
            return $clean_id . ' - Model Gemini 1.0 clasic';
        } elseif (strpos($clean_id, 'gemini-exp') === 0 || strpos($clean_id, 'exp') !== false) {
            return $clean_id . ' - Model experimental Gemini';
            return $clean_id;
        }
    }

    public static function get_available_deepseek_models($api_key)
    {
        $endpoint = defined('URL_API_DEEPSEEK_MODELS') ? URL_API_DEEPSEEK_MODELS : 'https://api.deepseek.com/models';
        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30, // Timeout de 30 secunde
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'Connection failed: ' . $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['data'])) {
            return ['error' => 'Invalid response from DeepSeek API'];
        }

        $models = [];
        foreach ($data['data'] as $model) {
            $id = $model['id'];
            $models[$id] = $id; // DeepSeek nu are neapărat descrieri separate momentan, folosim ID-ul
        }

        return $models;
    }

    public static function get_cached_deepseek_models($api_key)
    {
        // Încercăm să obținem modelele din cache
        $cached_models = get_transient('deepseek_models_cache');

        if ($cached_models !== false) {
            return $cached_models;
        }

        // Dacă nu avem API Key, returnăm o listă goală (nu încercăm să tragem date)
        if (empty($api_key)) {
            return [];
        }

        // Dacă nu sunt în cache, le obținem din API
        $models = self::get_available_deepseek_models($api_key);

        if (isset($models['error'])) {
            return $models; // Returnăm eroarea pentru afișare
        }

        // Salvăm în cache pentru 24 ore
        set_transient('deepseek_models_cache', $models, 24 * HOUR_IN_SECONDS);

        return $models;
    }

    // Handler AJAX pentru actualizarea listei de modele
    public static function ajax_refresh_openai_models()
    {
        // Verificăm nonce-ul
        /*if (!wp_verify_nonce($_POST['nonce'], 'refresh_models_nonce')) {
             // Removed verification temporarily as nonce passing in settings.js needs to be consistent
        }*/
        // Verificăm nonce-ul
        if (!wp_verify_nonce($_POST['nonce'], 'refresh_models_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }

        // Verificăm permisiunile
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        if (empty($api_key)) {
            wp_send_json_error('API key is required');
            return;
        }

        // Ștergem cache-ul existent
        delete_transient('openai_models_cache');

        // Obținem noile modele
        $models = self::get_available_openai_models($api_key);

        if ($models && !empty($models)) {
            // Salvăm în cache pentru 24 ore
            set_transient('openai_models_cache', $models, 24 * HOUR_IN_SECONDS);
            wp_send_json_success('Models list updated successfully');
        } else {
            wp_send_json_error('Failed to fetch models from OpenAI API');
        }
    }

    // Handler AJAX pentru actualizarea listei de modele Gemini
    public static function ajax_refresh_gemini_models()
    {
        // Verificăm nonce-ul
        if (!wp_verify_nonce($_POST['nonce'], 'refresh_gemini_models_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }

        // Verificăm permisiunile
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        if (empty($api_key)) {
            wp_send_json_error('API key is required');
            return;
        }

        // Ștergem cache-ul existent
        delete_transient('gemini_models_cache');

        // Obținem noile modele
        $models = self::get_available_gemini_models($api_key);

        if ($models && !empty($models) && !isset($models['error'])) {
            // Salvăm în cache pentru 24 ore
            set_transient('gemini_models_cache', $models, 24 * HOUR_IN_SECONDS);
            // Updating the option so the dropdown reflects the new list immediately
            update_option('auto_ai_news_poster_gemini_models', $models);
            wp_send_json_success('Gemini models list updated successfully');
        } else {
            $error_msg = isset($models['error']) ? $models['error'] : 'Failed to fetch models from Gemini API';
            wp_send_json_error($error_msg);
        }
    }

    // Handler AJAX pentru actualizarea listei de modele DeepSeek
    public static function ajax_refresh_deepseek_models()
    {
        // Verificăm nonce-ul
        if (!wp_verify_nonce($_POST['nonce'], 'refresh_deepseek_models_nonce')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }

        // Verificăm permisiunile
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        if (empty($api_key)) {
            wp_send_json_error('API key is required');
            return;
        }

        // Ștergem cache-ul existent
        delete_transient('deepseek_models_cache');

        // Obținem noile modele
        $models = self::get_available_deepseek_models($api_key);

        if ($models && !empty($models) && !isset($models['error'])) {
            // Salvăm în cache pentru 24 ore
            set_transient('deepseek_models_cache', $models, 24 * HOUR_IN_SECONDS);
            // Update the option for DeepSeek too
            update_option('auto_ai_news_poster_deepseek_models', $models);
            wp_send_json_success('DeepSeek models list updated successfully');
        } else {
            $error_msg = isset($models['error']) ? $models['error'] : 'Failed to fetch models from DeepSeek API';
            wp_send_json_error($error_msg);
        }
    }

    // Funcție simplă pentru sanitizarea doar a checkbox-urilor
    public static function sanitize_checkbox_settings($input)
    {
        // Obținem setările existente
        $existing_options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, []);

        // Păstrăm toate setările existente
        $sanitized = $existing_options;

        // Lista checkbox-urilor care trebuie să fie setate explicit
        $checkbox_fields = ['auto_rotate_categories', 'generate_image', 'extract_image_from_source',
                           'run_until_bulk_exhausted', 'generate_tags', 'use_openai', 'use_gemini', 'use_deepseek'];

        // Câmpurile de tip <select> care trebuie validate
        $select_fields = ['mode', 'status', 'specific_search_category', 'author_name', 'article_length_option', 'use_external_images', 'ai_model', 'generation_mode', 'gemini_model', 'imagen_model', 'deepseek_model'];

        // Setăm toate checkbox-urile la 'no' înainte de a procesa input-ul
        foreach ($checkbox_fields as $checkbox_field) {
            $sanitized[$checkbox_field] = 'no';
        }

        // Actualizăm doar câmpurile din input
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                // Pentru checkbox-uri, setăm 'yes' dacă sunt bifate
                if (in_array($key, $checkbox_fields)) {
                    $sanitized[$key] = ($value === 'yes') ? 'yes' : 'no';
                }
                // Pentru câmpurile de tip <select>, salvăm valoarea selectată
                elseif (in_array($key, $select_fields)) {
                    $sanitized[$key] = sanitize_text_field($value);
                }
                // Tasks Configuration (Nested Array)
                elseif ($key === 'tasks_config') {
                    if (is_array($value)) {
                        foreach ($value as $t_key => $t_val) {
                            if ($t_key === 'ai_instructions') {
                                $sanitized[$key][$t_key] = esc_textarea($t_val);
                            } else {
                                $sanitized[$key][$t_key] = sanitize_text_field($t_val);
                            }
                        }
                    }
                }
                // Task Lists (Array of Items) - with individual settings
                elseif ($key === 'task_lists') {
                    $sanitized[$key] = [];
                    if (is_array($value)) {
                        foreach ($value as $list_item) {
                            $sanitized[$key][] = [
                                // Existing fields
                                'id'       => sanitize_text_field($list_item['id'] ?? ''),
                                'name'     => sanitize_text_field($list_item['name'] ?? ''),
                                'titles'   => esc_textarea($list_item['titles'] ?? ''),
                                'author'   => intval($list_item['author'] ?? 1),
                                'category' => intval($list_item['category'] ?? 0),

                                // NEW INDIVIDUAL SETTINGS
                                'cron_interval_hours'   => intval($list_item['cron_interval_hours'] ?? 0),
                                'cron_interval_minutes' => intval($list_item['cron_interval_minutes'] ?? 30),
                                'generate_tags'         => ($list_item['generate_tags'] ?? 'yes') === 'yes' ? 'yes' : 'no',
                                'generate_image'        => ($list_item['generate_image'] ?? 'no') === 'yes' ? 'yes' : 'no',
                                'extract_image_from_source' => ($list_item['extract_image_from_source'] ?? 'yes') === 'yes' ? 'yes' : 'no',
                                'article_length_option' => sanitize_text_field($list_item['article_length_option'] ?? 'same_as_source'),
                                'min_length'            => intval($list_item['min_length'] ?? 0),
                                'max_length'            => intval($list_item['max_length'] ?? 0),
                                'post_status'           => in_array($list_item['post_status'] ?? 'draft', ['draft', 'publish']) ? $list_item['post_status'] : 'draft',
                                'ai_instructions'       => esc_textarea($list_item['ai_instructions'] ?? '')
                            ];
                        }
                    }
                }
                // Pentru textarea, folosim o sanitizare specifică
                elseif ($key === 'news_sources' || $key === 'parse_link_ai_instructions' || $key === 'ai_browsing_instructions' || $key === 'bulk_custom_source_urls') {
                    $sanitized[$key] = esc_textarea($value);
                } elseif ($key === 'scanning_source_urls') {
                    $sanitized[$key] = [];
                    if (is_array($value)) {
                        foreach ($value as $item) {
                            $url = isset($item['url']) ? esc_url_raw(trim($item['url'])) : '';
                            if (!empty($url)) {
                                $sanitized[$key][] = [
                                    'url' => $url,
                                    'active' => (isset($item['active']) && $item['active'] === 'yes') ? 'yes' : 'no'
                                ];
                            }
                        }
                    }
                }
                // Pentru alte câmpuri, sanitizăm normal
                else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }
        }

        // If provider selected but key missing, for safety reset selection
        if ((($sanitized['use_openai'] ?? 'no') === 'yes') && empty($sanitized['chatgpt_api_key'])) {
            $sanitized['use_openai'] = 'no';
        }
        if ((($sanitized['use_gemini'] ?? 'no') === 'yes') && empty($sanitized['gemini_api_key'])) {
            $sanitized['use_gemini'] = 'no';
        }

        // Mutual exclusivity pentru provider: dacă ambele sunt yes, păstrăm doar OpenAI implicit
        if (($sanitized['use_openai'] ?? 'no') === 'yes' && ($sanitized['use_gemini'] ?? 'no') === 'yes') {
            $sanitized['use_gemini'] = 'no';
        }

        return $sanitized;
    }

    public static function section_callback()
    {
        echo '<p>Configurează setările principale ale pluginului.</p>';
    }

    // --- Site Analyzer Tool ---

    public static function site_analyzer_ui_callback()
    {
        ?>
        <div class="settings-group settings-group-parse_link">
            <div class="settings-card site-analyzer-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">🔎</div>
                    <h3 class="settings-card-title">Site Analyzer (AI Filter)</h3>
                </div>
                <div class="settings-card-content">
                    <div class="form-row" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 2; min-width: 150px;">
                            <label for="sa_context">Context / Category</label>
                            <input type="text" id="sa_context" class="form-control" placeholder="ex: Technology, Politics">
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 100px;">
                            <label for="sa_max_links">Max Links to Extract</label>
                            <input type="number" id="sa_max_links" class="form-control" value="10" min="1" max="50">
                        </div>
                        <div class="form-group">
                            <button type="button" id="btn_scan_site" class="button button-primary button-large">
                                🚀 Scan & Analyze
                            </button>
                        </div>
                    </div>
                    
                    <div id="sa_loading_spinner" style="display:none; margin-top: 15px; color: #666;">
                        <span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Scanning and Filtering with AI... This may take a minute.
                    </div>

                    <div id="site_analyzer_results" style="margin-top: 20px; display: none;">
                        <h4>Analysis Results (<span id="sa_result_count">0</span>)</h4>
                        <div class="sa-table-wrapper" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; background: #fff;">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th class="check-column"><input type="checkbox" id="sa_select_all"></th>
                                        <th>Title</th>
                                        <th>URL</th>
                                    </tr>
                                </thead>
                                <tbody id="sa_results_body">
                                    <!-- Results will be injected here -->
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top: 15px;">
                            <button type="button" id="btn_sa_import_selected" class="button button-primary">
                                Import Selected to Queue
                            </button>
                            <span id="sa_import_status" style="margin-left: 10px; font-weight: bold; color: green;"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function ajax_scan_site()
    {
        check_ajax_referer('auto_ai_news_poster_check_settings', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $urls = isset($_POST['urls']) ? $_POST['urls'] : [];
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : '';
        $max_links = isset($_POST['max_links']) ? intval($_POST['max_links']) : 10;

        if (empty($urls) || !is_array($urls)) {
            wp_send_json_error('No URLs provided for scanning.');
        }

        // Increase timeout for multi-site scans
        set_time_limit(180);

        $all_candidates = [];
        include_once plugin_dir_path(__FILE__) . 'class-auto-ai-news-poster-scanner.php';

        foreach ($urls as $url) {
            $url = esc_url_raw(trim($url));
            if (empty($url)) {
                continue;
            }

            $site_candidates = Auto_Ai_News_Poster_Scanner::scan_url($url);
            if (!is_wp_error($site_candidates)) {
                // Limit candidates per site to avoid massive aggregate lists (e.g. 30 per site)
                $all_candidates = array_merge($all_candidates, array_slice($site_candidates, 0, 30));
            }
        }

        if (empty($all_candidates)) {
            wp_send_json_error('No links found on the provided sites.');
        }

        // Filter all collected candidates at once, passing the user-defined limit
        $filtered = Auto_Ai_News_Poster_Scanner::filter_candidates_with_ai($all_candidates, $context, $max_links);

        if (is_wp_error($filtered)) {
            wp_send_json_error('AI Filter Error: ' . $filtered->get_error_message());
        }

        wp_send_json_success([
            'count' => count($filtered),
            'candidates' => $filtered,
            'original_count' => count($all_candidates)
        ]);
    }

    public static function ajax_import_selected()
    {
        check_ajax_referer('auto_ai_news_poster_check_settings', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $items = isset($_POST['items']) ? $_POST['items'] : [];
        if (empty($items) || !is_array($items)) {
            wp_send_json_error('No items selected.');
        }

        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $bulk_links_string = isset($options['bulk_custom_source_urls']) ? $options['bulk_custom_source_urls'] : '';

        // Ensure it's handled as string for the queue
        if (is_array($bulk_links_string)) {
            $urls = array_map(function ($item) { return $item['url']; }, $bulk_links_string);
            $bulk_links_string = implode("\n", $urls);
        }

        $existing_urls = explode("\n", $bulk_links_string);
        $existing_urls = array_map('trim', $existing_urls);
        $existing_urls = array_filter($existing_urls);

        foreach ($items as $item) {
            $url = esc_url_raw(trim($item['url']));
            if (!empty($url) && !in_array($url, $existing_urls)) {
                $existing_urls[] = $url;
            }
        }

        $options['bulk_custom_source_urls'] = implode("\n", $existing_urls);
        update_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION, $options);

        wp_send_json_success('Imported ' . count($items) . ' links to the queue.');
    }

    /**
     * REUSABLE COMPONENT: AI Configuration Card
     */
    public static function render_ai_config_component($context = 'main')
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $is_tasks = ($context === 'tasks');
        $data = $is_tasks ? ($options['tasks_config'] ?? []) : $options;
        $name_key = $is_tasks ? 'tasks_config' : '';
        $id_pfx = $is_tasks ? 'tasks_' : '';
        $title = $is_tasks ? 'Configurare API AI (Tasks)' : 'Configurare API AI';

        // Helper for field names
        $fn = function ($key) use ($name_key) {
            return $name_key ? "auto_ai_news_poster_settings[{$name_key}][{$key}]" : "auto_ai_news_poster_settings[{$key}]";
        };

        $current_provider = $data['api_provider'] ?? ($is_tasks ? 'openai' : ($options['api_provider'] ?? 'openai'));
        $openai_key = $data['chatgpt_api_key'] ?? '';
        $selected_model = $data['ai_model'] ?? ($is_tasks ? 'gpt-4o-mini' : DEFAULT_AI_MODEL);

        // Fetch models
        $available_models = self::get_cached_openai_models($openai_key);
        $has_error = isset($available_models['error']);
        $error_message = $has_error ? $available_models['error'] : '';
        ?>
        <div class="settings-card ai-config-card" data-context="<?php echo $context; ?>">
            <div class="settings-card-header">
                <div class="settings-card-icon">🔑</div>
                <h3 class="settings-card-title"><?php echo esc_html($title); ?></h3>
            </div>
            <div class="settings-card-content">
                <div class="form-grid">
                    <div>
                        <!-- Selector Provider -->
                        <div class="form-group" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                            <label class="control-label">Furnizor AI Principal</label>
                            <select name="<?php echo $fn('api_provider'); ?>" class="form-control api-provider-select" id="<?php echo $id_pfx; ?>api_provider">
                                <option value="openai" <?php selected($current_provider, 'openai'); ?>>OpenAI (GPT)</option>
                                <option value="gemini" <?php selected($current_provider, 'gemini'); ?>>Google Gemini</option>
                                <option value="deepseek" <?php selected($current_provider, 'deepseek'); ?>>DeepSeek V3</option>
                            </select>
                        </div>
                        
                        <!-- Wrapper OpenAI -->
                        <div class="provider-wrapper wrapper-openai" id="<?php echo $id_pfx; ?>wrapper-openai" style="display: <?php echo($current_provider === 'openai' ? 'block' : 'none'); ?>;">
                            <div class="form-group">
                                <label class="control-label">Cheia API OpenAI</label>
                                <input type="password" name="<?php echo $fn('chatgpt_api_key'); ?>"
                                       value="<?php echo esc_attr($openai_key); ?>" class="form-control api-key-input"
                                       id="<?php echo $id_pfx; ?>chatgpt_api_key" placeholder="sk-...">
                            </div>

                            <div class="form-group">
                                <label class="control-label">Model OpenAI</label>
                                <select name="<?php echo $fn('ai_model'); ?>" class="form-control model-select" id="<?php echo $id_pfx; ?>ai_model">
                                    <?php if (!$has_error && !empty($available_models)): ?>
                                        <optgroup label="🌟 Recomandate">
                                            <?php
                                            $recommended_models = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'];
                                        foreach ($recommended_models as $model_id) {
                                            if (isset($available_models[$model_id])) {
                                                $description = self::get_model_description($model_id);
                                                echo "<option value=\"{$model_id}\" " . selected($selected_model, $model_id, false) . ">{$description}</option>";
                                            }
                                        }
                                        ?>
                                        </optgroup>
                                        <optgroup label="📊 Toate modelele">
                                            <?php
                                        foreach ($available_models as $model_id => $model) {
                                            if (!in_array($model_id, $recommended_models)) {
                                                $description = self::get_model_description($model_id);
                                                echo "<option value=\"{$model_id}\" " . selected($selected_model, $model_id, false) . ">{$description}</option>";
                                            }
                                        }
                                        ?>
                                        </optgroup>
                                    <?php else: ?>
                                        <option value="gpt-4o-mini">GPT-4o Mini (Default)</option>
                                    <?php endif; ?>
                                </select>
                                <div class="form-description">
                                    <button type="button" class="btn btn-sm btn-outline-primary refresh-models-btn" data-provider="openai">
                                        🔄 Actualizează lista OpenAI
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Wrapper Gemini -->
                        <div class="provider-wrapper wrapper-gemini" id="<?php echo $id_pfx; ?>wrapper-gemini" style="display: <?php echo($current_provider === 'gemini' ? 'block' : 'none'); ?>;">
                            <div class="form-group">
                                <label class="control-label">Cheia API Google Gemini</label>
                                <input type="password" name="<?php echo $fn('gemini_api_key'); ?>"
                                       value="<?php echo esc_attr($data['gemini_api_key'] ?? ''); ?>" class="form-control api-key-input"
                                       id="<?php echo $id_pfx; ?>gemini_api_key" placeholder="AIza...">
                            </div>
                            <div class="form-group">
                                <label class="control-label">Model Gemini</label>
                                <select name="<?php echo $fn('gemini_model'); ?>" class="form-control model-select" id="<?php echo $id_pfx; ?>gemini_model">
                                    <?php
                                    $gemini_models = get_option('auto_ai_news_poster_gemini_models', ['gemini-1.5-pro' => 'Gemini 1.5 Pro', 'gemini-1.5-flash' => 'Gemini 1.5 Flash']);
        $current_gemini_model = $data['gemini_model'] ?? 'gemini-1.5-pro';
        foreach ($gemini_models as $gid => $gname) {
            echo '<option value="' . esc_attr($gid) . '" ' . selected($current_gemini_model, $gid, false) . '>' . esc_html($gname) . '</option>';
        }
        ?>
                                </select>
                                <div class="form-description">
                                    <button type="button" class="btn btn-sm btn-outline-primary refresh-models-btn" data-provider="gemini">
                                        🔄 Actualizează lista Gemini
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Wrapper DeepSeek -->
                        <div class="provider-wrapper wrapper-deepseek" id="<?php echo $id_pfx; ?>wrapper-deepseek" style="display: <?php echo($current_provider === 'deepseek' ? 'block' : 'none'); ?>;">
                            <div class="form-group">
                                <label class="control-label">Cheia API DeepSeek</label>
                                <input type="password" name="<?php echo $fn('deepseek_api_key'); ?>"
                                       value="<?php echo esc_attr($data['deepseek_api_key'] ?? ''); ?>" class="form-control api-key-input"
                                       id="<?php echo $id_pfx; ?>deepseek_api_key" placeholder="sk-...">
                            </div>
                            <div class="form-group">
                                <label class="control-label">Model DeepSeek</label>
                                <select name="<?php echo $fn('deepseek_model'); ?>" class="form-control model-select" id="<?php echo $id_pfx; ?>deepseek_model">
                                    <?php
        $ds_models = get_option('auto_ai_news_poster_deepseek_models', ['deepseek-chat' => 'DeepSeek V3', 'deepseek-reasoner' => 'DeepSeek R1']);
        $current_ds_model = $data['deepseek_model'] ?? 'deepseek-chat';
        foreach ($ds_models as $did => $dname) {
            echo '<option value="' . esc_attr($did) . '" ' . selected($current_ds_model, $did, false) . '>' . esc_html($dname) . '</option>';
        }
        ?>
                                </select>
                                <div class="form-description">
                                    <button type="button" class="btn btn-sm btn-outline-primary refresh-models-btn" data-provider="deepseek">
                                        🔄 Actualizează lista DeepSeek
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="api-instructions-container" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                    <div class="api-instructions">
                        <h4 class="api-instructions-toggle" onclick="this.nextElementSibling.style.display = (this.nextElementSibling.style.display === 'none' ? 'block' : 'none')">
                            📋 Cum să obțineți cheia API OpenAI <span class="toggle-icon">▼</span>
                        </h4>
                        <div class="api-instructions-content" style="display: none;">
                            <ol>
                                <li>Accesați <a href="https://platform.openai.com" target="_blank">platform.openai.com</a></li>
                                <li>Creați un "Secret Key" în secțiunea API Keys.</li>
                                <li>Copiați cheia și lipiți-o mai sus.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * REUSABLE COMPONENT: Cron Configuration Card
     */
    public static function render_cron_config_component($context = 'main')
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $is_tasks = ($context === 'tasks');
        $data = $is_tasks ? ($options['tasks_config'] ?? []) : $options;
        $name_key = $is_tasks ? 'tasks_config' : '';
        $id_pfx = $is_tasks ? 'tasks_' : '';
        $title = $is_tasks ? 'Configurare Execuție Tasks' : 'Configurare Cron Job';

        $hours = $data['cron_interval_hours'] ?? ($is_tasks ? 0 : 1);
        $minutes = $data['cron_interval_minutes'] ?? ($is_tasks ? 30 : 0);

        $fn = function ($key) use ($name_key) {
            return $name_key ? "auto_ai_news_poster_settings[{$name_key}][{$key}]" : "auto_ai_news_poster_settings[{$key}]";
        };

        ?>
        <div class="settings-card cron-config-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">⏰</div>
                <h3 class="settings-card-title"><?php echo esc_html($title); ?></h3>
            </div>
            <div class="settings-card-content">
                <div class="form-grid" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="control-label">Ore</label>
                        <select name="<?php echo $fn('cron_interval_hours'); ?>" class="form-control">
                            <?php for ($i = 0; $i <= 23; $i++) : ?>
                                <option value="<?php echo $i; ?>" <?php selected($hours, $i); ?>><?php echo $i; ?> ore</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="control-label">Minute</label>
                        <select name="<?php echo $fn('cron_interval_minutes'); ?>" class="form-control">
                            <?php foreach ([0, 1, 2, 5, 10, 15, 20, 30, 45] as $m) : ?>
                                <option value="<?php echo $m; ?>" <?php selected($minutes, $m); ?>><?php echo $m; ?> minute</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ($is_tasks) : ?>
                    <div class="form-group" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                        <label class="control-label">Instrucțiuni AI specifice pentru Tasks</label>
                        <textarea name="<?php echo $fn('ai_instructions'); ?>" class="form-control" rows="3"><?php echo esc_textarea($data['ai_instructions'] ?? ''); ?></textarea>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * REUSABLE COMPONENT: Article Length Configuration Card
     */
    public static function render_article_length_component($context = 'main')
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $is_tasks = ($context === 'tasks');
        $data = $is_tasks ? ($options['tasks_config'] ?? []) : $options;
        $name_key = $is_tasks ? 'tasks_config' : '';

        $selected_option = $data['article_length_option'] ?? 'same_as_source';
        $min_length = $data['min_length'] ?? '';
        $max_length = $data['max_length'] ?? '';

        $fn = function ($key) use ($name_key) {
            return $name_key ? "auto_ai_news_poster_settings[{$name_key}][{$key}]" : "auto_ai_news_poster_settings[{$key}]";
        };

        ?>
        <div class="settings-card article-length-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">📏</div>
                <h3 class="settings-card-title">Dimensiune Articol</h3>
            </div>
            <div class="settings-card-content">
                <div class="form-group">
                    <label class="control-label">Opțiune dimensiune</label>
                    <select name="<?php echo $fn('article_length_option'); ?>" class="form-control">
                        <option value="same_as_source" <?php selected($selected_option, 'same_as_source'); ?>>
                            <?php echo $is_tasks ? 'Dimensiune variabilă (Standard AI)' : 'Aceiași dimensiune cu sursa'; ?>
                        </option>
                        <option value="set_limits" <?php selected($selected_option, 'set_limits'); ?>>Setează limite (cuvinte)</option>
                    </select>
                </div>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                    <div class="form-group">
                        <label class="control-label">Minim</label>
                        <input type="number" name="<?php echo $fn('min_length'); ?>" class="form-control"
                               value="<?php echo esc_attr($min_length); ?>" placeholder="ex: 300">
                    </div>
                    <div class="form-group">
                        <label class="control-label">Maxim</label>
                        <input type="number" name="<?php echo $fn('max_length'); ?>" class="form-control"
                               value="<?php echo esc_attr($max_length); ?>" placeholder="ex: 800">
                    </div>
                </div>
                <small class="form-text text-muted">Valori exprimate în număr aproximativ de cuvinte.</small>
            </div>
        </div>
        <?php
    }
}

Auto_Ai_News_Poster_Settings::init();

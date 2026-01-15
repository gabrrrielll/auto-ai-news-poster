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
        wp_enqueue_style('auto-ai-news-poster-admin-style', plugin_dir_url(__FILE__) . 'css/auto-ai-news-poster.css', [], '1.2.0');

        // Scripturile personalizate ale plugin-ului
        wp_enqueue_script('auto-ai-news-poster-admin-script', plugin_dir_url(__FILE__) . 'js/auto-ai-news-poster-settings.js', ['jquery', 'select2-js'], '1.2.0', true);

        // Localizare script pentru AJAX
        wp_localize_script('auto-ai-news-poster-admin-script', 'auto_ai_news_poster_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'force_refresh_now_nonce' => wp_create_nonce('force_refresh_now_nonce'),
            'check_settings_nonce' => wp_create_nonce('auto_ai_news_poster_check_settings'),
            'clear_transient_nonce' => wp_create_nonce('clear_transient_nonce')
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

        // Camp pentru lista de linkuri bulk
        add_settings_field(
            'bulk_custom_source_urls',
            'Lista de Linkuri Sursă',
            [self::class, 'bulk_custom_source_urls_callback'],
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
        $position = $options['source_photo_position'] ?? 'before';
        ?>
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

                <!-- 2. Generare Automată -->
                <div class="form-group" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <div class="checkbox-modern">
                        <input type="checkbox" name="auto_ai_news_poster_settings[generate_image]" value="yes" <?php checked($generate_image, 'yes'); ?> />
                        <label>Da, generează automat imaginea (dacă nu există)</label>
                    </div>
                </div>

                <!-- 3. Poziție Sursă Foto -->
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
        <?php
    }

    // Callback pentru noul camp "Mod de generare"
    public static function generation_mode_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $generation_mode = $options['generation_mode'] ?? 'parse_link';
        ?>
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">🧠</div>
                <h3 class="settings-card-title">Mod Principal de Operare</h3>
            </div>
            <div class="settings-card-content">
                <div class="form-group">
                    <label class="control-label">Alege cum vrei să generezi articolele</label>
                    <div class="mode-switch">
                        <input type="radio" id="mode_parse_link" name="auto_ai_news_poster_settings[generation_mode]" value="parse_link" <?php checked($generation_mode, 'parse_link'); ?>>
                        <label for="mode_parse_link">Parsare Link</label>

                        <input type="radio" id="mode_ai_browsing" name="auto_ai_news_poster_settings[generation_mode]" value="ai_browsing" <?php checked($generation_mode, 'ai_browsing'); ?>>
                        <label for="mode_ai_browsing">Generare AI</label>
                    </div>
                    <small class="form-text text-muted" style="margin-top: 10px; display: block;">
                        <b>Parsare Link:</b> Plugin-ul va prelua conținut de la un link specific din lista de surse.<br>
                        <b>Generare AI:</b> AI-ul va căuta o știre nouă pe internet, folosind sursele de informare și categoria specificată.
                    </small>
                </div>
            </div>
        </div>
        <?php
    }

    // Callback pentru campul Mod de publicare
    public static function mode_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        ?>
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
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $api_key = $options['chatgpt_api_key'] ?? '';
        $selected_model = $options['ai_model'] ?? DEFAULT_AI_MODEL;

        // Obținem lista de modele disponibile pentru OpenAI
        $available_models = self::get_cached_openai_models($api_key);
        $has_error = isset($available_models['error']);
        $error_message = $has_error ? $available_models['error'] : '';
        $error_type = $has_error ? $available_models['error_type'] : '';
        ?>
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">🔑</div>
                <h3 class="settings-card-title">Configurare API AI</h3>
            </div>
            <div class="settings-card-content">
                <div class="form-grid">
                    <div>
                        <!-- Selector Provider -->
                        <div class="form-group" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                            <?php $current_provider = $options['api_provider'] ?? 'openai'; ?>
                            <label for="api_provider" class="control-label">Furnizor AI Principal</label>
                            <select name="auto_ai_news_poster_settings[api_provider]" class="form-control" id="api_provider">
                                <option value="openai" <?php selected($current_provider, 'openai'); ?>>OpenAI (GPT)</option>
                                <option value="gemini" <?php selected($current_provider, 'gemini'); ?>>Google Gemini</option>
                                <option value="deepseek" <?php selected($current_provider, 'deepseek'); ?>>DeepSeek V3</option>
                            </select>
                        </div>
                        
                        <!-- Wrapper OpenAI -->
                        <div id="wrapper-openai" style="display: <?php echo ($current_provider === 'openai' ? 'block' : 'none'); ?>;">
                            <div class="form-group">
                                <label for="chatgpt_api_key" class="control-label">Cheia API OpenAI</label>
                                <input type="password" name="auto_ai_news_poster_settings[chatgpt_api_key]"
                                       value="<?php echo esc_attr($api_key); ?>" class="form-control"
                                       id="chatgpt_api_key" placeholder="sk-..." onchange="refreshModelsList()">
                                <span class="info-icon tooltip">
                                    i
                                    <span class="tooltiptext">Platform: platform.openai.com</span>
                                </span>
                            </div>

                            <div class="form-group">
                                <label for="ai_model" class="control-label">Model OpenAI</label>
                                <select name="auto_ai_news_poster_settings[ai_model]" class="form-control" id="ai_model">
                                    <?php if (!$has_error && !empty($available_models)): ?>
                                        <optgroup label="🌟 Recomandate">
                                            <?php
                                            $recommended_models = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'];
                                            foreach ($recommended_models as $model_id) {
                                                if (isset($available_models[$model_id])) {
                                                    $model = $available_models[$model_id];
                                                    $description = self::get_model_description($model_id);
                                                    $selected = selected($selected_model, $model_id, false);
                                                    echo "<option value=\"{$model_id}\" {$selected}>{$description}</option>";
                                                }
                                            }
                                            ?>
                                        </optgroup>
                                        <optgroup label="📊 Toate modelele disponibile">
                                            <?php
                                            foreach ($available_models as $model_id => $model) {
                                                if (!in_array($model_id, $recommended_models)) {
                                                    $description = self::get_model_description($model_id);
                                                    $selected = selected($selected_model, $model_id, false);
                                                    echo "<option value=\"{$model_id}\" {$selected}>{$description}</option>";
                                                }
                                            }
                                            ?>
                                        </optgroup>
                                    <?php else: ?>
                                        <option value="" disabled>
                                            <?php if ($has_error): ?>
                                                ❌ Eroare la încărcarea modelelor
                                            <?php else: ?>
                                                ⏳ Se încarcă modelele...
                                            <?php endif; ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                
                                <?php if ($has_error): ?>
                                    <div class="alert alert-danger" style="margin-top: 10px; padding: 10px; background: #fee; border: 1px solid #fcc; border-radius: 4px;">
                                        <strong>❌ Eroare la încărcarea modelelor OpenAI:</strong> <?php echo esc_html($error_message); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="form-description">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshModelsList()" style="margin-top: 5px;">
                                        🔄 Actualizează lista OpenAI
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Wrapper Gemini -->
                        <div id="wrapper-gemini" style="display: <?php echo ($current_provider === 'gemini' ? 'block' : 'none'); ?>;">
                            <div class="form-group">
                                <label for="gemini_api_key" class="control-label">Cheia API Google Gemini</label>
                                <input type="password" name="auto_ai_news_poster_settings[gemini_api_key]"
                                       value="<?php echo esc_attr($options['gemini_api_key'] ?? ''); ?>" class="form-control"
                                       id="gemini_api_key" placeholder="AIza...">
                                <span class="info-icon tooltip">
                                    i
                                    <span class="tooltiptext">Platform: aistudio.google.com</span>
                                </span>
                            </div>
                            <div class="form-group">
                                <label for="gemini_model" class="control-label">Model Gemini</label>
                                <select name="auto_ai_news_poster_settings[gemini_model]" class="form-control" id="gemini_model">
                                    <?php 
                                    $gemini_models = get_option('auto_ai_news_poster_gemini_models', [
                                        'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental)',
                                        'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                                        'gemini-1.5-flash' => 'Gemini 1.5 Flash',
                                        'gemini-pro' => 'Gemini 1.0 Pro',
                                    ]);
                                    // If for some reason options saved badly, revert to defaults
                                    if (!is_array($gemini_models) || empty($gemini_models)) {
                                          $gemini_models = [
                                            'gemini-1.5-pro' => 'Gemini 1.5 Pro (Fallback)',
                                            'gemini-1.5-flash' => 'Gemini 1.5 Flash (Fallback)'
                                          ];
                                    }

                                    $current_gemini_model = $options['gemini_model'] ?? 'gemini-1.5-pro';
                                    
                                    // Group if possible, or just list
                                    foreach ($gemini_models as $gid => $gname) {
                                        echo '<option value="' . esc_attr($gid) . '" ' . selected($current_gemini_model, $gid, false) . '>' . esc_html($gname) . '</option>';
                                    }
                                    ?>
                                </select>
                                <div class="form-description">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshGeminiModels()" style="margin-top: 5px;">
                                        🔄 Actualizează lista Gemini
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Wrapper DeepSeek -->
                        <div id="wrapper-deepseek" style="display: <?php echo ($current_provider === 'deepseek' ? 'block' : 'none'); ?>;">
                            <div class="form-group">
                                <label for="deepseek_api_key" class="control-label">Cheia API DeepSeek</label>
                                <input type="password" name="auto_ai_news_poster_settings[deepseek_api_key]"
                                       value="<?php echo esc_attr($options['deepseek_api_key'] ?? ''); ?>" class="form-control"
                                       id="deepseek_api_key" placeholder="sk-...">
                                <span class="info-icon tooltip">
                                    i
                                    <span class="tooltiptext">Platform: deepseek.com</span>
                                </span>
                            </div>
                            <div class="form-group">
                                <label for="deepseek_model" class="control-label">Model DeepSeek</label>
                                <select name="auto_ai_news_poster_settings[deepseek_model]" class="form-control" id="deepseek_model">
                                    <?php 
                                    $ds_models = get_option('auto_ai_news_poster_deepseek_models', [
                                        'deepseek-chat' => 'DeepSeek V3 (Chat)',
                                        'deepseek-coder' => 'DeepSeek Coder V2',
                                    ]);
                                     // Fallback
                                    if (!is_array($ds_models) || empty($ds_models)) {
                                          $ds_models = [
                                            'deepseek-chat' => 'DeepSeek V3 (Fallback)',
                                            'deepseek-coder' => 'DeepSeek Coder (Fallback)'
                                          ];
                                    }
                                    $current_ds_model = $options['deepseek_model'] ?? 'deepseek-chat';
                                    foreach ($ds_models as $did => $dname) {
                                        echo '<option value="' . esc_attr($did) . '" ' . selected($current_ds_model, $did, false) . '>' . esc_html($dname) . '</option>';
                                    }
                                    ?>
                                </select>
                                <div class="form-description">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshDeepSeekModels()" style="margin-top: 5px;">
                                        🔄 Actualizează lista DeepSeek
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="api-instructions-container" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                    <!-- OpenAI Instructions -->
                    <div class="api-instructions">
                        <h4 class="api-instructions-toggle" onclick="toggleInstructions('openai-instructions')">
                            📋 Cum să obțineți cheia API OpenAI <span class="toggle-icon">▼</span>
                        </h4>
                        <div class="api-instructions-content" id="openai-instructions" style="display: none;">
                            <ol>
                                <li><strong>Accesați</strong> <a href="https://platform.openai.com" target="_blank">https://platform.openai.com</a></li>
                                <li><strong>Vă înregistrați</strong> sau vă autentificați în contul OpenAI</li>
                                <li><strong>Navigați</strong> la <a href="https://platform.openai.com/api-keys" target="_blank">API Keys</a></li>
                                <li><strong>Faceți click</strong> pe "Create new secret key"</li>
                                <li><strong>Copiați</strong> cheia generată (începe cu "sk-")</li>
                                <li><strong>Lipiți</strong> cheia în câmpul OpenAI de mai sus</li>
                            </ol>
                            <div class="api-warning">
                                <strong>⚠️ Important:</strong> Asigurați-vă că aveți credit disponibil în contul OpenAI.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function toggleInstructions(id) {
            const content = document.getElementById(id);
            // Găsim iconița din interiorul header-ului care a declanșat evenimentul (părintele lui content nu e header-ul, ci sibling anterior)
            // Dar mai simplu, luăm elementul clicat din event sau căutăm sibling-ul anterior al content-ului
            const header = content.previousElementSibling;
            const icon = header.querySelector('.toggle-icon');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.textContent = '▲';
            } else {
                content.style.display = 'none';
                icon.textContent = '▼';
            }
        }

        
        function refreshModelsList() {
            const apiKey = document.getElementById('chatgpt_api_key').value;
            const modelSelect = document.getElementById('ai_model');
            
            if (!apiKey) {
                alert('Vă rugăm să introduceți mai întâi cheia API OpenAI.');
                return;
            }
            
            // Afișăm indicator de încărcare
            const refreshBtn = document.querySelector('button[onclick="refreshModelsList()"]');
            const originalText = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '⏳ Se încarcă...';
            refreshBtn.disabled = true;
            
            // Facem apel AJAX pentru a actualiza lista
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'refresh_openai_models',
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce('refresh_models_nonce'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reîncărcăm pagina pentru a afișa noile modele
                    location.reload();
                } else {
                    alert('Eroare la actualizarea listei de modele: ' + (data.data || 'Eroare necunoscută'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Eroare la actualizarea listei de modele.');
            })
            .finally(() => {
                refreshBtn.innerHTML = originalText;
                refreshBtn.disabled = false;
            });
        }



        function refreshGeminiModels() {
            const apiKey = document.getElementById('gemini_api_key').value;
            if (!apiKey) { alert('Introduceți cheia API Gemini.'); return; }
            
            const btn = document.querySelector('button[onclick="refreshGeminiModels()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ ...'; btn.disabled = true;

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'refresh_gemini_models',
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce('refresh_gemini_models_nonce'); ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) { location.reload(); }
                else { alert('Eroare Gemini: ' + data.data); }
            })
            .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
        }

        function refreshDeepSeekModels() {
            const apiKey = document.getElementById('deepseek_api_key').value;
            if (!apiKey) { alert('Introduceți cheia API DeepSeek.'); return; }

            const btn = document.querySelector('button[onclick="refreshDeepSeekModels()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ ...'; btn.disabled = true;

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'refresh_deepseek_models',
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce('refresh_deepseek_models_nonce'); ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) { location.reload(); }
                else { alert('Eroare DeepSeek: ' + data.data); }
            })
            .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
        }

        // Logică pentru schimbarea providerului și afișarea câmpurilor relevante
        document.addEventListener('DOMContentLoaded', function() {
            const providerSelect = document.getElementById('api_provider');
            const openAIGroup = document.getElementById('wrapper-openai');
            const geminiGroup = document.getElementById('wrapper-gemini');
            const deepseekGroup = document.getElementById('wrapper-deepseek');
            
            function updateVisibility() {
                const provider = providerSelect.value;
                
                // Hide all first
                if(openAIGroup) openAIGroup.style.display = 'none';
                if(geminiGroup) geminiGroup.style.display = 'none';
                if(deepseekGroup) deepseekGroup.style.display = 'none';
                
                // Show selected
                if (provider === 'openai' && openAIGroup) openAIGroup.style.display = 'block';
                if (provider === 'gemini' && geminiGroup) geminiGroup.style.display = 'block';
                if (provider === 'deepseek' && deepseekGroup) deepseekGroup.style.display = 'block';
            }
            
            if(providerSelect) {
                providerSelect.addEventListener('change', updateVisibility);
                updateVisibility(); // Run on load
            }
        });
        </script>
        <?php
    }

    // Callback pentru setarea intervalului cron
    public static function cron_interval_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $hours = $options['cron_interval_hours'] ?? 1;
        $minutes = $options['cron_interval_minutes'] ?? 0;
        ?>
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">⏰</div>
                <h3 class="settings-card-title">Configurare Cron Job</h3>
            </div>
            <div class="settings-card-content">
                <div class="form-grid">
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
                </div>
            </div>
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
        <?php
    }

    // Select pentru dimensiunea articolului
    public static function article_length_option_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $selected_option = $options['article_length_option'] ?? 'same_as_source';
        $min_length = $options['min_length'] ?? '';
        $max_length = $options['max_length'] ?? '';

        ?>
        <div class="settings-group">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">📏</div>
                    <h3 class="settings-card-title">Configurare Dimensiune Articol</h3>
                </div>
                <div class="settings-card-content">
                    <div class="form-group">
                        <label class="control-label">Selectează dimensiunea articolului</label>
                        <select name="auto_ai_news_poster_settings[article_length_option]" class="form-control">
                            <option value="same_as_source" <?php selected($selected_option, 'same_as_source'); ?>>Aceiași dimensiune cu articolul preluat</option>
                            <option value="set_limits" <?php selected($selected_option, 'set_limits'); ?>>Setează limite</option>
                        </select>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="control-label">Lungime minimă</label>
                            <input type="number" name="auto_ai_news_poster_settings[min_length]" class="form-control"
                                   value="<?php echo esc_attr($min_length); ?>" placeholder="Minim">
                        </div>
                        <div class="form-group">
                            <label class="control-label">Lungime maximă</label>
                            <input type="number" name="auto_ai_news_poster_settings[max_length]" class="form-control"
                                   value="<?php echo esc_attr($max_length); ?>" placeholder="Maxim">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }




    public static function bulk_custom_source_urls_callback()
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $bulk_links = $options['bulk_custom_source_urls'] ?? '';
        ?>
        <div class="settings-group settings-group-parse_link">
            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="settings-card-icon">🔗</div>
                    <h3 class="settings-card-title">Lista de Linkuri Sursă pentru Parsare</h3>
                </div>
                <div class="settings-card-content">
                    <div class="form-group">
                        <label class="control-label">Lista de linkuri sursă personalizate</label>
                        <textarea name="auto_ai_news_poster_settings[bulk_custom_source_urls]" class="form-control" rows="6" placeholder="Introduceți câte un link pe fiecare rând"><?php echo esc_textarea($bulk_links); ?></textarea>
                        <small class="form-text text-muted">Introduceți o listă de linkuri sursă. Acestea vor fi folosite automat sau manual pentru generarea articolelor.</small>
                    </div>
                </div>
            </div>
        </div>
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
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($api_key) . '&pageSize=1000';

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

        // Organizează modelele într-un array asociativ
        // Folosim numele modelului fără prefixul "models/" ca cheie pentru compatibilitate
        $models_array = [];
        foreach ($filtered_models as $model) {
            $model_name = $model['name'] ?? '';
            // Eliminăm prefixul "models/" pentru a păstra compatibilitatea cu setările existente
            $clean_name = str_replace('models/', '', $model_name);
            $models_array[$clean_name] = $model;
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
        $response = wp_remote_get('https://api.deepseek.com/models', [
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
        $checkbox_fields = ['auto_rotate_categories', 'generate_image',
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
                // Pentru textarea, folosim o sanitizare specifică
                elseif ($key === 'news_sources' || $key === 'parse_link_ai_instructions' || $key === 'ai_browsing_instructions' || $key === 'bulk_custom_source_urls') {
                    $sanitized[$key] = esc_textarea($value);
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

}

Auto_Ai_News_Poster_Settings::init();

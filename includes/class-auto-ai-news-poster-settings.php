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
        <div class="auto-ai-news-poster-admin">
            <div class="wrap">
                <!-- Header modern -->
                <div class="auto-ai-news-poster-header">
                    <h1>🤖 Auto AI News Poster</h1>
                    <p>Configurează-ți plugin-ul pentru publicarea automată de articole AI</p>
                </div>
                
                <!-- Formular modern -->
                <div class="auto-ai-news-poster-form">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('auto_ai_news_poster_settings_group');
        do_settings_sections('auto_ai_news_poster_settings_page');
        ?>
                        
                        <!-- Buton de salvare modern -->
                        <div style="text-align: center; margin-top: 40px; padding-top: 30px; border-top: 2px solid var(--border-color);">
                            <button type="submit" class="btn btn-primary">
                                💾 Salvează setările
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public static function register_settings()
    {
        register_setting('auto_ai_news_poster_settings_group', 'auto_ai_news_poster_settings', [
            'sanitize_callback' => [self::class, 'sanitize_checkbox_settings']
        ]);

        add_settings_section('main_section', 'Main Settings', null, 'auto_ai_news_poster_settings_page');

        // Camp pentru selectarea modului de generare (AI Browsing vs. Parsare Link)
        add_settings_field(
            'generation_mode',
            'Mod de generare',
            [self::class, 'generation_mode_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru selectarea modului de publicare
        add_settings_field(
            'mode',
            'Mod de publicare',
            [self::class, 'mode_callback'],
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

        // Camp pentru instructiuni AI (textarea) - Mod Parsare Link
        add_settings_field(
            'parse_link_ai_instructions',
            'Instrucțiuni AI (Parsare Link)',
            [self::class, 'parse_link_ai_instructions_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru instructiuni AI (textarea) - Mod AI Browsing
        add_settings_field(
            'ai_browsing_instructions',
            'Instrucțiuni AI (AI Browsing)',
            [self::class, 'ai_browsing_instructions_callback'],
            'auto_ai_news_poster_settings_page',
            'main_section'
        );

        // Camp pentru controlul generării etichetelor
        add_settings_field(
            'generate_tags',
            'Generează etichete',
            [self::class, 'generate_tags_callback'],
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

    // Callback pentru noul camp "Mod de generare"
    public static function generation_mode_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key'] ?? '';
        $selected_model = $options['ai_model'] ?? 'gpt-4o';

        // Obținem lista de modele disponibile
        $available_models = self::get_cached_openai_models($api_key);
        $has_error = isset($available_models['error']);
        $error_message = $has_error ? $available_models['error'] : '';
        $error_type = $has_error ? $available_models['error_type'] : '';
        ?>
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">🔑</div>
                <h3 class="settings-card-title">Configurare API</h3>
            </div>
            <div class="settings-card-content">
                <div class="form-group">
                    <label for="chatgpt_api_key" class="control-label">Cheia API OpenAI</label>
                    <input type="password" name="auto_ai_news_poster_settings[chatgpt_api_key]"
                           value="<?php echo esc_attr($api_key); ?>" class="form-control"
                           id="chatgpt_api_key" placeholder="sk-..." onchange="refreshModelsList()">
                    <span class="info-icon tooltip">
                        i
                        <span class="tooltiptext">Pentru a obține cheia API OpenAI, accesați https://platform.openai.com/settings/organization/api-keys</span>
                    </span>
                </div>
                
                <div class="form-group">
                    <label for="ai_model" class="control-label">Model AI</label>
                    <select name="auto_ai_news_poster_settings[ai_model]" class="form-control" id="ai_model">
                        <?php if (!$has_error && !empty($available_models)): ?>
                            <optgroup label="🌟 Recomandate">
                                <?php
                                $recommended_models = ['gpt-5', 'gpt-5-mini', 'gpt-4o', 'gpt-4o-mini'];
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
                            <strong>❌ Eroare la încărcarea modelelor:</strong><br>
                            <strong>Motivul:</strong> <?php echo esc_html($error_message); ?><br>
                            <strong>Tipul erorii:</strong> <?php echo esc_html($error_type); ?><br>
                            <small>Verificați cheia API și încercați din nou.</small>
                        </div>
                    <?php endif; ?>
                    
                <div class="form-description">
                    <?php if (!$has_error && !empty($available_models)): ?>
                        ✅ Lista de modele este actualizată dinamic din API-ul OpenAI. 
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshModelsList()" style="margin-left: 10px;">
                            🔄 Actualizează lista
                        </button>
                    <?php elseif ($has_error): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshModelsList()">
                            🔄 Încearcă din nou
                        </button>
                    <?php else: ?>
                        Introduceți cheia API pentru a vedea toate modelele disponibile.
                    <?php endif; ?>
                </div>
                
                <!-- Debug info pentru cron job -->
                <div class="form-group" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007cba;">
                    <h4 style="margin: 0 0 10px 0; color: #007cba;">🔧 Debug Info - Cron Job</h4>
                    <?php
                    $next_scheduled = wp_next_scheduled('auto_ai_news_poster_cron_hook');
        $settings = get_option('auto_ai_news_poster_settings', []);
        $mode = $settings['mode'] ?? 'manual';
        ?>
                    <p><strong>Modul curent:</strong> <?php echo esc_html($mode); ?></p>
                    <p><strong>Cron job programat:</strong> <?php echo $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'NU este programat'; ?></p>
                    <p><strong>Următoarea execuție:</strong> <?php echo $next_scheduled ? human_time_diff($next_scheduled) . ' de acum' : 'N/A'; ?></p>
                    <p><strong>Interval cron:</strong> <?php echo esc_html($settings['cron_interval_hours'] ?? 1); ?> ore, <?php echo esc_html($settings['cron_interval_minutes'] ?? 0); ?> minute</p>
                    
                    <?php if ($mode === 'auto' && !$next_scheduled): ?>
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <strong>⚠️ Atenție:</strong> Modul este setat pe automat dar cron job-ul nu este programat! 
                            <button type="button" class="btn btn-sm btn-warning" onclick="location.reload()" style="margin-left: 10px;">
                                🔄 Reîncarcă pagina
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                </div>
                
                <div class="api-instructions">
                    <h4 class="api-instructions-toggle" onclick="toggleApiInstructions()">
                        📋 Cum să obțineți cheia API OpenAI: <span class="toggle-icon">▼</span>
                    </h4>
                    <div class="api-instructions-content" id="api-instructions-content" style="display: none;">
                        <ol>
                            <li><strong>Accesați</strong> <a href="https://platform.openai.com" target="_blank">https://platform.openai.com</a></li>
                            <li><strong>Vă înregistrați</strong> sau vă autentificați în contul OpenAI</li>
                            <li><strong>Navigați</strong> la <a href="https://platform.openai.com/api-keys" target="_blank">API Keys</a></li>
                            <li><strong>Faceți click</strong> pe "Create new secret key"</li>
                            <li><strong>Copiați</strong> cheia generată (începe cu "sk-")</li>
                            <li><strong>Lipiți</strong> cheia în câmpul de mai sus</li>
                        </ol>
                        
                        <div class="api-warning">
                            <strong>⚠️ Important:</strong>
                            <ul>
                                <li>Cheia API este confidențială - nu o partajați cu nimeni</li>
                                <li>Asigurați-vă că aveți credit disponibil în contul OpenAI</li>
                                <li>Cheia va fi folosită pentru generarea articolelor și imaginilor</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function toggleApiInstructions() {
            const content = document.getElementById('api-instructions-content');
            const icon = document.querySelector('.toggle-icon');
            
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
        </script>
        <?php
    }

    // Callback pentru setarea intervalului cron
    public static function cron_interval_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
        $instructions = $options['ai_browsing_instructions'] ?? 'Scrie un articol de știre original, în limba română, de 300-500 de cuvinte. Articolul trebuie să fie obiectiv, informativ și bine structurat (introducere, cuprins, încheiere).';
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
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
        $selected_option = $options['article_length_option'] ?? 'same_as_source';
        $min_length = $options['min_length'] ?? '';
        $max_length = $options['max_length'] ?? '';

        ?>
        <div class="settings-group settings-group-parse_link">
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


    // Callback pentru selectarea modului de imagine (externă/importată)
    public static function use_external_images_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        $use_external_images = $options['use_external_images'] ?? 'external';
        ?>
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">🖼️</div>
                <h3 class="settings-card-title">Configurare Imagini</h3>
            </div>
            <div class="settings-card-content">
                <div class="form-group">
                    <label for="use_external_images" class="control-label">Folosire imagini:</label>
                    <select name="auto_ai_news_poster_settings[use_external_images]" class="form-control" id="use_external_images">
                        <option value="external" <?php selected($use_external_images, 'external'); ?>>Folosește imagini externe</option>
                        <option value="import" <?php selected($use_external_images, 'import'); ?>>Importă imagini în WordPress</option>
                    </select>
                </div>
            </div>
        </div>
        <?php
    }


    // Callback pentru opțiunea de generare automată a imaginii
    public static function generate_image_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
        ?>
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">🎨</div>
                <h3 class="settings-card-title">Generare Automată Imagini</h3>
            </div>
            <div class="settings-card-content">
                <div class="checkbox-modern">
                    <input type="checkbox" name="auto_ai_news_poster_settings[generate_image]" value="yes" <?php checked($options['generate_image'], 'yes'); ?> />
                    <label>Da, generează automat imaginea</label>
                </div>
            </div>
        </div>
        <?php
    }

    public static function bulk_custom_source_urls_callback()
    {
        $options = get_option('auto_ai_news_poster_settings');
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
        $options = get_option('auto_ai_news_poster_settings');
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

    // Handler AJAX pentru actualizarea listei de modele
    public static function ajax_refresh_openai_models()
    {
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

    // Funcție simplă pentru sanitizarea doar a checkbox-urilor
    public static function sanitize_checkbox_settings($input)
    {
        // Obținem setările existente
        $existing_options = get_option('auto_ai_news_poster_settings', []);

        // Păstrăm toate setările existente
        $sanitized = $existing_options;

        // Lista checkbox-urilor care trebuie să fie setate explicit
        $checkbox_fields = ['auto_rotate_categories', 'generate_image',
                           'run_until_bulk_exhausted', 'generate_tags'];

        // Câmpurile de tip <select> care trebuie validate
        $select_fields = ['mode', 'status', 'specific_search_category', 'author_name', 'article_length_option', 'use_external_images', 'ai_model', 'generation_mode'];

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

        return $sanitized;
    }

}

Auto_Ai_News_Poster_Settings::init();

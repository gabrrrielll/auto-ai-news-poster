<?php

class Auto_Ai_News_Poster_Metabox
{
    public static function init()
    {
        // Adăugăm metabox-ul pentru "Get Article from Sources"
        add_action('add_meta_boxes', [self::class, 'add_get_article_metabox']);

        // Adăugăm metabox-ul pentru URL-ul imaginii externe
        add_action('add_meta_boxes', [self::class, 'add_external_image_metabox']);

        // Aplicăm editorul TinyMCE pe câmpul implicit de rezumat al WordPress
        add_action('admin_init', [self::class, 'customize_excerpt_editor']);

        // Salvăm datele metaboxurilor
        add_action('save_post', [self::class, 'save_article_data']);

    }

    public static function add_get_article_metabox()
    {
        // Adăugăm metabox-ul "Get Article from Sources"
        add_meta_box(
            'auto_ai_news_poster_get_article',
            'Get Article from Sources',
            [self::class, 'render_get_article_metabox'],
            'post',
            'side',
            'high',
            ['class' => 'auto-ai-news-poster-metabox']
        );
    }

    public static function render_get_article_metabox($post)
    {
        // Preluăm linkul salvat anterior (dacă există)
        $custom_source_url = get_post_meta($post->ID, '_custom_source_url', true);
        $additional_instructions = get_post_meta($post->ID, '_additional_instructions', true);
        $current_generation_mode = get_post_meta($post->ID, '_generation_mode_metabox', true);
        if (empty($current_generation_mode)) {
            $current_generation_mode = 'parse_link'; // Default value
        }

        // Adăugăm nonce-ul pentru securitate
        wp_nonce_field('get_article_from_sources_nonce', 'get_article_from_sources_nonce');
        ?>
        <div class="inside">
            <div class="metabox-section">
                <div class="metabox-section-header">
                    <span class="metabox-section-icon">⚙️</span>
                    <h4 class="metabox-section-title">Mod de Generare</h4>
                </div>
                <div class="mode-switch-container">
                    <label class="mode-switch-label">
                        <input type="radio" name="generation_mode_metabox" value="parse_link" <?php checked('parse_link', $current_generation_mode); ?>>
                        Parsează link
                    </label>
                    <label class="mode-switch-label">
                        <input type="radio" name="generation_mode_metabox" value="ai_browsing" <?php checked('ai_browsing', $current_generation_mode); ?>>
                        Browsing cu AI
                    </label>
                </div>
            </div>
            <div class="metabox-section">
                <div class="metabox-section-header">
                    <span class="metabox-section-icon">🤖</span>
                    <h4 class="metabox-section-title">Instrucțiuni suplimentare pentru AI</h4>
                </div>
                <textarea id="additional-instructions" name="additional_instructions" class="metabox-textarea" 
                          placeholder="Introduceți instrucțiuni suplimentare pentru AI..."><?php echo esc_textarea($additional_instructions); ?></textarea>
            </div>
            
            <div class="metabox-section">
                <div class="metabox-section-header">
                    <span class="metabox-section-icon">🔗</span>
                    <h4 class="metabox-section-title">Link sursă personalizată</h4>
                </div>
                <textarea id="custom-source-url" name="custom_source_url" class="metabox-textarea" 
                          placeholder="Introduceți un link sursă pentru a genera articolul..."><?php echo esc_url($custom_source_url); ?></textarea>
            </div>
            
            <button id="get-article-button" type="button" class="metabox-generate-btn">
                <span>✨</span>
                Generează articol
            </button>
        </div>
        
        <script type="text/javascript">
        // Configure AJAX object for metabox
        if (typeof autoAiNewsPosterAjax === 'undefined') {
            window.autoAiNewsPosterAjax = {
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                admin_url: '<?php echo admin_url(); ?>',
                get_article_nonce: '<?php echo wp_create_nonce('get_article_from_sources_nonce'); ?>',
                generate_image_nonce: '<?php echo wp_create_nonce('generate_image_nonce'); ?>',
                get_generation_mode: function() {
                    const selectedMode = document.querySelector('input[name="generation_mode_metabox"]:checked');
                    return selectedMode ? selectedMode.value : 'parse_link'; // Default to parse_link
                }
            };
        }
        </script>
        <?php
    }


    public static function add_external_image_metabox()
    {
        // Adăugăm metabox-ul pentru introducerea unui URL pentru imaginea externă
        add_meta_box(
            'auto_ai_news_poster_external_image',
            'Imagine reprezentativă externă',
            [self::class, 'render_external_image_metabox'],
            'post',
            'side',
            'high',
            ['class' => 'auto-ai-news-poster-metabox'] // Adaugăm clasa custom pentru stilizare
        );
    }

    public static function render_external_image_metabox($post)
    {
        // Preluăm URL-ul imaginii reprezentative externe dacă există
        $external_image_url = get_post_meta($post->ID, '_external_image_url', true);
        $external_image_source = get_post_meta($post->ID, '_external_image_source', true);
        $external_image_source_position = get_post_meta($post->ID, '_external_image_source_position', true);
        if (empty($external_image_source_position)) {
             // Dacă nu avem o poziție salvată, preluăm setarea globală
             $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
             $external_image_source_position = $options['source_photo_position'] ?? 'before';
        }
        ?>
        <div class="inside auto-ai-metabox-content">
            <div class="metabox-field-group">
                <label for="external_image_url" class="metabox-label">
                    <span class="metabox-icon">🖼️</span>
                    URL imagine reprezentativă externă
                </label>
                <input type="text" name="external_image_url" id="external_image_url" class="metabox-input"
                       value="<?php echo esc_url($external_image_url); ?>" placeholder="Introduceți URL-ul imaginii..."/>
                <p class="metabox-description">Dacă adăugați un URL, acesta va fi folosit ca imagine reprezentativă pentru acest articol.</p>
            </div>

            <div class="metabox-field-group">
                <label for="external_image_source" class="metabox-label">
                    <span class="metabox-icon">📝</span>
                    Sursa imaginii
                </label>
                <input type="text" id="external_image_source" name="external_image_source" value="<?php echo esc_attr($external_image_source); ?>" class="metabox-input" placeholder="Sursa imaginii (de ex: Digi24)">
                <p class="metabox-description">Acest text va fi afișat în articol ca „Sursa foto: ...”.</p>
                <div class="metabox-subsection">
                    <div class="metabox-subsection-title">
                        <span class="metabox-icon">↕️</span>
                        Poziție afișare „Sursa foto”
                    </div>
                    <div class="mode-switch-container">
                        <label class="mode-switch-label">
                            <input type="radio" name="external_image_source_position" value="before" <?php checked('before', $external_image_source_position); ?>>
                            Înainte de articol
                        </label>
                        <label class="mode-switch-label">
                            <input type="radio" name="external_image_source_position" value="after" <?php checked('after', $external_image_source_position); ?>>
                            După articol
                        </label>
                    </div>
                </div>
            </div>


            
            <div class="metabox-field-group">
                <label for="feedback-text" class="metabox-label">
                    <span class="metabox-icon">💬</span>
                    Feedback pentru imaginea generată
                </label>
                <textarea id="feedback-text" class="metabox-textarea" placeholder="Introduceți feedback pentru imaginea generată..."></textarea>
            </div>
           
            <button id="generate-image-button" type="button" class="metabox-button metabox-button-primary">
                <span class="button-icon">🎨</span>
                Generează imagine AI
            </button>
        </div>
        <?php
    }

    public static function customize_excerpt_editor()
    {
        // Verificăm dacă postarea curentă suportă rezumatul
        if (post_type_supports('post', 'excerpt')) {
            // Eliminăm metabox-ul implicit pentru rezumat
            remove_meta_box('postexcerpt', 'post', 'normal');

            // Adăugăm metabox-ul cu editor TinyMCE pentru rezumat
            add_meta_box(
                'postexcerpt',
                __('Rezumat'),
                [self::class, 'render_excerpt_tinymce'],
                'post',
                'normal',
                'high'
            );
        }
    }

    public static function render_excerpt_tinymce($post)
    {
        // Preluăm rezumatul existent
        $excerpt = get_post_meta($post->ID, '_wp_excerpt', true) ?: $post->post_excerpt;

        // Afișăm editorul TinyMCE pentru rezumatul implicit
        wp_editor($excerpt, 'excerpt', [
            'textarea_name' => 'excerpt',
            'media_buttons' => false, // Nu permitem butoane media
            'textarea_rows' => 10,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'bold italic underline | bullist numlist | alignleft aligncenter alignright | link unlink',
                'toolbar2' => 'formatselect | blockquote',
            ],
        ]);
    }

    public static function save_article_data($post_id)
    {
        // Salvăm rezumatul la salvarea postării
        if (array_key_exists('excerpt', $_POST)) {
            update_post_meta($post_id, '_wp_excerpt', sanitize_text_field($_POST['excerpt']));
        }

        // Salvăm URL-ul sursei personalizate
        if (isset($_POST['custom_source_url'])) {
            update_post_meta($post_id, '_custom_source_url', esc_url_raw($_POST['custom_source_url']));
        }

        // Salvăm instrucțiunile suplimentare pentru AI
        if (isset($_POST['additional_instructions'])) {
            update_post_meta($post_id, '_additional_instructions', sanitize_textarea_field($_POST['additional_instructions']));
        }

        // Salvăm modul de generare selectat în metabox
        if (isset($_POST['generation_mode_metabox'])) {
            update_post_meta($post_id, '_generation_mode_metabox', sanitize_text_field($_POST['generation_mode_metabox']));
        }

        // Salvăm URL-ul imaginii externe
        if (isset($_POST['external_image_url'])) {
            update_post_meta($post_id, '_external_image_url', esc_url_raw($_POST['external_image_url']));

            // Setăm imaginea reprezentativă doar dacă avem un URL valid
            $image_url = esc_url_raw($_POST['external_image_url']);
            if (!empty($image_url)) {
                Post_Manager::set_featured_image($post_id, $image_url);
            }

            if (isset($_POST['external_image_source'])) {
                update_post_meta($post_id, '_external_image_source', sanitize_text_field($_POST['external_image_source']));
            }


        }

        // Salvăm poziția pentru afișarea "Sursa foto"
        if (isset($_POST['external_image_source_position'])) {
            $pos = sanitize_text_field($_POST['external_image_source_position']);
            if (!in_array($pos, ['before', 'after'], true)) {
                $pos = 'before';
            }
            update_post_meta($post_id, '_external_image_source_position', $pos);
        }
    }

}

Auto_Ai_News_Poster_Metabox::init();

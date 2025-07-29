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

        // Adăugăm metabox-ul pentru imaginea reprezentativă
        add_action('add_meta_boxes', [self::class, 'add_featured_image_metabox']);
    }

    // Funcție pentru adăugarea metabox-ului imaginii reprezentative
    public static function add_featured_image_metabox()
    {
        add_meta_box(
            'auto_ai_news_poster_featured_image',
            'Imagine reprezentativă AI',
            [self::class, 'render_featured_image_metabox'],
            'post',
            'side',
            'high'
        );
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
            'high'
        );
    }

    public static function render_get_article_metabox($post)
    {
        // Preluăm linkul salvat anterior (dacă există)
        $custom_source_url = get_post_meta($post->ID, '_custom_source_url', true);
        ?>
        <div class="inside">
            <textarea id="additional-instructions" class="widefat" 
                      placeholder="Introduceți instrucțiuni suplimentare pentru AI..."></textarea>
            
            <label for="custom-source-url" style="margin-top: 10px;">Link sursă personalizată</label>
            <textarea id="custom-source-url" name="custom_source_url" class="widefat" 
                      placeholder="Introduceți un link sursă pentru a genera articolul..."><?php echo esc_url($custom_source_url); ?></textarea>
            
            <button id="get-article-button" type="button" class="button button-primary"
                    style="width: 100%; margin-top: 10px;">
                Generează articol
            </button>
        </div>
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
            'high'
        );
    }

    public static function render_external_image_metabox($post)
    {
        // Preluăm URL-ul imaginii reprezentative externe dacă există
        $external_image_url = get_post_meta($post->ID, '_external_image_url', true);
        $external_image_source = get_post_meta($post->ID, '_external_image_source', true);
        ?>
        <div class="inside">
            <label for="external_image_url">URL imagine reprezentativă externă</label>
            <input type="text" name="external_image_url" id="external_image_url" class="widefat"
                   value="<?php echo esc_url($external_image_url); ?>" placeholder="Introduceți URL-ul imaginii..."/>
            <p class="description">Dacă adăugați un URL, acesta va fi folosit ca imagine reprezentativă pentru acest articol.</p>

            <label for="external_image_source">Sursa imaginii</label>
            <input type="text" id="external_image_source" name="external_image_source" value="<?php echo esc_attr($external_image_source); ?>" class="widefat" placeholder="Sursa imaginii (de ex: Digi24)">
            
            <p></p>
            
            <label for="feedback-text">Feedback pentru imaginea generată</label>
            <textarea type="text" id="feedback-text"  class="widefat" placeholder="Introduceți feedback pentru imaginea generată..."></textarea>
     
           
             <button id="generate-image-button" type="button"  class="button button-primary"
                    style="width: 100%; margin-top: 10px;">Generează imagine AI</button>
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
    }

    public static function render_featured_image_metabox($post)
    {
        $image_url = get_post_meta($post->ID, '_external_image_url', true) ?: get_the_post_thumbnail_url($post->ID);

        if (!$image_url) {
            echo '<p>Nu există o imagine reprezentativă asociată acestui articol.</p>';
            return;
        }

        echo '<div style="text-align: center;">';
        echo '<img src="' . esc_url($image_url) . '" alt="Imagine reprezentativă" style="max-width: 100%; height: auto; margin-bottom: 15px;" />';
        echo '</div>';

        echo '<textarea id="image_feedback" class="widefat" rows="4" placeholder="Introdu feedback pentru această imagine..."></textarea>';
        echo '<button id="send-feedback-button" type="button" class="button button-primary" style="margin-top: 10px;">Trimite feedback</button>';

        // Elimin textarea și butonul pentru feedback imagine, plus nonce-ul asociat
    }

}

Auto_Ai_News_Poster_Metabox::init();

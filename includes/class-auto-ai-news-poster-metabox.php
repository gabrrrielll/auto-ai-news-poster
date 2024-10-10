<?php

class Auto_Ai_News_Poster_Metabox
{
    public static function init()
    {
        // Adăugăm metabox-ul pentru "Get Article from Sources"
        add_action('add_meta_boxes', [self::class, 'add_get_article_metabox']);

        // Aplicăm editorul TinyMCE pe câmpul implicit de rezumat al WordPress
        add_action('admin_init', [self::class, 'customize_excerpt_editor']);

        // Salvăm datele metaboxurilor
        add_action('save_post', [self::class, 'save_article_summary']);
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
        ?>
        <div class="inside">
            <textarea id="additional-instructions" class="widefat"
                      placeholder="Introduceți instrucțiuni suplimentare pentru AI..."></textarea>
            <button id="get-article-button" type="button" class="button button-primary"
                    style="width: 100%; margin-top: 10px;">
                Get Article from Sources
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

    public static function save_article_summary($post_id)
    {
        // Salvăm rezumatul la salvarea postării
        if (array_key_exists('excerpt', $_POST)) {
            update_post_meta($post_id, '_wp_excerpt', sanitize_text_field($_POST['excerpt']));
        }
    }
}

Auto_Ai_News_Poster_Metabox::init();
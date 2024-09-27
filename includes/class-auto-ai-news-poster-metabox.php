<?php

class Auto_Ai_News_Poster_Metabox
{

    public static function init()
    {
        // Adăugăm metabox-ul personalizat la pagina de editare a articolelor
        add_action('add_meta_boxes', [self::class, 'add_get_article_metabox']);
    }

    public static function add_get_article_metabox()
    {
        // Adăugăm metabox-ul în coloana din dreapta, la cea mai înaltă prioritate (deasupra celorlalte)
        add_meta_box(
            'auto_ai_news_poster_get_article',
            'Get Article from Sources',
            [self::class, 'render_get_article_metabox'],
            'post',  // Afișează doar în postări (articole)
            'side',  // Coloana din dreapta
            'high'   // Prioritate mare, pentru a apărea deasupra celorlalte metaboxuri
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
}

Auto_Ai_News_Poster_Metabox::init();

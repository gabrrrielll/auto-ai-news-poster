<?php

class Auto_Ai_News_Poster_Articles_View
{

    public static function init()
    {
        // Adăugăm un hook pentru a personaliza pagina de listare articole
        add_action('all_admin_notices', [self::class, 'display_custom_article_editor']);
    }

    public static function display_custom_article_editor()
    {
        // Verificăm dacă ne aflăm pe pagina de listare articole (/wp-admin/edit.php)
        global $pagenow;
        if ($pagenow == 'edit.php') {
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline">Adaugă articol nou</h1>
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <!-- Coloana principală: Editor articol -->
                        <div id="post-body-content">
                            <div class="postbox">
                                <h2 class="hndle ui-sortable-handle"><span>Titlul articolului</span></h2>
                                <div class="inside">
                                    <input type="text" id="title" name="post_title" class="widefat"
                                           placeholder="Titlul comun al știrii">
                                </div>
                            </div>

                            <div class="postbox">
                                <h2 class="hndle ui-sortable-handle"><span>Conținutul articolului</span></h2>
                                <div class="inside">
                                    <textarea id="content" name="post_content" rows="10" class="widefat"
                                              placeholder="Conținutul obiectiv al știrii din minim trei surse."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Coloana din dreapta: Categorii, etichete, imagine -->
                        <div id="postbox-container-1" class="postbox-container">
                            <div class="postbox">
                                <h2 class="hndle ui-sortable-handle"><span>Categorii</span></h2>
                                <div class="inside">
                                    <label>
                                        <input type="checkbox" name="category[]" value="sport"> Sport
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="category[]" value="economy"> Economie
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="category[]" value="uncategorized"> Uncategorized
                                    </label>
                                </div>
                            </div>

                            <div class="postbox">
                                <h2 class="hndle ui-sortable-handle"><span>Etichete</span></h2>
                                <div class="inside">
                                    <input type="text" name="tags" class="widefat"
                                           placeholder="Adaugă etichete separate prin virgulă">
                                </div>
                            </div>

                            <div class="postbox">
                                <h2 class="hndle ui-sortable-handle"><span>Imagine reprezentativă</span></h2>
                                <div class="inside">
                                    <button class="button button-secondary">Stabilește imaginea reprezentativă</button>
                                </div>
                            </div>

                            <div class="postbox">
                                <div class="inside">
                                    <button type="button" class="button button-primary">Get article from sources
                                    </button>
                                    <button type="submit" class="button button-primary">Publish</button>
                                    <button type="button" class="button button-secondary">Save draft</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
}

Auto_Ai_News_Poster_Articles_View::init();

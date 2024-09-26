<?php

class Auto_Ai_News_Poster_Articles_View
{

    public static function init()
    {
        // Adaugă un nou hook pentru a personaliza pagina de listare articole
        add_action('all_admin_notices', [self::class, 'display_custom_article_editor']);
    }

    public static function display_custom_article_editor()
    {
        // Verifică dacă ne aflăm pe pagina de listare articole (/wp-admin/edit.php)
        global $pagenow;
        if ($pagenow == 'edit.php') {
            ?>
            <div class="wrap auto-ai-news-poster-editor">
                <h1>Adaugă articol nou</h1>
                <form method="post" action="post.php">
                    <div id="poststuff">
                        <div id="post-body" class="metabox-holder columns-2">
                            <!-- Editor vizual text -->
                            <div id="post-body-content">
                                <label for="title" class="screen-reader-text">Titlul articolului</label>
                                <input type="text" id="title" name="post_title" class="form-control"
                                       placeholder="Titlul comun al știrii"/>

                                <label for="content" class="screen-reader-text">Conținutul articolului</label>
                                <textarea id="content" name="post_content" rows="10" class="form-control"
                                          placeholder="Conținutul obiectiv al știrii din minim trei surse."></textarea>
                            </div>
                            <!-- Sidebar cu categoriile și butoanele -->
                            <div id="postbox-container-1" class="postbox-container">
                                <div class="postbox">
                                    <h2>Categorii</h2>
                                    <div class="inside">
                                        <label>
                                            <input type="checkbox" name="category[]" value="sport"> Sport
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="category[]" value="economy"> Economie
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="category[]" value="uncategorized">
                                            Uncategorized
                                        </label>
                                    </div>
                                </div>

                                <div class="postbox">
                                    <h2>Etichete</h2>
                                    <div class="inside">
                                        <input type="text" name="tags" class="form-control"
                                               placeholder="Adaugă etichete separate prin virgulă">
                                    </div>
                                </div>

                                <div class="postbox">
                                    <h2>Imagine reprezentativă</h2>
                                    <div class="inside">
                                        <button class="button button-secondary">Stabilește imaginea reprezentativă
                                        </button>
                                    </div>
                                </div>

                                <!-- Butoane pentru publicare și extragere articole -->
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
                </form>
            </div>
            <?php
        }
    }
}

Auto_Ai_News_Poster_Articles_View::init();

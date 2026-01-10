<?php

class Auto_Ai_News_Poster_Hooks
{
    public static function init()
    {
        // Aplicăm filtrul pentru a adăuga imaginea externă sau importată în funcție de setare
        add_filter('the_content', [self::class, 'display_external_image'], 10, 2);

        // Adăugăm toate hook-urile și filtrele necesare aici
        add_filter('admin_post_thumbnail_html', [self::class, 'display_external_featured_image_in_metabox'], 10, 2);

    }

    public static function display_external_image($content)
    {
        global $post;

        // Verificăm dacă $post este un obiect valid
        if (!is_object($post) || !isset($post->ID)) {
            return $content; // Returnăm conținutul original dacă $post nu este valid
        }

        // Preluăm opțiunea din setările pluginului
        $options = get_option('auto_ai_news_poster_settings');
        $use_external_images = $options['use_external_images'] ?? 'external';

        // Preluăm setarea globală pentru poziția sursei foto (default: before)
        $global_source_position = $options['source_photo_position'] ?? 'before';

        // Preluăm URL-ul imaginii externe și sursa
        $external_image_url = get_post_meta($post->ID, '_external_image_url', true);
        $external_image_source = trim((string) get_post_meta($post->ID, '_external_image_source', true));
        $external_image_source_position = get_post_meta($post->ID, '_external_image_source_position', true);
        
        // Dacă poziția nu este setată explicit pe articol, folosim setarea globală
        if (empty($external_image_source_position)) {
            $external_image_source_position = $global_source_position;
        }

        // Determinăm poziția finală (care este acum doar external_image_source_position)
        $final_source_position = $external_image_source_position;

        // Dacă folosim imagini externe și există un URL extern
        if ($use_external_images === 'external' && !empty($external_image_url)) {
            $image_html = '<div class="external-image">';
            $image_html .= '<img src="' . esc_url($external_image_url) . '" alt="" />';

            // Adăugăm și sursa imaginii dacă există
            $image_html .= '</div>';

            // Adăugăm imaginea externă înainte de conținut
            $content = $image_html . $content;
        } 
        
        // Afișăm sursa foto indiferent de tipul imaginii (externă sau featured image standard)
        if (has_post_thumbnail($post->ID) || ($use_external_images === 'external' && !empty($external_image_url))) {
            if (!empty($external_image_source)) {
                 $info_icon = '';
                if (str_contains(strtolower($external_image_source), 'imagine generat')) {
                    $info_icon = ' <span class="info-icon tooltip">i<span class="tooltiptext">Această imagine a fost generată automat de AI pe baza rezumatului articolului și nu reprezintă un moment real fotografiat.</span></span>';
                }
                
                $source_html = '<p id="sursa-foto"><em>Sursa foto: <b>' . esc_html($external_image_source) . '</b></em>' . $info_icon . '</p>';
                
                if ($final_source_position === 'before') {
                    // Adăugăm sursa foto înainte de conținut (dar după imaginea externă dacă există, deoarece $content deja o conține)
                    // Notă: Dacă imaginea externă e deja în $content, sursa va fi pusă între imagine și text sau înainte de tot dacă nu e imagine externă.
                    // Ideal sursa ar trebui să fie imediat sub imagine.
                    // Dacă $content începe cu div-ul external-image, vrem ca sursa să fie după el.
                    if (strpos($content, '<div class="external-image">') === 0) {
                        // Inserăm după închiderea div-ului
                        $close_div_pos = strpos($content, '</div>');
                        if ($close_div_pos !== false) {
                            $part1 = substr($content, 0, $close_div_pos + 6); // +6 pentru </div>
                            $part2 = substr($content, $close_div_pos + 6);
                            $content = $part1 . '<br>' . $source_html . '<br>' . $part2;
                        } else {
                             $content = $source_html . '<br>' . $content;
                        }
                    } else {
                         $content = $source_html . '<br>' . $content;
                    }

                } else {
                    // Adăugăm sursa foto după conținut
                    $content .= '<br>' . $source_html;
                }
            }
        }

        return $content;
    }

    // Funcție pentru a afișa imaginea externă în metabox-ul de imagine reprezentativă
    public static function display_external_featured_image_in_metabox($content, $post_id)
    {
        // Verificăm dacă există o imagine reprezentativă externă
        $external_image_url = get_post_meta($post_id, '_external_image_url', true);

        if ($external_image_url) {
            // Construim HTML-ul pentru a afișa imaginea externă
            $image_html = '<div style="margin-top: 10px;">';
            $image_html .= '<img src="' . esc_url($external_image_url) . '" alt="" style="max-width:100%; height:auto;">';
            $image_html .= '</div>';
            $image_html .= '<p style="margin-top:10px;"><strong>Imagine reprezentativă externă:</strong></p>';
            $image_html .= '<p>' . esc_url($external_image_url) . '</p>';

            // Adăugăm HTML-ul în conținutul original al metabox-ului
            return $content . $image_html;
        }

        // Dacă nu există imagine externă, returnăm conținutul original
        return $content;
    }

}

Auto_Ai_News_Poster_Hooks::init();

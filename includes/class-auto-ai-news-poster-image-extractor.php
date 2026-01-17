<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class Auto_AI_News_Poster_Image_Extractor
 * 
 * Extrage link-ul imaginii principale dintr-o sursă externă folosind
 * multiple strategii: Open Graph, meta tags, și heuristica din conținut.
 */
class Auto_AI_News_Poster_Image_Extractor
{
    /**
     * Extrage imaginea principală dintr-un URL sau din conținut HTML.
     *
     * @param string $url URL-ul sursă
     * @param string|null $html_content Conținutul HTML (opțional, dacă nu este furnizat, va fi descărcat)
     * @return string|null URL-ul imaginii sau null dacă nu s-a găsit
     */
    public static function extract_image_from_url($url, $html_content = null)
    {
        // Dacă nu avem HTML, îl descărcăm
        if ($html_content === null) {
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]);

            if (is_wp_error($response)) {
                error_log('[IMAGE_EXTRACTOR] Failed to fetch URL: ' . $url . ' - ' . $response->get_error_message());
                return null;
            }

            $html_content = wp_remote_retrieve_body($response);
        }

        if (empty($html_content)) {
            error_log('[IMAGE_EXTRACTOR] Empty HTML content for URL: ' . $url);
            return null;
        }

        // Încercăm diferite strategii în ordine de prioritate
        $image_url = null;

        // 1. Open Graph / Twitter Cards (cea mai bună sursă)
        $image_url = self::extract_from_og_tags($html_content, $url);
        if ($image_url) {
            error_log('[IMAGE_EXTRACTOR] Found image via OG tags: ' . $image_url);
            return $image_url;
        }

        // 2. Meta tags standard
        $image_url = self::extract_from_meta_tags($html_content, $url);
        if ($image_url) {
            error_log('[IMAGE_EXTRACTOR] Found image via meta tags: ' . $image_url);
            return $image_url;
        }

        // 3. Heuristică din conținut HTML
        $image_url = self::extract_from_content($html_content, $url);
        if ($image_url) {
            error_log('[IMAGE_EXTRACTOR] Found image via content heuristics: ' . $image_url);
            return $image_url;
        }

        error_log('[IMAGE_EXTRACTOR] No image found for URL: ' . $url);
        return null;
    }

    /**
     * Extrage imaginea din Open Graph și Twitter Card tags.
     *
     * @param string $html_content Conținutul HTML
     * @param string $base_url URL-ul de bază pentru rezolvarea URL-urilor relative
     * @return string|null URL-ul imaginii sau null
     */
    public static function extract_from_og_tags($html_content, $base_url)
    {
        $patterns = [
            // Open Graph
            '/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i',
            '/<meta\s+content=["\']([^"\']+)["\']\s+property=["\']og:image["\']/i',
            // Twitter Card
            '/<meta\s+name=["\']twitter:image["\']\s+content=["\']([^"\']+)["\']/i',
            '/<meta\s+content=["\']([^"\']+)["\']\s+name=["\']twitter:image["\']/i',
            '/<meta\s+name=["\']twitter:image:src["\']\s+content=["\']([^"\']+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html_content, $matches)) {
                $image_url = trim($matches[1]);
                $image_url = self::validate_image_url($image_url, $base_url);
                if ($image_url) {
                    return $image_url;
                }
            }
        }

        return null;
    }

    /**
     * Extrage imaginea din meta tags standard.
     *
     * @param string $html_content Conținutul HTML
     * @param string $base_url URL-ul de bază
     * @return string|null URL-ul imaginii sau null
     */
    public static function extract_from_meta_tags($html_content, $base_url)
    {
        $patterns = [
            '/<meta\s+name=["\']image["\']\s+content=["\']([^"\']+)["\']/i',
            '/<meta\s+content=["\']([^"\']+)["\']\s+name=["\']image["\']/i',
            // Schema.org
            '/<meta\s+itemprop=["\']image["\']\s+content=["\']([^"\']+)["\']/i',
            '/<meta\s+content=["\']([^"\']+)["\']\s+itemprop=["\']image["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html_content, $matches)) {
                $image_url = trim($matches[1]);
                $image_url = self::validate_image_url($image_url, $base_url);
                if ($image_url) {
                    return $image_url;
                }
            }
        }

        return null;
    }

    /**
     * Extrage imaginea folosind heuristica din conținutul HTML.
     * Caută imagini mari și relevante în zona de conținut principal.
     *
     * @param string $html_content Conținutul HTML
     * @param string $base_url URL-ul de bază
     * @return string|null URL-ul imaginii sau null
     */
    public static function extract_from_content($html_content, $base_url)
    {
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html_content, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
            $xpath = new DOMXPath($dom);

            // Căutăm în zonele relevante (article, main, content)
            $content_selectors = [
                '//article//img',
                '//main//img',
                '//div[contains(@class, "content")]//img',
                '//div[contains(@class, "article")]//img',
                '//div[contains(@class, "post")]//img',
                '//body//img',
            ];

            $candidates = [];

            foreach ($content_selectors as $selector) {
                $images = $xpath->query($selector);
                if ($images) {
                    foreach ($images as $img) {
                        $img_url = self::get_image_url_from_element($img, $base_url);
                        if (!$img_url) {
                            continue;
                        }

                        // Verificăm dacă nu este o imagine de tip ad/banner/logo
                        if (self::is_irrelevant_image($img)) {
                            continue;
                        }

                        // Obținem dimensiunile și poziția
                        $width = self::get_image_dimension($img, 'width');
                        $height = self::get_image_dimension($img, 'height');
                        $score = self::calculate_image_score($img, $width, $height, $xpath);

                        if ($score > 0) {
                            $candidates[] = [
                                'url' => $img_url,
                                'score' => $score,
                                'width' => $width,
                                'height' => $height,
                            ];
                        }
                    }
                }
            }

            // Sortăm după scor și returnăm cea mai bună
            if (!empty($candidates)) {
                usort($candidates, function($a, $b) {
                    return $b['score'] - $a['score'];
                });

                $best_image = $candidates[0];
                error_log('[IMAGE_EXTRACTOR] Best candidate: ' . $best_image['url'] . ' (score: ' . $best_image['score'] . ')');
                return $best_image['url'];
            }

        } catch (Exception $e) {
            error_log('[IMAGE_EXTRACTOR] Error in extract_from_content: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Obține URL-ul imaginii dintr-un element <img>.
     * Suportă src, data-src (lazy loading), și srcset.
     *
     * @param DOMElement $img Elementul img
     * @param string $base_url URL-ul de bază
     * @return string|null URL-ul imaginii sau null
     */
    private static function get_image_url_from_element($img, $base_url)
    {
        // Încercăm src normal
        $src = $img->getAttribute('src');
        if (!empty($src)) {
            return self::validate_image_url($src, $base_url);
        }

        // Încercăm data-src (lazy loading)
        $data_src = $img->getAttribute('data-src');
        if (!empty($data_src)) {
            return self::validate_image_url($data_src, $base_url);
        }

        // Încercăm data-lazy-src
        $data_lazy_src = $img->getAttribute('data-lazy-src');
        if (!empty($data_lazy_src)) {
            return self::validate_image_url($data_lazy_src, $base_url);
        }

        // Încercăm srcset (luăm prima opțiune)
        $srcset = $img->getAttribute('srcset');
        if (!empty($srcset)) {
            // srcset format: "url1 1x, url2 2x" sau "url1 300w, url2 600w"
            if (preg_match('/^([^\s,]+)/', $srcset, $matches)) {
                return self::validate_image_url(trim($matches[1]), $base_url);
            }
        }

        return null;
    }

    /**
     * Verifică dacă o imagine este irelevantă (ad, banner, logo, etc.).
     *
     * @param DOMElement $img Elementul img
     * @return bool True dacă imaginea este irelevantă
     */
    private static function is_irrelevant_image($img)
    {
        $irrelevant_patterns = [
            'ad', 'ads', 'advertisement', 'banner', 'logo', 'icon', 'avatar',
            'thumbnail', 'thumb', 'placeholder', 'spacer', 'pixel', 'tracking',
            'social', 'share', 'widget', 'sidebar', 'header', 'footer', 'menu',
        ];

        // Verificăm clasele
        $class = strtolower($img->getAttribute('class'));
        foreach ($irrelevant_patterns as $pattern) {
            if (strpos($class, $pattern) !== false) {
                return true;
            }
        }

        // Verificăm ID-ul
        $id = strtolower($img->getAttribute('id'));
        foreach ($irrelevant_patterns as $pattern) {
            if (strpos($id, $pattern) !== false) {
                return true;
            }
        }

        // Verificăm alt text
        $alt = strtolower($img->getAttribute('alt'));
        foreach ($irrelevant_patterns as $pattern) {
            if (strpos($alt, $pattern) !== false) {
                return true;
            }
        }

        // Verificăm dimensiunile (imagini foarte mici sunt probabil iconuri)
        $width = self::get_image_dimension($img, 'width');
        $height = self::get_image_dimension($img, 'height');
        if (($width > 0 && $width < 100) || ($height > 0 && $height < 100)) {
            return true;
        }

        return false;
    }

    /**
     * Obține dimensiunea unei imagini (width sau height).
     *
     * @param DOMElement $img Elementul img
     * @param string $dimension 'width' sau 'height'
     * @return int Dimensiunea sau 0 dacă nu este disponibilă
     */
    private static function get_image_dimension($img, $dimension)
    {
        // Încercăm din atribut
        $attr_value = $img->getAttribute($dimension);
        if (!empty($attr_value) && is_numeric($attr_value)) {
            return intval($attr_value);
        }

        // Încercăm din data-* attributes
        $data_attr = $img->getAttribute('data-' . $dimension);
        if (!empty($data_attr) && is_numeric($data_attr)) {
            return intval($data_attr);
        }

        return 0;
    }

    /**
     * Calculează un scor pentru o imagine bazat pe relevanță.
     *
     * @param DOMElement $img Elementul img
     * @param int $width Lățimea imaginii
     * @param int $height Înălțimea imaginii
     * @param DOMXPath $xpath XPath pentru căutări
     * @return int Scorul (mai mare = mai bun)
     */
    private static function calculate_image_score($img, $width, $height, $xpath)
    {
        $score = 0;

        // Scor bazat pe dimensiuni (imagini mai mari = mai bune)
        if ($width > 0 && $height > 0) {
            $area = $width * $height;
            if ($area >= 500000) { // >= 500x1000 sau similar
                $score += 100;
            } elseif ($area >= 200000) { // >= 400x500
                $score += 50;
            } elseif ($area >= 50000) { // >= 200x250
                $score += 25;
            }
        } else {
            // Dacă nu avem dimensiuni, presupunem că este OK
            $score += 10;
        }

        // Bonus dacă este în article sau main
        $parent = $img->parentNode;
        $depth = 0;
        while ($parent && $depth < 5) {
            $tag_name = strtolower($parent->nodeName ?? '');
            if ($tag_name === 'article') {
                $score += 50;
                break;
            } elseif ($tag_name === 'main') {
                $score += 30;
                break;
            }
            $parent = $parent->parentNode;
            $depth++;
        }

        // Bonus pentru clase relevante
        $class = strtolower($img->getAttribute('class'));
        $relevant_classes = ['featured', 'hero', 'main', 'primary', 'cover', 'header-image'];
        foreach ($relevant_classes as $relevant_class) {
            if (strpos($class, $relevant_class) !== false) {
                $score += 20;
                break;
            }
        }

        // Penalizare pentru imagini mici
        if (($width > 0 && $width < 300) || ($height > 0 && $height < 300)) {
            $score -= 30;
        }

        return max(0, $score); // Nu returnăm scor negativ
    }

    /**
     * Validează și normalizează un URL de imagine.
     *
     * @param string $url URL-ul imaginii
     * @param string $base_url URL-ul de bază pentru rezolvarea URL-urilor relative
     * @return string|null URL-ul validat sau null dacă este invalid
     */
    public static function validate_image_url($url, $base_url)
    {
        if (empty($url)) {
            return null;
        }

        $url = trim($url);

        // Eliminăm whitespace și caractere invalide
        $url = preg_replace('/[\s\n\r\t]/', '', $url);

        // Dacă este URL relativ, îl convertim în absolut
        if (!preg_match('/^https?:\/\//i', $url)) {
            // Parsează base URL
            $parsed_base = parse_url($base_url);
            if (!$parsed_base) {
                return null;
            }

            $base_scheme = $parsed_base['scheme'] ?? 'https';
            $base_host = $parsed_base['host'] ?? '';

            // Dacă URL-ul începe cu /, este relativ la root
            if (strpos($url, '/') === 0) {
                $url = $base_scheme . '://' . $base_host . $url;
            } else {
                // URL relativ la directorul curent
                $base_path = $parsed_base['path'] ?? '/';
                $base_path = rtrim($base_path, '/');
                $url = $base_scheme . '://' . $base_host . $base_path . '/' . $url;
            }
        }

        // Validăm că este un URL valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Verificăm că URL-ul pare să fie o imagine (extensie sau pattern)
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $url_lower = strtolower($url);
        $has_image_extension = false;
        foreach ($image_extensions as $ext) {
            if (strpos($url_lower, '.' . $ext) !== false) {
                $has_image_extension = true;
                break;
            }
        }

        // Dacă nu are extensie, verificăm dacă pare să fie un URL de imagine (conține /image/ sau similar)
        if (!$has_image_extension) {
            $image_patterns = ['/image/', '/img/', '/photo/', '/picture/', '/media/'];
            $has_image_pattern = false;
            foreach ($image_patterns as $pattern) {
                if (strpos($url_lower, $pattern) !== false) {
                    $has_image_pattern = true;
                    break;
                }
            }
            if (!$has_image_pattern) {
                // Poate fi un URL de CDN sau API care returnează imagini
                // Acceptăm orice URL valid ca potențial valid
            }
        }

        return $url;
    }
}

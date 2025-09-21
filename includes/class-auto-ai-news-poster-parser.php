<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Auto_AI_News_Poster_Parser
{
    /**
     * Extracts the main article content from a given URL.
     *
     * @param string $url The URL to fetch and parse.
     * @return string|WP_Error The extracted text content or a WP_Error on failure.
     */
    public static function extract_content_from_url($url)
    {
        error_log('🔗 Extracting content from URL: ' . $url);

        // Add User-Agent to avoid being blocked by some websites
        // Also add cache-busting parameters to prevent cached responses
        $cache_bust_url = $url;
        if (strpos($url, '?') !== false) {
            $cache_bust_url .= '&_cb=' . time() . '_' . rand(1000, 9999);
        } else {
            $cache_bust_url .= '?_cb=' . time() . '_' . rand(1000, 9999);
        }

        $response = wp_remote_get($cache_bust_url, [
            'timeout' => 300,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'ro-RO,ro;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('❌ WP_Remote_Get error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('❌ HTTP Error ' . $response_code . ' for URL: ' . $url);
            return new WP_Error('http_error', 'HTTP Error ' . $response_code . ' when accessing URL.');
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            error_log('⚠️ Extracted body is empty for URL: ' . $url);
            return new WP_Error('empty_body', 'Nu s-a putut extrage conținutul din URL-ul furnizat.');
        }

        error_log('📄 Raw HTML body length: ' . strlen($body) . ' characters');
        error_log('📄 First 500 chars of raw HTML: ' . substr($body, 0, 500));

        $article_content = '';
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $body, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
        $xpath = new DOMXPath($dom);

        // Extrage conținutul din elementul <body>
        $body_node = $xpath->query('//body')->item(0);
        if (!$body_node) {
            error_log('⚠️ No <body> tag found. Returning raw body content after basic cleanup.');
            $article_content = preg_replace('/[ \t]+/', ' ', $body);
            $article_content = preg_replace('/(?:\s*\n\s*){2,}/', "\n\n", $article_content);
            $article_content = trim(strip_tags($article_content));
            return $article_content;
        }

        // Extragem 'innerHTML' din elementul <body> pentru a evita reconstruirea <head>
        $body_inner_html = '';
        foreach ($body_node->childNodes as $child_node) {
            $body_inner_html .= $dom->saveHTML($child_node);
        }

        // Reconstruim un DOMDocument doar cu conținutul din <body> (fără head)
        $dom_body_clean = new DOMDocument();
        // Încărcăm HTML-ul în mod explicit într-o structură completă pentru a preveni auto-adăugarea de <head>
        @$dom_body_clean->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $body_inner_html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
        $xpath_body = new DOMXPath($dom_body_clean);

        // Nodul de context pentru căutările ulterioare este acum elementul body din noul document
        $context_node_clean = $xpath_body->query('//body')->item(0);
        if (!$context_node_clean) {
            error_log('❌ Failed to re-parse body content after innerHTML extraction.');
            return new WP_Error('body_reparse_failed', 'Eroare internă la procesarea conținutului articolului.');
        }

        // 1. Eliminăm elementele irelevante din noul document (doar <body>)
        $elements_to_remove = [
            '//script',
            '//style',
            '//header',
            '//footer',
            '//nav',
            '//aside',
            '//form',
            '//iframe',
            '//noscript',
            '//meta',
            '//link',
            '//img[not(@src)]',
            '//svg',
            '//button',
            '//input',
            '//select',
            '//textarea',
            '//comment()',
            '//*[contains(@class, "ad")]',
            '//*[contains(@class, "ads")]',
            '//*[contains(@id, "ad")]',
            '//*[contains(@id, "ads")]',
            '//*[contains(@class, "sidebar")]',
            '//*[contains(@id, "sidebar")]',
            '//*[contains(@class, "menu")]',
            '//*[contains(@id, "menu")]',
            '//*[contains(@class, "widget")]',
            '//*[contains(@id, "widget")]',
            '//*[contains(@class, "breadcrumb")]',
            '//*[contains(@id, "breadcrumb")]',
        ];

        foreach ($elements_to_remove as $selector) {
            $nodes = $xpath_body->query($selector, $context_node_clean); // Căutăm în contextul body curățat
            if ($nodes) {
                foreach ($nodes as $node) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }
        }

        // 2. Caută elementul principal de articol (într-o ordine de prioritate) în contextul curățat
        $selectors = [
            ['//article', 10], // Highest priority
            ['//main', 9],
            ['//div[contains(@class, "entry-content")]', 8],
            ['//div[contains(@class, "post-content")]', 8],
            ['//div[contains(@class, "article-content")]', 8],
            ['//div[contains(@class, "td-post-content")]', 7],
            ['//div[contains(@id, "content")]', 7],
            ['//div[contains(@class, "content")]', 6],
            ['//div[contains(@class, "td-container")]', 5],
            ['//div[contains(@class, "tdc-row")]', 4],
            ['//div[contains(@class, "tdb-block-inner td-fix-index")]', 3],
            ['//div[contains(@class, "td_block_wrap")]', 2],
            ['//div[contains(@class, "td-ss-main-content")]', 2],
            ['//div[contains(@class, "tdb-block-inner")]', 2],
            ['//div[contains(@class, "tdb_single_content")]', 8],
            ["//div[@class='tdb_single_content']", 8],
            ["//div[@id='td-outer-wrap']", 1],
            ['.', 0], // Fallback with lowest priority
        ];

        $best_node = null;
        $best_score = -1;

        foreach ($selectors as $selector_pair) {
            list($selector, $priority) = $selector_pair;
            $nodes = $xpath_body->query($selector, $context_node_clean);
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $text_length = strlen(trim($node->textContent));
                    // Calculate a score based on priority and text length
                    $current_score = $priority * 1000 + $text_length; // Prioritize by score, then length

                    // Add a penalty if the node is likely an ad or irrelevant content
                    if (self::is_node_irrelevant($node)) {
                        $current_score -= 5000; // Major penalty for irrelevant content
                    }

                    if ($current_score > $best_score) {
                        $best_score = $current_score;
                        $best_node = $node;
                    }
                }
            }
        }

        if ($best_node && $best_score > 0) { // Ensure a meaningful node with a positive score is found
            $article_content = $best_node->textContent;
            error_log('✅ Found content using selector with score: ' . $best_score . ')');
        } else {
            $article_content = $context_node_clean->textContent; // Folosesc textul din body-ul curățat
            error_log('⚠️ No specific content selector matched, using full body content');
        }

        // 3. Post-procesare pentru curățarea textului
        $article_content = preg_replace('/[ \t]+/', ' ', $article_content);
        $article_content = preg_replace('/(?:\s*\n\s*){2,}/', "\n\n", $article_content);
        $article_content = trim($article_content);

        error_log('✅ Content extracted. Length: ' . strlen($article_content));
        error_log('📄 First 200 chars of extracted content: ' . substr($article_content, 0, 200));

        // Check for suspicious content patterns that might indicate parsing failure
        $suspicious_patterns = [
            'partenera lui Sorin Grindeanu',
            'Sorin Grindeanu',
            'partenera',
            'grindeanu'
        ];

        $is_suspicious = false;
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($article_content, $pattern) !== false) {
                $is_suspicious = true;
                error_log('⚠️ WARNING: Suspicious content pattern detected: "' . $pattern . '"');
                break;
            }
        }

        if ($is_suspicious) {
            error_log('🚨 CRITICAL: Content appears to be incorrect/default content. Full content: ' . $article_content);

            // Try alternative parsing method
            error_log('🔄 Attempting alternative parsing method...');
            $alternative_content = self::try_alternative_parsing($body, $url);
            if (!empty($alternative_content) && strlen($alternative_content) > 100) {
                error_log('✅ Alternative parsing successful. Using alternative content.');
                $article_content = $alternative_content;
            }
        }

        $max_content_length = 15000;
        if (strlen($article_content) > $max_content_length) {
            $article_content = substr($article_content, 0, $max_content_length);
            error_log('⚠️ Article content truncated to ' . $max_content_length . ' characters.');
        }

        // Verificăm dacă conținutul pare să fie corect
        if (strlen($article_content) < 100) {
            error_log('⚠️ WARNING: Extracted content is very short (' . strlen($article_content) . ' chars). This might indicate a parsing issue.');
            error_log('📄 Full extracted content: ' . $article_content);
        }

        return $article_content;
    }

    /**
     * Alternative parsing method when primary parsing fails or returns suspicious content.
     */
    private static function try_alternative_parsing($html_body, $url)
    {
        error_log('🔄 ALTERNATIVE_PARSING() STARTED for URL: ' . $url);

        try {
            // Method 1: Try to extract content using simple regex patterns
            $patterns = [
                '/<article[^>]*>(.*?)<\/article>/is',
                '/<main[^>]*>(.*?)<\/main>/is',
                '/<div[^>]*class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/is',
                '/<div[^>]*class="[^"]*post[^"]*"[^>]*>(.*?)<\/div>/is',
                '/<div[^>]*class="[^"]*article[^"]*"[^>]*>(.*?)<\/div>/is',
                '/<div[^>]*id="content"[^>]*>(.*?)<\/div>/is',
                '/<div[^>]*id="post"[^>]*>(.*?)<\/div>/is',
            ];

            $best_content = '';
            $max_length = 0;

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html_body, $matches)) {
                    foreach ($matches[1] as $match) {
                        $clean_content = strip_tags($match);
                        $clean_content = preg_replace('/\s+/', ' ', $clean_content);
                        $clean_content = trim($clean_content);

                        if (strlen($clean_content) > $max_length && strlen($clean_content) > 200) {
                            $max_length = strlen($clean_content);
                            $best_content = $clean_content;
                        }
                    }
                }
            }

            if (!empty($best_content)) {
                error_log('✅ Alternative parsing found content with length: ' . strlen($best_content));
                return $best_content;
            }

            // Method 2: Try to extract all paragraph content
            if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html_body, $paragraph_matches)) {
                $paragraphs = [];
                foreach ($paragraph_matches[1] as $paragraph) {
                    $clean_paragraph = strip_tags($paragraph);
                    $clean_paragraph = preg_replace('/\s+/', ' ', $clean_paragraph);
                    $clean_paragraph = trim($clean_paragraph);

                    if (strlen($clean_paragraph) > 50) { // Only keep substantial paragraphs
                        $paragraphs[] = $clean_paragraph;
                    }
                }

                if (!empty($paragraphs)) {
                    $combined_content = implode("\n\n", $paragraphs);
                    error_log('✅ Alternative parsing found ' . count($paragraphs) . ' paragraphs with total length: ' . strlen($combined_content));
                    return $combined_content;
                }
            }

            // Method 3: Last resort - extract all text content
            $all_text = strip_tags($html_body);
            $all_text = preg_replace('/\s+/', ' ', $all_text);
            $all_text = trim($all_text);

            if (strlen($all_text) > 500) {
                error_log('✅ Alternative parsing using all text content with length: ' . strlen($all_text));
                return $all_text;
            }

            error_log('❌ Alternative parsing failed to extract meaningful content');
            return '';

        } catch (Exception $e) {
            error_log('❌ Alternative parsing error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Helper method to check if a node is likely to be an ad or irrelevant content.
     * This is a heuristic and might need refinement based on specific website patterns.
     *
     * @param DOMNode $node The DOM node to check.
     * @return bool True if the node is likely irrelevant, false otherwise.
     */
    private static function is_node_irrelevant($node)
    {
        // Common patterns for ads and irrelevant content
        $irrelevant_classes = [
            'ad', 'ads', 'sidebar', 'menu', 'widget', 'breadcrumb', 'comment', 'footer', 'header', 'nav', 'form',
            'iframe', 'noscript', 'meta', 'link', 'img[src*="ad"]', 'svg', 'button', 'input', 'select', 'textarea',
            'script', 'style', 'aside', 'div[class*="ad"]', 'div[id*="ad"]', 'div[class*="ads"]', 'div[id*="ads"]',
            'div[class*="sidebar"]', 'div[id*="sidebar"]', 'div[class*="menu"]', 'div[id*="menu"]', 'div[class*="widget"]',
            'div[id*="widget"]', 'div[class*="breadcrumb"]', 'div[id*="breadcrumb"]',
        ];

        $node_classes = $node->getAttribute('class');
        $node_id = $node->getAttribute('id');
        $node_tag = $node->tagName;

        // Check if the node has any of the irrelevant classes
        if ($node_classes) {
            $classes = explode(' ', $node_classes);
            foreach ($classes as $class) {
                if (in_array($class, $irrelevant_classes)) {
                    return true;
                }
            }
        }

        // Check if the node has an irrelevant ID
        if ($node_id) {
            if (in_array($node_id, $irrelevant_classes)) { // Reusing the list for IDs
                return true;
            }
        }

        // Check if the node is a script, style, or iframe (often used for ads)
        if ($node_tag === 'script' || $node_tag === 'style' || $node_tag === 'iframe') {
            return true;
        }

        // Check if the node is an image without a src attribute (often used for ads)
        if ($node_tag === 'img' && !$node->hasAttribute('src')) {
            return true;
        }

        // Check if the node is an SVG (often used for ads)
        if ($node_tag === 'svg') {
            return true;
        }

        // Check if the node is a button (often used for ads)
        if ($node_tag === 'button') {
            return true;
        }

        // Check if the node is an input (often used for ads)
        if ($node_tag === 'input') {
            return true;
        }

        // Check if the node is a select (often used for ads)
        if ($node_tag === 'select') {
            return true;
        }

        // Check if the node is a textarea (often used for ads)
        if ($node_tag === 'textarea') {
            return true;
        }

        // Check if the node is a comment (often used for ads)
        if ($node->nodeType === XML_COMMENT_NODE) {
            return true;
        }

        // Check if the node is a meta tag (often used for ads)
        if ($node_tag === 'meta') {
            return true;
        }

        // Check if the node is a link tag (often used for ads)
        if ($node_tag === 'link') {
            return true;
        }

        // Check if the node is a div with a class or ID that indicates it's a sidebar or menu
        if ($node_tag === 'div' && ($node_classes || $node_id)) {
            $classes = explode(' ', $node_classes);
            if (in_array('sidebar', $classes) || in_array('menu', $classes) || $node_id === 'sidebar' || $node_id === 'menu') {
                return true;
            }
        }

        // Check if the node is a widget (often used for ads)
        if ($node_tag === 'div' && ($node_classes || $node_id)) {
            $classes = explode(' ', $node_classes);
            if (in_array('widget', $classes) || $node_id === 'widget') {
                return true;
            }
        }

        // Check if the node is a breadcrumb (often used for ads)
        if ($node_tag === 'div' && ($node_classes || $node_id)) {
            $classes = explode(' ', $node_classes);
            if (in_array('breadcrumb', $classes) || $node_id === 'breadcrumb') {
                return true;
            }
        }

        return false;
    }
}

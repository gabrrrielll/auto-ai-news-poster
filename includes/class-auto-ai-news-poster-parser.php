<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Auto_AI_News_Poster_Parser
{
    private static $raw_html_logged_for_request = false;

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
                // 'Accept-Encoding' => 'gzip, deflate', // Temporarily removed for debugging content issues
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

        // Adăugăm o verificare suplimentară pentru a ne asigura că $response este un array și nu este gol
        if (!is_array($response) || empty($response)) {
            error_log('❌ Unexpected or empty response from wp_remote_get. Type: ' . gettype($response) . ', Value: ' . print_r($response, true));
            return new WP_Error('unexpected_response', 'Răspuns neașteptat sau gol de la serverul sursă.');
        }

        // Logăm întregul răspuns înainte de a încerca să extragem detalii din el
        error_log('📥 Full wp_remote_get $response before parsing: ' . print_r($response, true));

        // Get response code. This should be safe as wp_remote_get() returned a valid response.
        $response_code = wp_remote_retrieve_response_code($response);

        // Manually retrieve the final URL from headers to avoid wp_remote_retrieve_url() if it's undefined.
        $final_url = $url; // Default to original URL
        $response_headers = wp_remote_retrieve_headers($response);
        if (isset($response_headers['location'])) {
            $final_url = is_array($response_headers['location']) ? end($response_headers['location']) : $response_headers['location'];
        }

        error_log('🌐 Final URL after wp_remote_get: ' . $final_url);
        error_log('📊 Response Headers: ' . print_r($response_headers, true));

        if ($response_code !== 200) {
            error_log('❌ HTTP Error ' . $response_code . ' for URL: ' . $final_url);
            return new WP_Error('http_error', 'HTTP Error ' . $response_code . ' when accessing URL: ' . $final_url);
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            error_log('⚠️ Extracted body is empty for URL: ' . $url);
            return new WP_Error('empty_body', 'Nu s-a putut extrage conținutul din URL-ul furnizat.');
        }

        // Log raw HTML body only once per request
        if (!self::$raw_html_logged_for_request) {
            error_log('📄 Raw HTML body (full content): ' . $body);
            self::$raw_html_logged_for_request = true;
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
            '//img',
            '//svg',
            '//*[contains(@class, "ads")]', // Remove elements with class containing "ads"
            '//*[contains(@id, "ads")]',    // Remove elements with ID containing "ads"
            '//*[contains(@class, "menu")]',   // Remove elements with class containing "menu"
            '//*[contains(@id, "menu")]',      // Remove elements with ID containing "menu"
            '//*[contains(@class, "head")]',   // Remove elements with class containing "head"
            '//*[contains(@id, "head")]',      // Remove elements with ID containing "head"
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

        // Add an intermediate log to check content after initial aggressive cleanup
        error_log('📄 HTML body after removing irrelevant elements (first 1000 chars): ' . mb_substr($dom_body_clean->saveHTML($context_node_clean), 0, 1000) . '...');

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
            $article_content = $context_node_clean->textContent; // Fallback: iau conținutul din nodul de context rămas (body)
            error_log('⚠️ No specific content selector matched, using full body content');
        }

        // 3. Post-procesare pentru curățarea textului
        $article_content = preg_replace('/[ \t]+/', ' ', $article_content);
        $article_content = preg_replace('/(?:\s*\n\s*){2,}/', "\n\n", $article_content);
        $article_content = trim($article_content);

        error_log('✅ Final content extracted (sent to AI). Length: ' . strlen($article_content));
        error_log('📄 First 200 chars of final content for AI: ' . substr($article_content, 0, 200));

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

        $max_content_length = 50000;
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
        $node_tag = strtolower($node->nodeName);
        $node_text_content = trim($node->textContent);

        // Check for specific tags that are typically not article content
        $irrelevant_tags = ['nav', 'footer', 'aside', 'form', 'script', 'style', 'header', 'noscript', 'iframe', 'svg', 'button', 'input', 'select', 'textarea', 'meta', 'link'];
        if (in_array($node_tag, $irrelevant_tags)) {
            return true;
        }

        // Check if the node is an image without a src attribute (often used for ads)
        if ($node_tag === 'img' && !$node->hasAttribute('src')) {
            return true;
        }

        // Removed the overly aggressive short content filtering for non-block elements.
        // This was a major cause of valid content being removed.

        return false;
    }

    /**
     * Helper method to recursively filter out short or irrelevant paragraphs.
     *
     * @param DOMNodeList $nodes The list of DOM nodes to filter.
     * @param int $min_length The minimum length a paragraph should have.
     * @return string The combined filtered text.
     */
    private static function filter_paragraphs($nodes, $min_length = 100)
    {
        $filtered_paragraphs = [];
        foreach ($nodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                if ($node->tagName === 'p') {
                    $text = trim($node->textContent);
                    if (strlen($text) >= $min_length) {
                        $filtered_paragraphs[] = $text;
                    }
                } elseif ($node->hasChildNodes()) {
                    $filtered_paragraphs[] = self::filter_paragraphs($node->childNodes, $min_length);
                }
            }
        }
        return implode("\n\n", array_filter($filtered_paragraphs));
    }
}

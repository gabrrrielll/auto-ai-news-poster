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
            '//article',
            '//main',
            '//div[contains(@class, "entry-content")]',
            '//div[contains(@class, "post-content")]',
            '//div[contains(@class, "article-content")]',
            '//div[contains(@class, "td-post-content")]',
            '//div[contains(@id, "content")]',
            '//div[contains(@class, "content")]',
            '//div[contains(@class, "td-container")]',
            '//div[contains(@class, "tdc-row")]',
            '//div[contains(@class, "tdb-block-inner td-fix-index")]',
            '//div[contains(@class, "td_block_wrap")]',
            '//div[contains(@class, "td-ss-main-content")]',
            '//div[contains(@class, "tdb-block-inner")]',
            '//div[contains(@class, "tdb_single_content")]',
            '//div[contains(@class, "td-post-content tagdiv-type")]',
            "//div[@class='tdb_single_content']",
            "//div[@id='td-outer-wrap']",
            '.', // Fallback: iau conținutul din nodul de context rămas (body)
        ];

        $found_node = null;
        foreach ($selectors as $selector) {
            $nodes = $xpath_body->query($selector, $context_node_clean);
            if ($nodes->length > 0) {
                $best_node = null;
                $max_text_length = 0;
                foreach ($nodes as $node) {
                    $text_length = strlen(trim($node->textContent));
                    if ($text_length > $max_text_length) {
                        $max_text_length = $text_length;
                        $best_node = $node;
                    }
                }
                if ($best_node) {
                    $found_node = $best_node;
                    break;
                }
            }
        }

        if ($found_node) {
            $article_content = $found_node->textContent;
            error_log('✅ Found content using selector: ' . $selector);
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
}

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
        $response = wp_remote_get($url, ['timeout' => 300]); // Mărit timeout-ul la 300 de secunde (5 minute)

        if (is_wp_error($response)) {
            error_log('❌ WP_Remote_Get error: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            error_log('⚠️ Extracted body is empty for URL: ' . $url);
            return new WP_Error('empty_body', 'Nu s-a putut extrage conținutul din URL-ul furnizat.');
        }

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
        } else {
            $article_content = $context_node_clean->textContent; // Folosesc textul din body-ul curățat
        }

        // 3. Post-procesare pentru curățarea textului
        $article_content = preg_replace('/[ \t]+/', ' ', $article_content);
        $article_content = preg_replace('/(?:\s*\n\s*){2,}/', "\n\n", $article_content);
        $article_content = trim($article_content);

        error_log('✅ Content extracted. Length: ' . strlen($article_content));
        $max_content_length = 15000;
        if (strlen($article_content) > $max_content_length) {
            $article_content = substr($article_content, 0, $max_content_length);
            error_log('⚠️ Article content truncated to ' . $max_content_length . ' characters.');
        }
        return $article_content;
    }
}

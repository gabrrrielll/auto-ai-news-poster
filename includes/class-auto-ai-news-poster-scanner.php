<?php

class Auto_Ai_News_Poster_Scanner
{
    /**
     * Fetches and extracts valid candidate article links from a given URL.
     *
     * @param string $url The URL to scan.
     * @return array|WP_Error Array of unique links ('url', 'text') or WP_Error on failure.
     */
    public static function scan_url($url)
    {
        // 1. Fetch the page
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('empty_body', 'Empty response from URL.');
        }

        // 2. Parse HTML
        $dom = new DOMDocument();
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        // Hack to handle UTF-8 correctly
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $body);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[@href]');
        
        $links = [];
        $base_host = parse_url($url, PHP_URL_HOST);
        $base_scheme = parse_url($url, PHP_URL_SCHEME);
        $base_url = $base_scheme . '://' . $base_host;

        foreach ($anchors as $anchor) {
            $href = $anchor->getAttribute('href');
            $text = trim($anchor->textContent);

            // Normalize URL
            if (strpos($href, '//') === 0) {
                $href = $base_scheme . ':' . $href;
            } elseif (strpos($href, '/') === 0) {
                $href = $base_url . $href;
            } elseif (strpos($href, 'http') !== 0) {
                // Skip relative paths without leading slash for now to avoid complexity, 
                // or assume relative to current path if needed.
                // For main page scanning, most links are absolute or root-relative.
                continue; 
            }

            // 3. Basic Filtering (Domain check & Junk removal)
            $link_host = parse_url($href, PHP_URL_HOST);
            
            // Only internal links (or subdomains)
            if ($link_host !== $base_host && strpos($link_host, 'www.' . $base_host) === false && strpos('www.' . $link_host, $base_host) === false) {
                continue;
            }

            // Length check (too short titles are usually "Home", "More", etc.)
            if (strlen($text) < 10) {
                continue;
            }

            // Word count (titles usually have at least 3 words)
            if (str_word_count($text) < 3) {
                continue;
            }

            // Exclusion patterns (Terms, Login, Policy, etc.)
            if (self::is_junk_link($href)) {
                continue;
            }

            // Add to array (keyed by URL to avoid dupes)
            $links[$href] = [
                'url' => $href,
                'title' => $text
            ];
        }

        return array_values($links);
    }

    /**
     * Checks if a link is likely irrelevant (policy, login, etc.)
     */
    private static function is_junk_link($url)
    {
        $junk_patterns = [
            '/login', '/signin', '/register', '/account',
            '/contact', '/about', '/terms', '/privacy', '/cookies',
            '/feed', '/rss', '/xmlrpc',
            '/wp-admin', '/wp-content', '/wp-includes',
            '/category/', '/tag/', '/page/', '/shop/', '/cart/', // Skip archive pages effectively
            '.jpg', '.png', '.gif', '.pdf', '.css', '.js'
        ];

        foreach ($junk_patterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }


    /**
     * Filters a list of candidate links using AI to identify relevant news.
     *
     * @param array $candidates Array of ['url' => ..., 'title' => ...] candidates.
     * @param string $context The category or keyword context to look for (e.g. "Technology", "Politics").
     * @return array|WP_Error Filtered array of candidates or WP_Error.
     */
    public static function filter_candidates_with_ai($candidates, $context = 'General News')
    {
        if (empty($candidates)) {
            return [];
        }

        // Limit candidates to avoid token overflow (e.g., analyze top 50 matches only)
        $candidates_slice = array_slice($candidates, 0, 50);
        
        // Prepare list for AI
        $list_text = "";
        foreach ($candidates_slice as $index => $item) {
            $list_text .= "[$index] Title: " . $item['title'] . " | URL: " . $item['url'] . "\n";
        }

        // Construct Prompt
        $prompt = "You are a professional news editor assistant. Your goal is to filter a list of potential article links and identify ONLY the ones that appear to be valid, significant NEWS articles about: \"$context\".\n\n";
        $prompt .= "CRITERIA:\n";
        $prompt .= "1. Exclude ads, homepage links, navigation items, subscriptions, or unrelated content.\n";
        $prompt .= "2. Exclude old archives or generic pages.\n";
        $prompt .= "3. Include ONLY specific news stories or articles relevant to the topic.\n\n";
        $prompt .= "LIST TO ANALYZE:\n";
        $prompt .= $list_text;
        $prompt .= "\n\n";
        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "Return a JSON object with a single key 'valid_indices' containing an array of the integer indices (from the list above) that are valid news articles.\n";
        $prompt .= "Example JSON format: {\"valid_indices\": [0, 3, 5, 12]}\n";
        $prompt .= "Reply strictly with the JSON object and nothing else.";

        // Call Centralized API
        // Note: call_ai_api automatically handles provider selection (OpenAI, Gemini, DeepSeek)
        $response = call_ai_api($prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        // Parse Response
        $body = $response['body'] ?? '';
        // Handle nested structure if necessary (call_ai_api returns WP_Remote formatted array)
        if (empty($body)) {
             // Sometimes call_ai_api might return just the body string or different structure depending on provider wrapper
             // Verification needed: call_ai_api usually returns a result that simulates WP remote response
             return new WP_Error('empty_ai_response', 'AI returned empty response during filtering.');
        }

        // Decode JSON
        // Clean markdown code blocks if any (common with Gemini)
        $clean_body = $body;
        if (preg_match('/```json\s*(.*?)\s*```/s', $body, $matches)) {
            $clean_body = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $body, $matches)) {
             $clean_body = $matches[1];
        }
        
        // Ensure we are parsing the CONTENT of the response, not the entire wrapper if it's nested
        // call_ai_api for OpenAI returns json with choices... content.
        // Let's decode the main response first.
        $response_data = json_decode($body, true);
        
        // Extract content string based on OpenAI format (which call_ai_api standardizes to)
        $ai_content = '';
        if (isset($response_data['choices'][0]['message']['content'])) {
            $ai_content = $response_data['choices'][0]['message']['content'];
        } elseif (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) { // Raw Gemini fallback
             $ai_content = $response_data['candidates'][0]['content']['parts'][0]['text'];
        } else {
             // Fallback: maybe the body itself is the content if modified wrappers are involved
             $ai_content = $body;
        }

        // Clean content again just in case it was inside the JSON structure
        if (preg_match('/```json\s*(.*?)\s*```/s', $ai_content, $matches)) {
            $ai_content = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $ai_content, $matches)) {
             $ai_content = $matches[1];
        }

        $result_json = json_decode($ai_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($result_json['valid_indices']) || !is_array($result_json['valid_indices'])) {
             error_log("Site Analyzer AI JSON parse error: " . json_last_error_msg() . " | Content: " . substr($ai_content, 0, 200));
             return new WP_Error('invalid_json', 'AI Filtering returned invalid JSON.');
        }

        // Reconstruct filtered list
        $filtered = [];
        foreach ($result_json['valid_indices'] as $idx) {
            if (isset($candidates_slice[$idx])) {
                $filtered[] = $candidates_slice[$idx];
            }
        }

        return $filtered;
    }
}

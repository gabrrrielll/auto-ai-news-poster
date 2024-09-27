<?php

class Auto_Ai_News_Poster_Api
{

    public static function init()
    {
        // Înregistrăm funcția AJAX pentru apelul API
        add_action('wp_ajax_get_article_from_sources', [self::class, 'get_article_from_sources']);
    }

    public static function get_article_from_sources()
    {
        // Verificare nonce pentru securitate
        check_ajax_referer('get_article_from_sources_nonce', 'security');

        // Verificăm dacă toate datele necesare sunt trimise
        if (!isset($_POST['post_id']) || !isset($_POST['instructions'])) {
            wp_send_json_error(['message' => 'Date incomplete']);
        }

        // Preluăm datele din cererea AJAX și le logăm pentru depanare
        $post_id = intval($_POST['post_id']);
        $additional_instructions = sanitize_text_field($_POST['instructions']);
        $options = get_option('auto_ai_news_poster_settings');
        $api_key = $options['chatgpt_api_key']; // Cheia API
        $sources = explode("\n", trim($options['news_sources'])); // Sursele din setări

        // Verificăm dacă există o cheie API și surse, și logăm pentru depanare
        if (empty($api_key) || empty($sources)) {
            wp_send_json_error(['message' => 'Cheia API sau sursele lipsesc']);
        }

        // Pregătim promptul pentru API-ul OpenAI și trimitem sursele ca date de inspirație
        $prompt = "Creează un articol unic pe baza următoarelor surse de știri:\n";
        $prompt .= implode("\n", $sources);
        $prompt .= "\n\nInstrucțiuni suplimentare: " . $additional_instructions;

        // Apelul către API-ul OpenAI
        $response = wp_remote_post('https://api.openai.com/v1/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'text-davinci-003',
                'prompt' => $prompt,
                'max_tokens' => 500,
            ]),
        ]);

        // Verificăm dacă răspunsul API-ului are erori și logăm pentru depanare
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['text'])) {
            wp_send_json_success(['article_content' => $body['choices'][0]['text']]);
        } else {
            wp_send_json_error(['message' => 'A apărut o eroare la generarea articolului.']);
        }
    }
}

// Inițializăm clasa pentru API
Auto_Ai_News_Poster_Api::init();

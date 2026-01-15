<?php

require_once __DIR__ . '/prompts.php';
require_once __DIR__ . '/constants.php';

// Funcție pentru generarea promptului
const URL_API_OPENAI = URL_API_OPENAI_CHAT; // Backward compatibility alias
const URL_API_IMAGE = URL_API_OPENAI_IMAGE;   // Backward compatibility alias
const URL_API_DEEPSEEK = URL_API_DEEPSEEK_CHAT; // Backward compatibility alias

function generate_custom_source_prompt($article_text_content, $additional_instructions = '', $source_link = '')
{
    return Auto_Ai_News_Poster_Prompts::get_custom_source_prompt($article_text_content, $additional_instructions, $source_link);
}


function generate_prompt($sources, $additional_instructions, $tags): string
{
    return Auto_Ai_News_Poster_Prompts::get_browsing_prompt($sources, $additional_instructions, $tags);
}

/**
 * Generează un prompt text simplu pentru API-ul OpenAI.
 *
 * @param string $system_message Mesajul de sistem pentru AI.
 * @param string $user_message Mesajul utilizatorului pentru AI.
 * @return string Promptul formatat pentru apelul OpenAI.
 */
function generate_simple_text_prompt(string $system_message, string $user_message): string
{
    $prompt = "[SYSTEM]: {$system_message}\n";
    $prompt .= "[USER]: {$user_message}";
    return $prompt;
}

// Funcție pentru apelarea API-ului OpenAI
// Provider selection wrapper for text generation
// Provider selection wrapper for text generation
function call_ai_api($prompt)
{
    $options = get_option('auto_ai_news_poster_settings');
    $provider = $options['api_provider'] ?? 'openai';
    
    // Logging start request
    error_log('[AUTO_AI_NEWS_POSTER] AI request details START');
    error_log('Provider: ' . $provider);
    error_log('Prompt Length: ' . strlen((string) $prompt));
    error_log('Prompt Preview: ' . substr((string) $prompt, 0, 500) . '...');

    if ($provider === 'gemini') {
        $api_key = $options['gemini_api_key'] ?? '';
        $model = $options['gemini_model'] ?? 'gemini-1.5-pro';
        error_log('Selected Model (Gemini): ' . $model);
        return call_gemini_api($api_key, $model, $prompt);
    } 
    elseif ($provider === 'deepseek') {
        $api_key = $options['deepseek_api_key'] ?? '';
        $model = $options['deepseek_model'] ?? 'deepseek-chat';
        error_log('Selected Model (DeepSeek): ' . $model);
        // DeepSeek uses OpenAI-compatible API
        return call_openai_api($api_key, $prompt, $model, URL_API_DEEPSEEK);
    } 
    else {
        // Default OpenAI
        $api_key = $options['chatgpt_api_key'] ?? '';
        $model = $options['ai_model'] ?? DEFAULT_AI_MODEL;
        error_log('Selected Model (OpenAI): ' . $model);
        return call_openai_api($api_key, $prompt, $model, URL_API_OPENAI);
    }
}

function call_openai_api($api_key, $prompt, $model = null, $api_url = URL_API_OPENAI)
{

    // Obținem modelul selectat din setări (doar dacă nu e specificat explicit)
    $selected_model = $model;
    if (empty($selected_model)) {
        $options = get_option('auto_ai_news_poster_settings', []);
        $selected_model = $options['ai_model'] ?? DEFAULT_AI_MODEL;
    }


    // Preluăm setările pentru a vedea dacă trebuie să generăm etichete
    // $options = get_option('auto_ai_news_poster_settings', []); // Deja preluat mai sus
    // $generate_tags_option = $options['generate_tags'] ?? 'yes'; // Nu mai este necesar aici pentru a condiționa required

    // Setăm toate proprietățile ca fiind obligatorii (inclusiv tags)
    $required_properties = ['title', 'content', 'summary', 'category', 'tags', 'sources', 'source_titles'];

    // Verificăm dacă este DeepSeek (care nu suportă încă json_schema strict)
    $is_deepseek = ($api_url === URL_API_DEEPSEEK);

    // Mesajul de sistem de bază
    $system_content = 'You are a precise news article generator. NEVER invent information. Use ONLY the exact information provided in sources. If sources mention specific lists (movies, people, events), copy them EXACTLY without modification. Always respect the required word count.';

    // Dacă e DeepSeek, adăugăm instrucțiuni explicite despre JSON în prompt, deoarece nu folosim json_schema strict
    if ($is_deepseek) {
        $system_content .= " ERROR HANDLING: You MUST respond with valid JSON only. The JSON must follow this structure: {\"title\": \"...\", \"content\": \"...\", \"summary\": \"...\", \"category\": \"...\", \"tags\": [\"...\"], \"sources\": [\"...\"], \"source_titles\": [\"...\"]}";
    }

    $request_body = [
        'model' => $selected_model,
        'messages' => [
            ['role' => 'system', 'content' => $system_content],
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_completion_tokens' => 8000, // Limita safe pentru majoritatea modelelor
    ];

    if ($is_deepseek) {
        // DeepSeek folosește 'json_object' standard
        $request_body['response_format'] = ['type' => 'json_object'];
    } else {
        // OpenAI folosește 'json_schema' pentru Structured Outputs
        $request_body['response_format'] = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'article_response',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titlul articolului generat'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Conținutul complet al articolului generat'
                        ],
                        'summary' => [
                            'type' => 'string',
                            'description' => 'Un rezumat al articolului generat'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Categoria articolului generat'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'description' => 'Etichete relevante pentru articol',
                            'items' => [
                                'type' => 'string'
                            ]
                        ],
                        'sources' => [
                            'type' => 'array',
                            'description' => 'URL-urile complete ale știrilor citite',
                            'items' => [
                                'type' => 'string'
                            ]
                        ],
                        'source_titles' => [
                            'type' => 'array',
                            'description' => 'Titlurile exacte ale articolelor parsate si citite',
                            'items' => [
                                'type' => 'string'
                            ]
                        ]
                    ],
                    'required' => $required_properties,
                    'additionalProperties' => false
                ]
            ]
        ];
    }

    // --- Debug logs: model + message payload preview (fără chei API) ---
    $messages_preview = [];
    foreach (($request_body['messages'] ?? []) as $m) {
        $role = isset($m['role']) ? (string) $m['role'] : 'unknown';
        $content = isset($m['content']) ? (string) $m['content'] : '';
        $messages_preview[] = [
            'role' => $role,
            'len' => strlen($content),
            'preview' => substr($content, 0, 250),
        ];
    }
    $provider_label = ($api_url === URL_API_DEEPSEEK) ? 'DeepSeek(OpenAI-compatible)' : 'OpenAI';
    $response_format_type = $request_body['response_format']['type'] ?? null;
    error_log('[AUTO_AI_NEWS_POSTER] AI request payload provider=' . $provider_label . ' api_url=' . $api_url . ' model=' . $selected_model . ' response_format=' . (string) $response_format_type . ' messages=' . wp_json_encode($messages_preview));


    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($request_body),
        'timeout' => DEFAULT_TIMEOUT_SECONDS, // Mărit timeout-ul la 300 de secunde (5 minute)
    ]);

    if (!is_wp_error($response)) {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $decoded_error = json_decode($body, true);
            $error_message = $decoded_error['error']['message'] ?? 'Unknown OpenAI API Error';
            return new WP_Error('openai_api_error', 'OpenAI API Error (' . $response_code . '): ' . $error_message, $body);
        }
    }

    return $response;
}


// Funcție pentru apelarea API-ului pentru generarea de imagini
// Image generation wrapper - folosește provider-ul selectat (OpenAI sau Gemini)
function call_ai_image_api($dalle_prompt, $feedback = '')
{
    error_log('=== CALL_AI_IMAGE_API START ===');
    error_log('Prompt: ' . substr($dalle_prompt, 0, 100) . '...');
    error_log('Feedback: ' . (!empty($feedback) ? $feedback : 'NONE'));
    
    $options = get_option('auto_ai_news_poster_settings');
    // Temporar: doar OpenAI activ (Gemini dezactivat chiar dacă există în setări).
    error_log('Using OpenAI DALL-E');
    $api_key = $options['chatgpt_api_key'] ?? '';
    if (empty($api_key)) {
        error_log('ERROR: OpenAI API key missing');
        return new WP_Error('no_image_api', 'Cheia API OpenAI lipsește pentru generarea imaginii.');
    }
    
    error_log('Calling call_openai_image_api...');
    $result = call_openai_image_api($api_key, $dalle_prompt, $feedback);
    error_log('call_openai_image_api result: ' . (is_wp_error($result) ? 'ERROR' : 'SUCCESS'));
    error_log('=== CALL_AI_IMAGE_API END ===');
    return $result;
}

function call_openai_image_api($api_key, $dalle_prompt, $feedback = '')
{

    // Creăm un prompt pentru generarea imaginii
    $prompt = $dalle_prompt;
    if (!empty($feedback)) {
        $prompt .= "\n Utilizează următorul feedback de la imaginea generată anterior pentru a îmbunătăți imaginea: " . $feedback;
    }


    $request_body = [
        'model' => 'dall-e-3',  // Modelul DALL-E 3 pentru imagini
        'prompt' => $prompt,
        'size' => '1792x1024',
        'quality' => 'standard',
        'n' => 1,
        'style' => 'natural'
    ];


    // Apelăm API-ul OpenAI pentru generarea imaginii
    $response = wp_remote_post(URL_API_IMAGE, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($request_body),
        'timeout' => DEFAULT_IMAGE_TIMEOUT_SECONDS,
    ]);

    return $response;
}

// --- Google Gemini (text) ---
function call_gemini_api($api_key, $model, $prompt)
{
    if (empty($api_key)) {
        return new WP_Error('gemini_missing_key', 'Missing Gemini API key');
    }

    // Strip "models/" prefix if present, as URL_API_GEMINI_BASE already includes it
    $clean_model = str_replace('models/', '', $model);
    $endpoint = URL_API_GEMINI_BASE . urlencode($clean_model) . ':generateContent?key=' . urlencode($api_key);

    $body = [
        'contents' => [
            [
                'parts' => [ ['text' => $prompt] ]
            ]
        ]
    ];

    // Debug logs: model + request payload preview (fără chei API)
    error_log('[AUTO_AI_NEWS_POSTER] Gemini request model=' . $model . ' endpoint=v1beta:generateContent prompt_len=' . strlen((string) $prompt) . ' prompt_preview=' . substr((string) $prompt, 0, 250) . ' body_preview=' . substr(wp_json_encode($body), 0, 250));

    $response = wp_remote_post($endpoint, [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body' => wp_json_encode($body),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        return $response; // Already a WP_Error
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code !== 200) {
        $error_msg = 'Gemini HTTP ' . $code;
        if (isset($data['error']['message'])) {
            $error_msg .= ' - ' . $data['error']['message'];
        } elseif (is_string($raw)) {
            $error_msg .= ' - ' . substr($raw, 0, 200);
        }
        return new WP_Error('gemini_api_error', $error_msg, $raw);
    }

    // Extract text
    $text = '';
    if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $data['candidates'][0]['content']['parts'][0]['text'];
    }
    
    // Log raw response for debugging
    error_log('[AUTO_AI_NEWS_POSTER] Gemini Raw Response: ' . substr($raw, 0, 1000));

    // Simulăm structura de răspuns OpenAI pentru compatibilitate cu functiile existente
    // Structure: choices[0].message.content = JSON string
    // Gemini usually returns plain text, but our prompt asks for JSON.
    // If text contains ```json ... ```, strip it.
    
    $clean_text = $text;
    if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
        $clean_text = $matches[1];
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $text, $matches)) {
         $clean_text = $matches[1];
    }

    $simulated_choices = [ 'choices' => [ ['message' => ['content' => $clean_text]] ] ];
    
    // Returnăm un array care simulează structura de răspuns a wp_remote_post
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'headers' => [],
        'body' => json_encode($simulated_choices),
        'cookies' => [],
        'filename' => null
    ];
}

// --- Google Gemini (image generation via Generative Language API) ---
// Implementare bazată pe @google/genai SDK (testată și funcțională în React/TypeScript)
function call_gemini_image_api($api_key, $model, $prompt, $feedback = '')
{
    error_log('=== GEMINI IMAGE API CALL START ===');
    error_log('Model received: ' . $model);
    error_log('API Key present: ' . (!empty($api_key) ? 'YES' : 'NO'));
    error_log('Prompt length: ' . strlen($prompt));
    
    if (empty($api_key)) {
        return new WP_Error('missing_api_key', 'Missing Gemini API key');
    }

    // Extragem textul din prompt dacă este JSON
    $final_prompt = $prompt;
    $decoded_prompt = json_decode($prompt, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_prompt)) {
        if (!empty($decoded_prompt['content'])) {
            $final_prompt = $decoded_prompt['content'];
        } elseif (!empty($decoded_prompt['prompt'])) {
            $final_prompt = $decoded_prompt['prompt'];
        } elseif (!empty($decoded_prompt['text'])) {
            $final_prompt = $decoded_prompt['text'];
        }
    }
    
    if (!empty($feedback)) {
        $final_prompt .= "\n Utilizează următorul feedback: " . $feedback;
    }

    // Mapăm modelele la ID-urile corecte din API
    // Model names conform @google/genai SDK și Generative Language API
    // Notă: Modelele Gemini pentru imagini folosesc generateContent, nu generateImages
    $model_mapping = [
        'gemini-2.5-flash-image' => 'gemini-2.5-flash-image', // Model corect pentru generateContent
        'gemini-3-pro-image-preview' => 'gemini-3-pro-image-preview',
        'imagen-4' => 'imagen-4',
    ];
    
    $api_model = $model_mapping[$model] ?? $model;
    error_log('Using API model: ' . $api_model . ' (mapped from: ' . $model . ')');
    
    // Verificare: dacă modelul nu este în mapping și nu este Imagen, încercăm variante alternative
    if ($api_model === $model && $api_model !== 'imagen-4' && strpos($api_model, 'gemini') === 0) {
        // Dacă modelul nu este în mapping, folosim direct valoarea din setări
        // Aceasta permite flexibilitate pentru modele noi sau experimentale
        error_log('Using model name directly from settings: ' . $api_model);
    }
    
    // Inițializăm imageConfig pentru modelele Gemini (va fi folosit mai jos)
    $imageConfig = [
        'aspectRatio' => '16:9',
    ];
    
    if ($api_model === 'gemini-3-pro-image-preview') {
        $imageConfig['imageSize'] = '2K'; // Opțiuni: 1K, 2K, 4K
    }

    // Case 1: Imagen 4.0 Model (Uses generateImages)
    if ($api_model === 'imagen-4') {
        $endpoint = URL_API_GEMINI_IMAGEN_4;
        
        $body = [
            'prompt' => $final_prompt,
            'numberOfImages' => 1,
            'outputMimeType' => 'image/jpeg',
            'aspectRatio' => '16:9',
        ];
        
        error_log('Using generateImages endpoint for Imagen 4');
        
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'x-goog-api-key' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => DEFAULT_IMAGE_TIMEOUT_SECONDS,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code !== 200) {
            $error_msg = 'Imagen 4 API Error (HTTP ' . $code . ')';
            if (isset($data['error']['message'])) {
                $error_msg .= ': ' . $data['error']['message'];
            }
            return new WP_Error('imagen_api_error', $error_msg);
        }

        // Extragem imaginea din răspuns Imagen 4
        if (!empty($data['generatedImages']) && !empty($data['generatedImages'][0]['image']['imageBytes'])) {
            $image_base64 = $data['generatedImages'][0]['image']['imageBytes'];
            return save_base64_image($image_base64, 'image/jpeg', 'imagen');
        }

        return new WP_Error('no_image', 'No image found in Imagen 4 response');
    }

    // Case 2: Gemini Flash/Pro Series (Uses generateContent)
    $endpoint = URL_API_GEMINI_BASE . urlencode($api_model) . ':generateContent';
    
    // imageConfig este deja definit mai sus pentru toate modelele Gemini

    $body = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $final_prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'imageConfig' => $imageConfig
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_NONE'
            ]
        ]
    ];
    
    error_log('Using generateContent endpoint for ' . $api_model);
    error_log('Request body (imageConfig): ' . json_encode($imageConfig));
    error_log('Request body (full, first 300 chars): ' . substr(wp_json_encode($body), 0, 300));
    
    $response = wp_remote_post($endpoint, [
        'headers' => [
            'x-goog-api-key' => $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode($body),
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code !== 200) {
        $error_msg = 'Gemini Image API Error (HTTP ' . $code . ')';
        if (isset($data['error']['message'])) {
            $error_msg .= ': ' . $data['error']['message'];
        }
        // Adăugăm sugestie pentru verificarea modelelor disponibile
        if ($code === 404) {
            $error_msg .= '. Sugestie: Verifică dacă modelul "' . $api_model . '" este disponibil în API. Poți lista modelele disponibile folosind ListModels API.';
        }
        error_log('Gemini Image API Error Details: Model=' . $api_model . ', Endpoint=' . $endpoint . ', Error=' . $error_msg);
        error_log('Response body (first 500 chars): ' . substr($raw, 0, 500));
        return new WP_Error('gemini_api_error', $error_msg);
    }

    // Verificăm dacă există candidat
    if (empty($data['candidates']) || empty($data['candidates'][0])) {
        error_log('No candidates in API response. Full response: ' . json_encode($data));
        return new WP_Error('no_candidate', 'No candidates found in API response');
    }
    
    $candidate = $data['candidates'][0];
    
    // Verificăm finishReason pentru a vedea de ce nu s-a generat imaginea
    $finish_reason = $candidate['finishReason'] ?? 'UNKNOWN';
    error_log('Finish reason: ' . $finish_reason);
    
    // Verificăm safety ratings pentru a detecta blocările
    $safety_ratings = $candidate['safetyRatings'] ?? [];
    $has_safety_block = false;
    if (!empty($safety_ratings)) {
        foreach ($safety_ratings as $rating) {
            $blocked = $rating['blocked'] ?? false;
            $category = $rating['category'] ?? '';
            $probability = $rating['probability'] ?? '';
            
            if ($blocked || $probability === 'HIGH' || $probability === 'MEDIUM') {
                $has_safety_block = true;
                error_log('Safety block detected - Category: ' . $category . ', Blocked: ' . ($blocked ? 'YES' : 'NO') . ', Probability: ' . $probability);
            }
        }
    }
    
    if ($finish_reason !== 'STOP' || $has_safety_block) {
        $error_msg = 'Image generation stopped. Reason: ' . $finish_reason;
        if (!empty($safety_ratings)) {
            $error_msg .= '. Safety ratings: ' . json_encode($safety_ratings);
        }
        if ($has_safety_block) {
            $error_msg .= ' [SAFETY_BLOCK]';
        }
        error_log($error_msg);
        return new WP_Error('generation_stopped', $error_msg);
    }
    
    // Log response structure for debugging
    error_log('API Response structure: ' . json_encode([
        'has_candidates' => isset($data['candidates']),
        'candidates_count' => isset($data['candidates']) ? count($data['candidates']) : 0,
        'first_candidate_keys' => isset($candidate) ? array_keys($candidate) : [],
        'has_content' => isset($candidate['content']),
        'has_parts' => isset($candidate['content']['parts']),
        'parts_count' => isset($candidate['content']['parts']) ? count($candidate['content']['parts']) : 0,
        'finish_reason' => $finish_reason,
    ]));

    // Iterate through parts to find the image
    $parts = $candidate['content']['parts'] ?? [];
    
    if (empty($parts)) {
        error_log('No parts found in response. Full response structure: ' . json_encode($data));
        return new WP_Error('no_image', 'No parts found in API response. Response: ' . substr(json_encode($data), 0, 500));
    }
    
    foreach ($parts as $index => $part) {
        error_log('Checking part ' . $index . ', keys: ' . implode(', ', array_keys($part)));
        
        // Verificăm dacă există imagine în inlineData
        if (!empty($part['inlineData']['data'])) {
            $image_base64 = $part['inlineData']['data'];
            $mime_type = $part['inlineData']['mimeType'] ?? 'image/png';
            error_log('Image found in part ' . $index . ', mime type: ' . $mime_type . ', data length: ' . strlen($image_base64));
            return save_base64_image($image_base64, $mime_type, 'gemini');
        }
        
        // Verificăm dacă există text în part (poate fi un mesaj de eroare sau modelul returnează text în loc de imagine)
        if (!empty($part['text'])) {
            $text_content = $part['text'];
            error_log('Text found in part ' . $index . ': ' . substr($text_content, 0, 200));
            
            // Dacă modelul returnează doar text și nu există imagini, înseamnă că modelul nu generează imagini
            // Poate că modelul nu este disponibil sau nu funcționează cum ne așteptăm
            if (strpos(strtolower($text_content), 'imagine') !== false || strpos(strtolower($text_content), 'image') !== false) {
                error_log('WARNING: Model returned text description instead of image. This suggests the model may not support image generation or is not available.');
            }
        }
    }

    // Dacă nu am găsit imagini dar avem text, modelul probabil nu generează imagini
    $has_text_only = false;
    foreach ($parts as $part) {
        if (!empty($part['text']) && empty($part['inlineData'])) {
            $has_text_only = true;
            break;
        }
    }
    
    if ($has_text_only) {
        $error_msg = 'Modelul ' . $api_model . ' a returnat text în loc de imagine. ';
        $error_msg .= 'Posibile cauze: modelul nu este disponibil, nu suportă generarea de imagini, sau trebuie folosit un alt model (ex: imagen-4 sau gemini-3-pro-image-preview).';
        error_log('ERROR: ' . $error_msg);
        error_log('Parts structure: ' . json_encode($parts));
        error_log('Full response (first 1000 chars): ' . substr(json_encode($data), 0, 1000));
        return new WP_Error('no_image', $error_msg);
    }

    error_log('No image data found in any part. Parts structure: ' . json_encode($parts));
    error_log('Full response (first 1000 chars): ' . substr(json_encode($data), 0, 1000));
    return new WP_Error('no_image', 'No image data found in response. Finish reason: ' . $finish_reason);
}

// Helper function pentru salvarea imaginii base64
function save_base64_image($image_base64, $mime_type, $prefix = 'gemini')
{
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/temp-gemini-images';
    
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    $extension = 'png';
    if (strpos($mime_type, 'jpeg') !== false || strpos($mime_type, 'jpg') !== false) {
        $extension = 'jpg';
    } elseif (strpos($mime_type, 'webp') !== false) {
        $extension = 'webp';
    }
    
    $temp_filename = $prefix . '-' . time() . '-' . wp_generate_password(8, false) . '.' . $extension;
    $temp_filepath = $temp_dir . '/' . $temp_filename;
    
    $image_data = base64_decode($image_base64);
    if ($image_data !== false && file_put_contents($temp_filepath, $image_data)) {
        $image_url = $upload_dir['baseurl'] . '/temp-gemini-images/' . $temp_filename;
        error_log('Image saved: ' . $image_url);
        return [
            'data' => [
                [
                    'url' => $image_url
                ]
            ]
        ];
    }
    
    return new WP_Error('save_failed', 'Failed to save image file');
}

// --- Vertex AI Imagen API ---
function call_vertex_ai_imagen_api($project_id, $location, $service_account_json, $model, $prompt, $feedback = '')
{
    error_log('=== VERTEX AI IMAGEN API CALL START ===');
    error_log('Project ID: ' . $project_id);
    error_log('Location: ' . $location);
    error_log('Model: ' . $model);
    error_log('Prompt length: ' . strlen($prompt));
    
    if (empty($project_id) || empty($service_account_json)) {
        error_log('ERROR: Missing Vertex AI configuration');
        return new WP_Error('missing_vertex_config', 'Vertex AI Project ID și Service Account JSON sunt necesare.');
    }

    // Adăugăm feedback-ul la prompt dacă există
    $final_prompt = $prompt;
    
    // Dacă prompt-ul este JSON, încercăm să extragem conținutul text
    $decoded_prompt = json_decode($prompt, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_prompt)) {
        if (!empty($decoded_prompt['content'])) {
            $final_prompt = $decoded_prompt['content'];
        } elseif (!empty($decoded_prompt['prompt'])) {
            $final_prompt = $decoded_prompt['prompt'];
        } elseif (!empty($decoded_prompt['text'])) {
            $final_prompt = $decoded_prompt['text'];
        }
        error_log('Extracted text from JSON prompt, length: ' . strlen($final_prompt));
    }
    
    if (!empty($feedback)) {
        $final_prompt .= "\n Utilizează următorul feedback de la imaginea generată anterior pentru a îmbunătăți imaginea: " . $feedback;
    }

    // Obținem access token din Service Account JSON
    $service_account = json_decode($service_account_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($service_account['private_key'])) {
        error_log('ERROR: Invalid Service Account JSON');
        return new WP_Error('invalid_service_account', 'Service Account JSON invalid sau incomplet.');
    }

    // Generăm access token
    $access_token = get_vertex_ai_access_token($service_account);
    if (is_wp_error($access_token)) {
        error_log('ERROR: Failed to get access token: ' . $access_token->get_error_message());
        return $access_token;
    }
    
    error_log('Access token obtained successfully');

    // Endpoint Vertex AI pentru Imagen
    $endpoint = sprintf(
        URL_API_VERTEX_BASE,
        $location,
        $project_id,
        $location,
        $model
    );
    
    error_log('Endpoint: ' . $endpoint);

    $body = [
        'instances' => [
            [
                'prompt' => $final_prompt
            ]
        ],
        'parameters' => [
            'sampleCount' => 1,
            'aspectRatio' => '16:9',
            'safetyFilterLevel' => 'BLOCK_SOME',
            'personGeneration' => 'ALLOW_ALL'
        ]
    ];
    
    error_log('Request body: ' . wp_json_encode($body));

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode($body),
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        error_log('ERROR: wp_remote_post failed: ' . $response->get_error_message());
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);
    
    error_log('Response HTTP code: ' . $code);
    error_log('Response body (first 500 chars): ' . substr($raw, 0, 500));

    if ($code !== 200) {
        $error_msg = 'Vertex AI API Error (HTTP ' . $code . ')';
        if (isset($data['error']['message'])) {
            $error_msg .= ': ' . $data['error']['message'];
            error_log('Error message: ' . $data['error']['message']);
        }
        if (isset($data['error']['status'])) {
            error_log('Error status: ' . $data['error']['status']);
        }
        error_log('=== VERTEX AI IMAGEN API CALL END (ERROR) ===');
        return new WP_Error('vertex_api_error', $error_msg);
    }

    // Extragem imaginea din răspuns Vertex AI
    $image_url = '';
    
    if (!empty($data['predictions']) && !empty($data['predictions'][0])) {
        $prediction = $data['predictions'][0];
        error_log('Found prediction, keys: ' . implode(', ', array_keys($prediction)));
        
        // Verificăm dacă avem bytesBase64Encoded
        if (!empty($prediction['bytesBase64Encoded'])) {
            error_log('Found bytesBase64Encoded, length: ' . strlen($prediction['bytesBase64Encoded']));
            $image_base64 = $prediction['bytesBase64Encoded'];
            
            // Salvăm base64 într-un fișier temporar
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/temp-gemini-images';
            
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_filename = 'imagen-' . time() . '-' . wp_generate_password(8, false) . '.png';
            $temp_filepath = $temp_dir . '/' . $temp_filename;
            
            $image_data = base64_decode($image_base64);
            if ($image_data !== false && file_put_contents($temp_filepath, $image_data)) {
                $image_url = $upload_dir['baseurl'] . '/temp-gemini-images/' . $temp_filename;
                error_log('Image saved successfully. URL: ' . $image_url);
            } else {
                error_log('ERROR: Failed to save image file');
            }
        }
    }

    if (empty($image_url)) {
        error_log('ERROR: No image URL extracted from Vertex AI response');
        error_log('Response structure: ' . wp_json_encode($data));
        error_log('=== VERTEX AI IMAGEN API CALL END (NO IMAGE) ===');
        return new WP_Error('no_image', 'No image found in Vertex AI response. Response: ' . wp_json_encode($data));
    }

    error_log('Success: Image URL extracted: ' . $image_url);
    error_log('=== VERTEX AI IMAGEN API CALL END (SUCCESS) ===');
    
    return [
        'data' => [
            [
                'url' => $image_url
            ]
        ]
    ];
}

// Funcție helper pentru obținerea access token din Service Account
function get_vertex_ai_access_token($service_account)
{
    // Verificăm dacă avem cache pentru token
    $cache_key = 'vertex_ai_token_' . md5($service_account['client_email'] ?? '');
    $cached_token = get_transient($cache_key);
    
    if ($cached_token !== false) {
        error_log('Using cached access token');
        return $cached_token;
    }

    error_log('Generating new access token from Service Account');

    // Construim JWT claim
    $now = time();
    $jwt_header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];

    $jwt_claim = [
        'iss' => $service_account['client_email'],
        'scope' => URL_GOOGLE_CLOUD_PLATFORM_SCOPE,
        'aud' => URL_GOOGLE_OAUTH_TOKEN,
        'exp' => $now + 3600,
        'iat' => $now
    ];

    // Encodăm header și claim
    $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($jwt_header)));
    $base64_claim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($jwt_claim)));

    // Semnăm JWT
    $signature_input = $base64_header . '.' . $base64_claim;
    
    // Folosim OpenSSL pentru semnătură
    $private_key = openssl_pkey_get_private($service_account['private_key']);
    if (!$private_key) {
        error_log('ERROR: Invalid private key in Service Account');
        return new WP_Error('invalid_private_key', 'Invalid private key in Service Account');
    }

    openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
    openssl_free_key($private_key);

    $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $signature_input . '.' . $base64_signature;

    // Obținem access token
    $token_response = wp_remote_post(URL_GOOGLE_OAUTH_TOKEN, [
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body' => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($token_response)) {
        error_log('ERROR: Failed to get token from OAuth2: ' . $token_response->get_error_message());
        return $token_response;
    }

    $token_code = wp_remote_retrieve_response_code($token_response);
    $token_body = wp_remote_retrieve_body($token_response);
    $token_data = json_decode($token_body, true);

    if ($token_code !== 200 || empty($token_data['access_token'])) {
        error_log('ERROR: Token response code: ' . $token_code);
        error_log('Token response: ' . $token_body);
        return new WP_Error('token_error', 'Failed to obtain access token: ' . ($token_data['error_description'] ?? 'Unknown error'));
    }

    $access_token = $token_data['access_token'];
    $expires_in = isset($token_data['expires_in']) ? intval($token_data['expires_in']) - 60 : 3540; // -60 sec pentru buffer

    // Salvăm în cache
    set_transient($cache_key, $access_token, $expires_in);
    error_log('Access token cached for ' . $expires_in . ' seconds');

    return $access_token;
}


// --- AI Prompts & Helper Functions ---


function generate_ai_browsing_prompt($news_sources, $category_name, $latest_titles_str, $final_instructions, $length_instruction)
{
    return Auto_Ai_News_Poster_Prompts::get_ai_browsing_prompt($news_sources, $category_name, $latest_titles_str, $final_instructions, $length_instruction);
}

function generate_retry_browsing_prompt($category_name)
{
    return Auto_Ai_News_Poster_Prompts::get_retry_browsing_prompt($category_name);
}

function generate_dalle_abstraction_system_message()
{
    return Auto_Ai_News_Poster_Prompts::get_dalle_abstraction_system_message();
}

function generate_dalle_abstraction_user_message($original_prompt)
{
    return Auto_Ai_News_Poster_Prompts::get_dalle_abstraction_user_message($original_prompt);
}

function get_photorealism_instructions()
{
    return Auto_Ai_News_Poster_Prompts::get_photorealism_instructions();
}


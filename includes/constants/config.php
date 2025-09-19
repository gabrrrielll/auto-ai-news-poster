<?php

// Funcție pentru generarea promptului
const URL_API_OPENAI = 'https://api.openai.com/v1/chat/completions';
const URL_API_IMAGE = 'https://api.openai.com/v1/images/generations';

function generate_custom_source_prompt($article_text_content, $additional_instructions = '')
{
    // Construim prompt-ul de bază
    $prompt = "Ești un jurnalist expert care scrie pentru o publicație de știri din România. Sarcina ta este să scrii un articol de știri complet nou și original în limba română, bazat pe informațiile din textul furnizat. Urmează aceste reguli stricte:\n";
    $prompt .= "1. **NU menționa niciodată** 'textul furnizat', 'articolul sursă', 'materialul analizat' sau orice expresie similară. Articolul trebuie să fie independent și să nu facă referire la sursa ta de informație.\n";
    $prompt .= "2. **NU copia și lipi (copy-paste)** fragmente din textul sursă. Toate informațiile trebuie reformulate cu propriile tale cuvinte și integrate natural în noul articol.\n";
    $prompt .= "3. Scrie un articol obiectiv, bine structurat, cu un titlu captivant, un conținut informativ și o listă de etichete (tags) relevante.\n";
    $prompt .= "4. Scopul este să sintetizezi și să prezinți informațiile într-un format de știre proaspăt și original, nu să comentezi pe marginea textului sursă.\n";


    // Adăugăm instrucțiuni suplimentare, dacă există
    if (!empty($additional_instructions)) {
        $prompt .= 'Instrucțiuni suplimentare: ' . $additional_instructions . "\n";
    }

    // Adăugăm textul articolului sursă
    $prompt .= "\n--- Text Sursă pentru Analiză ---\n" . $article_text_content;

    return $prompt;
}


function generate_prompt($sources, $additional_instructions, $tags): string
{
    // Listă de etichete existente
    $existing_tags = get_tags(['hide_empty' => false]);
    $existing_tag_names = [];
    foreach ($existing_tags as $tag) {
        $existing_tag_names[] = $tag->name;
    }
    $existing_tag_list = implode(', ', $existing_tag_names);


    // Preluăm categoriile din baza de date și le adăugăm la prompt
    $categories = get_categories([
        'orderby' => 'name',
        'order' => 'ASC',
        'hide_empty' => false,
    ]);

    $category_names = [];
    foreach ($categories as $category) {
        $category_names[] = $category->name;
    }
    $category_list = implode(', ', $category_names);

    // Obținem categoria selectată din opțiunile de setări
    $options = get_option('auto_ai_news_poster_settings');

    $article_length_option = $options['article_length_option'] ?? 'same_as_source';
    $min_length = $options['min_length'] ?? 800;
    $max_length = $options['max_length'] ?? 1200;

    // Dimensiunea articolului
    if ($article_length_option === 'set_limits' && $min_length && $max_length) {
        $length_instruction = "între $min_length și $max_length cuvinte";
    } else {
        $length_instruction = 'similare cu articolul sursă in privinta numarului de cuvinte';
    }

    $selected_category_id = $options['specific_search_category'] ?? '';
    $selected_category_name = '';
    $current_date = date('Y/m/d') || 'azi';

    if ($selected_category_id) {
        // Extragem numele categoriei selectate dacă există
        $category = get_category($selected_category_id);
        if ($category) {
            $selected_category_name = $category->name;
        }
    }

    $prompt = "Urmează să generezi un articol optimizat SEO și pentru asta urmează pașii: \n";
    $prompt .= "IMPORTANT: NU INVENTA INFORMATII! Folosește DOAR informațiile exacte din sursele de știri. Nu adăuga detalii care nu sunt menționate în articolele originale.\n";

    $prompt .= 'Fă browsing pe următoarele surse de știri, descoperă ultimele știri publicate în data curentă (' . date('Y/m/d') . ') și nu căuta informații publicate înainte de această dată';

    if ($options['mode'] === 'auto') { // Daca este modul automat
        if ($options['auto_rotate_categories'] === 'yes') { // Daca se rotesc categoriile
            $selected_category_name = Auto_Ai_News_Poster_Api::get_next_category();

            $prompt .= " în categoria \"$selected_category_name\".\n";
        } else { // Daca NU se rotesc categoriile
            if (!empty($selected_category_name)) {
                $prompt .= " în categoria \"$selected_category_name\".\n";
            } else {
                $prompt .= " din toate categoriile disponibile.\n";
            }
        }
    } else { // Daca este modul manual
        if (!empty($selected_category_name)) {
            $prompt .= " în categoria \"$selected_category_name\".\n";
        } else {
            $prompt .= " din toate categoriile disponibile.\n";
        }

    }

    $last_category_titles = Auto_Ai_News_Poster_Api::getLastCategoryTitles($selected_category_name, 3);
    $prompt .= "(Atentie! Nu extrage informatii care contin subiectele existente in aceste ultime articole deja publicate: $last_category_titles )\n";

    $prompt .= implode("\n", $sources);
    $prompt .= "\n Folosește doar informația pentru a compune un nou articol unic care să respecte informația și să conțină toate detaliile acesteia.\n";
    $prompt .= "DIMENSIUNE OBLIGATORIE: Articolul trebuie să aibă exact $length_instruction. Nu mai mult, nu mai puțin. Numără cuvintele și respectă această cerință.\n";
    $prompt .= "ATENȚIE: Nu adăuga informații care nu sunt în sursele de știri! Dacă sursele menționează o listă specifică (ex: filme, persoane, evenimente), copiază EXACT aceeași listă, nu o modifica sau nu adăuga alte elemente.\n";
    $prompt .= $additional_instructions !== '' ? "\n Instrucțiuni suplimentare: " . $additional_instructions : '';

    // Verificăm dacă trebuie să generăm etichete
    $generate_tags_option = $options['generate_tags'] ?? 'yes';

    $prompt .= "\n Include următoarele informații în răspunsul tău:\n";
    $prompt .= "1. Generează un titlu relevant pentru articol, intrigant si care să stărnească curiozitatea cititorului in a citi articolul generat (title).\n";
    $prompt .= "2. Generează 1-3 etichete relevante (tags) și asigură-te că acestea sunt folosite de cel puțin două ori în conținutul articolului pentru optimizare SEO  și asigură-te că fiecare cuvânt începe cu majusculă.\n";
    $prompt .= " Etichetele sugerate pot fi din lista existentă de etichete: '$existing_tag_list'. Dacă nu există potriviri relevante, sugerează noi etichete.\n";
    $prompt .= "3. Numește numele categoriei care se potrivește mai bine din lista: '$category_list'.\n";
    $prompt .= "4. Creează un rezumat al articolului (summary).\n";
    $prompt .= "5. Generează un articol cu respectarea strictă a dimensiunii $length_instruction, detaliat, folosește un stil jurnalistic în exprimare, nu include titlul în interiorul acestuia și nu omite nici un aspect din informația preluată.";
    $prompt .= ' ATENȚIE: Nu adăuga informații care nu sunt în sursele de știri! Dacă sursele menționează o listă specifică (ex: filme, persoane, evenimente), copiază EXACT aceeași listă, nu o modifica sau nu adăuga alte elemente.';
    $prompt .= " Structura articolului (poate să includă dacă consideri necesar - una, două sau trei subtitluri semantice de tip H2, H3) și să fie formatată în HTML pentru o structură SEO-friendly astfel încât să aibă și un design plăcut (content).\n";
    $prompt .= "6. Copiază adresele URL complete ale articolelor pe care le-ai parsat și de unde ai extras informația (sources).\n";
    $prompt .= "7. Copiază identic titlurile articolelor pe care le-ai parsat și de unde ai extras informația (source_titles).\n";
    $prompt .= "8. Copiază adresele URL complete ale imaginilor reprezentative ale articolelor de unde ai extras informația (images).\n";

    return $prompt;
}

// Funcție pentru apelarea API-ului OpenAI
function call_openai_api($api_key, $prompt)
{
    error_log('🔥 CALL_OPENAI_API() STARTED');

    // Obținem modelul selectat din setări
    $options = get_option('auto_ai_news_poster_settings', []);
    $selected_model = $options['ai_model'] ?? 'gpt-4o';

    error_log('🤖 AI API CONFIGURATION:');
    error_log('   - Selected model: ' . $selected_model);
    error_log('   - API URL: ' . URL_API_OPENAI);
    error_log('   - API Key length: ' . strlen($api_key));
    error_log('   - Prompt length: ' . strlen($prompt));

    // Preluăm setările pentru a vedea dacă trebuie să generăm etichete
    // $options = get_option('auto_ai_news_poster_settings', []); // Deja preluat mai sus
    // $generate_tags_option = $options['generate_tags'] ?? 'yes'; // Nu mai este necesar aici pentru a condiționa required

    // Setăm toate proprietățile ca fiind obligatorii (inclusiv tags)
    $required_properties = ['title', 'content', 'summary', 'category', 'tags', 'images', 'sources', 'source_titles'];

    $request_body = [
        'model' => $selected_model,  // Model selectat din setări
        // 'temperature' => 0.1,  // Foarte strict, respectă exact sursa (0.0-1.0) - eliminat conform erorii API
        'messages' => [
            ['role' => 'system', 'content' => 'You are a precise news article generator. NEVER invent information. Use ONLY the exact information provided in sources. If sources mention specific lists (movies, people, events), copy them EXACTLY without modification. Always respect the required word count.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'response_format' => [
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
                            'description' => 'Etichete relevante pentru articol (opțional)',
                            'items' => [
                                'type' => 'string'
                            ]
                        ],
                        'images' => [
                            'type' => 'array',
                            'description' => 'URL-urile imaginilor relevante din articolele sursă',
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
        ],
        'max_completion_tokens' => 9000,
    ];

    error_log('📤 REQUEST BODY TO OPENAI:');
    error_log('   - JSON: ' . json_encode($request_body, JSON_PRETTY_PRINT));

    $response = wp_remote_post(URL_API_OPENAI, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($request_body),
        'timeout' => 300, // Mărit timeout-ul la 300 de secunde (5 minute)
    ]);

    error_log('📥 OPENAI API RESPONSE:');
    if (is_wp_error($response)) {
        error_log('❌ WP Error: ' . $response->get_error_message());
    } else {
        error_log('✅ Response status: ' . wp_remote_retrieve_response_code($response));
        error_log('📄 Response headers: ' . print_r(wp_remote_retrieve_headers($response), true));
        error_log('💬 Response body: ' . wp_remote_retrieve_body($response));
    }

    return $response;
}


// Funcție pentru apelarea API-ului OpenAI folosind DALL-E 3 pentru generarea de imagini
function call_openai_image_api($api_key, $summary, $tags = [], $feedback = '')
{
    error_log('🎨 call_openai_image_api() STARTED');

    // Creăm un prompt pentru generarea imaginii
    $prompt = 'Generează o imagine cât mai naturală și realistă, fără a utiliza texte sau cuvinte în interiorul imaginii, având ca temă aceste etichete: ';
    if (!empty($tags)) {
        $prompt .=  implode(', ', $tags) . '.';
    }
    $prompt .= "Foloseste todata acest rezumat ca si context pentru a desena imaginea:'" . $summary . "'.Evită să desenezi chipurile specifice a oamenilor cănd se face referire la anumite persoane in mod direct, caz in care trebuie sa desenezi personajele din spate . ";

    if (!empty($feedback)) {
        $prompt .= "\n Utilizează următorul feedback de la imaginea generată anterior pentru a îmbunătăți imaginea: " . $feedback;
    }

    error_log('🎨 DALL-E API Configuration:');
    error_log('   - API Key length: ' . strlen($api_key));
    error_log('   - Prompt: ' . $prompt);
    error_log('   - Prompt length: ' . strlen($prompt) . ' characters');

    $request_body = [
        'model' => 'dall-e-3',  // Modelul DALL-E 3 pentru imagini
        'prompt' => $prompt,
        'size' => '1792x1024',
        'quality' => 'standard',
        'n' => 1,
        'style' => 'natural'
    ];

    error_log('📤 DALL-E API Request Body: ' . json_encode($request_body));

    // Apelăm API-ul OpenAI pentru generarea imaginii
    $response = wp_remote_post(URL_API_IMAGE, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($request_body),
        'timeout' => 90,
    ]);

    if (is_wp_error($response)) {
        error_log('❌ DALL-E API Error: ' . $response->get_error_message());
    } else {
        error_log('✅ DALL-E API Response status: ' . wp_remote_retrieve_response_code($response));
    }

    return $response;
}

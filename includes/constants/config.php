<?php

// Funcție pentru generarea promptului
const URL_API_OPENAI = 'https://api.openai.com/v1/chat/completions';
const URL_API_IMAGE = 'https://api.openai.com/v1/images/generations';

function generate_custom_source_prompt($article_text_content, $additional_instructions = '', $source_link = '')
{
    $options = get_option('auto_ai_news_poster_settings');

    // Obținem setările de lungime a articolului
    $article_length_option = $options['article_length_option'] ?? 'same_as_source';
    $min_length = $options['min_length'] ?? 800; // Default values
    $max_length = $options['max_length'] ?? 1200; // Default values

    $length_instruction = '';
    if ($article_length_option === 'set_limits' && $min_length && $max_length) {
        $length_instruction = "Articolul trebuie să aibă între {$min_length} și {$max_length} de cuvinte.";
    } else {
        $length_instruction = 'Articolul trebuie să aibă o lungime similară cu textul sursă.';
    }

    $parse_link_instructions = $options['parse_link_ai_instructions'] ?? '';

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

    // Construim prompt-ul de bază
    $prompt = "Ești un jurnalist expert care scrie pentru o publicație de știri din România. Sarcina ta este să scrii un articol de știri complet nou și original în limba română, bazat pe informațiile din textul furnizat. Urmează aceste reguli stricte:\n";
    
    // Adăugăm instrucțiuni specifice pentru identificarea articolului corect folosind linkul
    if (!empty($source_link)) {
        $prompt .= "**IMPORTANT - CLARIFICARE PROCES:**\n";
        $prompt .= "Textul de mai jos a fost DEJA extras și parsat din linkul: {$source_link}\n";
        $prompt .= "**NU trebuie să parsezi linkul sau să ceri conținut.** Textul este deja disponibil mai jos.\n";
        $prompt .= "**SARCINA TA:** Textul parsat poate conține mai multe articole sau informații adiacente (articole recomandate, articole similare, meniuri, reclame, etc.).\n";
        $prompt .= "Folosește linkul ca referință pentru a identifica și selecta DOAR articolul care corespunde acestui link specific.\n\n";
        
        $prompt .= "**CUM SĂ IDENTIFICI ARTICOLUL CORECT:**\n";
        $prompt .= "1. Analizează linkul și identifică cuvintele cheie din URL (de exemplu, din linkul \"bogdan-ivan-trimite-corpul-de-control-la-hidroelectrica\" identifică subiectul: Bogdan Ivan, Corp de Control, Hidroelectrica)\n";
        $prompt .= "2. Caută în textul parsat articolul care conține aceste cuvinte cheie și subiecte\n";
        $prompt .= "3. Identifică titlul articolului căutat (care de obicei apare în slug-ul URL-ului)\n";
        $prompt .= "4. Selectează DOAR conținutul acelui articol specific, ignorând complet:\n";
        $prompt .= "   - Alte articole recomandate sau similare\n";
        $prompt .= "   - Meniuri, navigare, anteturi, subsoluri\n";
        $prompt .= "   - Reclame sau promovări\n";
        $prompt .= "   - Orice alt conținut care nu face parte din articolul indicat de link\n\n";
        
        $prompt .= "**DUPĂ IDENTIFICARE:**\n";
        $prompt .= "Odată ce ai identificat articolul corect din textul parsat, generează un articol NOU și ORIGINAL bazat pe informațiile din acel articol identificat.\n";
        $prompt .= "**NU** trebuie să parsezi linkul sau să ceri conținut - tot ce ai nevoie este deja în textul furnizat mai jos.\n\n";
    }
    
    $prompt .= "1. **NU menționa niciodată** 'textul furnizat', 'articolul sursă', 'materialul analizat' sau orice expresie similară. Articolul trebuie să fie independent și să nu facă referire la sursa ta de informație.\n";
    $prompt .= "2. **Reformulează** cu propriile tale cuvinte informațiile din textul furnizat, integrându-le natural în noul articol. **NU copia și lipi (copy-paste) fragmente din textul sursă.**\n";
    $prompt .= "3. Scrie un articol obiectiv, bine structurat, cu un titlu captivant, un conținut informativ și o listă de etichete (tags) relevante. **Păstrează toate faptele, detaliile, numele, numerele și listele (ex: liste de filme, produse, evenimente) EXACT așa cum apar în textul sursă. Nu omite și nu adăuga elemente noi în liste.** {$length_instruction}\n";
    $prompt .= "4. Articolul trebuie să fie o reformulare fidelă a textului sursă, nu un sumar sau un comentariu personal. Menține tonul și perspectiva originală.\n";
    $prompt .= "5. **ATENȚIE la conținutul non-articolistic:** Identifică și ignoră blocurile de text care reprezintă liste de servicii, recomandări de produse, reclame, secțiuni de navigare, subsoluri, anteturi sau orice alt conținut care nu face parte direct din articolul principal. Nu le reproduce în textul generat, chiar dacă apar în textul sursă.\n";
    $prompt .= "6. **EXCLUDE TOT CE E NON-EDITORIAL:** Ignoră complet orice text care arată ca tabele de comparație, liste de prețuri, specificații tehnice listate, matrice de planuri, comparații side-by-side, și orice alt format care nu este text editorial continuu. Dacă vezi linii de tipul: \"Brightspeed\", \"Spectrum\", \"T-Mobile Home Internet\", \"Verizon Fios\" cu prețuri și specificații - IGNORĂ TOTUL. Nu menționa deloc astfel de liste sau tabele.\n";
    $prompt .= "7. **EXCLUDE RECLAMELE:** Dacă textul sursă conține reclame sau promovări de produse/servicii, NU le include în articol. Focalizează-te doar pe conținutul editorial/news, nu pe secțiuni comerciale.\n";
    $prompt .= "8. **LIMBA ROMÂNĂ STRICTĂ:** Tot articolul trebuie să fie în limba română. Traduce TOATE citatele, frazele și expresiile din engleză sau alte limbi în română. NU copia citate în limba originală. Dacă apare o citare în engleză în textul sursă, tu trebuie să o traduci în română în cadrul articolului. DOAR termenii tehnici fără echivalent în română pot fi menționați în limba originală.\n";
    $prompt .= "9. **NU adăuga concluzii:** Articolul trebuie să se încheie natural, fără a adăuga secțiuni de tipul \"Concluzie\", \"În concluzie\", \"Pentru a rezuma\", etc. Articolul se termină când ai prezentat toate informațiile relevante.\n";
    $prompt .= "10. **Generare etichete:** Generează între 1 și 3 etichete relevante (tags) pentru articol. Fiecare cuvânt trebuie să înceapă cu majusculă.\n";
    $prompt .= "11. **Generare meta descriere:** Creează o meta descriere de maximum 160 de caractere, optimizată SEO.\n";
    $prompt .= "12. **Selectare categorie:** Analizează conținutul articolului și selectează categoria care se potrivește cel mai bine din următoarea listă de categorii existente pe site: '$category_list'. IMPORTANT: Nu inventa o categorie nouă, trebuie să alegi DOAR una dintre categoriile din listă.\n";
    $prompt .= "13. **Respectă strict structura JSON** cu titlu, conținut, etichete (tags), categorie (category) și rezumat (summary). Asigură-te că articolul este obiectiv și bine formatat.\n";

    // Dacă există instrucțiuni suplimentare, le adăugăm acum
    if (!empty($parse_link_instructions)) {
        $prompt .= "\n**Instrucțiuni suplimentare:** {$parse_link_instructions}\n";
    }

    $prompt .= "\n**IMPORTANT - Formatarea articolului:**\n";
    $prompt .= "- NU folosi titluri explicite precum \"Introducere\", \"Dezvoltare\", \"Concluzie\" în text\n";
    $prompt .= "- Articolul trebuie să fie un text fluent și natural, fără secțiuni marcate explicit\n";
    $prompt .= "- Folosește formatare HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly\n";
    $prompt .= "- Subtitlurile H2/H3 trebuie să fie descriptive și relevante pentru conținut, nu generice\n";
    $prompt .= "- Fiecare paragraf să aibă sens complet și să fie bine conectat cu următorul\n";
    $prompt .= "- **Respectă structura de paragrafe și subtitluri (H2, H3) din textul sursă pentru a menține ierarhia informației.**\n";

    $prompt .= "\n**Format de răspuns OBLIGATORIU:**\n";
    $prompt .= "**CRITICAL:** Răspunsul tău trebuie să fie EXACT UN OBIECT JSON, fără niciun alt text înainte sau după.\n";
    $prompt .= "**NU** adăuga text explicativ, mesaje, întrebări sau cereri de clarificare.\n";
    $prompt .= "**NU** spune că nu poți accesa linkul sau că ai nevoie de conținut - textul este deja furnizat mai jos.\n";
    $prompt .= "**DOAR** returnează obiectul JSON cu articolul generat. Structura trebuie să fie următoarea:\n";
    $prompt .= "{\n";
    $prompt .= "  \"title\": \"Titlul articolului generat de tine\",\n";
    $prompt .= "  \"content\": \"Conținutul complet al articolului, formatat în HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly. NU folosi titluri explicite precum Introducere/Dezvoltare/Concluzie.\",\n";
    $prompt .= "  \"summary\": \"O meta descriere de maximum 160 de caractere, optimizată SEO.\",\n";
    $prompt .= "  \"tags\": [\"intre_1_si_3_etichete_relevante\"],\n";
    $prompt .= "  \"category\": \"Numele categoriei selectate din lista de categorii existente\"\n";
    $prompt .= "}\n";

    // Adăugăm instrucțiuni suplimentare, dacă există (pentru apelurile manuale unde se poate adăuga text extra)
    if (!empty($additional_instructions)) {
        $prompt .= 'Instrucțiuni suplimentare de moment: ' . $additional_instructions . "\n";
    }

    // Adăugăm linkul ca referință înainte de textul sursă
    if (!empty($source_link)) {
        $prompt .= "\n--- LINK REFERINȚĂ (textul a fost DEJA extras din acest link) ---\n";
        $prompt .= "Link sursă: {$source_link}\n";
        $prompt .= "**REȚINE:** Textul de mai jos a fost DEJA parsat din acest link. NU trebuie să parsezi linkul sau să ceri conținut.\n";
        $prompt .= "Folosește linkul doar ca referință pentru a identifica care parte din textul de mai jos este articolul corect.\n";
        $prompt .= "Analizează cuvintele cheie din link (ex: din \"bogdan-ivan-trimite-corpul-de-control\" identifică subiectul) și caută în text articolul care conține aceste subiecte.\n\n";
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

    return $prompt;
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
function call_ai_api($prompt)
{
    $options = get_option('auto_ai_news_poster_settings');
    $use_gemini = isset($options['use_gemini']) && $options['use_gemini'] === 'yes';
    if ($use_gemini) {
        $api_key = $options['gemini_api_key'] ?? '';
        $model = $options['gemini_model'] ?? 'gemini-1.5-pro';
        // Dacă modelul nu are prefixul "models/", îl adăugăm pentru compatibilitate cu API-ul
        if (strpos($model, 'models/') !== 0) {
            $model = 'models/' . $model;
        }
        return call_gemini_api($api_key, $model, $prompt);
    }
    // default to OpenAI
    $api_key = $options['chatgpt_api_key'] ?? '';
    return call_openai_api($api_key, $prompt);
}

function call_openai_api($api_key, $prompt)
{

    // Obținem modelul selectat din setări
    $options = get_option('auto_ai_news_poster_settings', []);
    $selected_model = $options['ai_model'] ?? 'gpt-4o';


    // Preluăm setările pentru a vedea dacă trebuie să generăm etichete
    // $options = get_option('auto_ai_news_poster_settings', []); // Deja preluat mai sus
    // $generate_tags_option = $options['generate_tags'] ?? 'yes'; // Nu mai este necesar aici pentru a condiționa required

    // Setăm toate proprietățile ca fiind obligatorii (inclusiv tags)
    $required_properties = ['title', 'content', 'summary', 'category', 'tags', 'sources', 'source_titles'];

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
        ],
        'max_completion_tokens' => 128000,
    ];


    $response = wp_remote_post(URL_API_OPENAI, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($request_body),
        'timeout' => 300, // Mărit timeout-ul la 300 de secunde (5 minute)
    ]);

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
    $use_gemini = isset($options['use_gemini']) && $options['use_gemini'] === 'yes';
    
    error_log('Use Gemini: ' . ($use_gemini ? 'YES' : 'NO'));
    
    if ($use_gemini) {
        // NOTĂ: Generarea de imagini cu Gemini/Imagen prin Generative Language API nu este disponibilă
        // Modelele Imagen și Gemini image generation necesită Vertex AI API
        // Pentru moment, returnăm o eroare informativă și sugerăm folosirea OpenAI DALL-E
        error_log('WARNING: Gemini image generation requested, but not available through Generative Language API');
        error_log('Imagen 3 and Gemini image models require Vertex AI API configuration');
        
        $api_key = $options['gemini_api_key'] ?? '';
        $imagen_model = $options['imagen_model'] ?? 'gemini-2.5-flash-image-exp';
        
        error_log('Gemini API Key present: ' . (!empty($api_key) ? 'YES' : 'NO'));
        error_log('Imagen model from settings: ' . $imagen_model);
        
        // Returnăm eroare informativă
        return new WP_Error('gemini_image_not_available', 
            'Generarea de imagini cu Gemini/Imagen nu este disponibilă prin Generative Language API. ' .
            'Modelele Imagen 3 și Gemini image generation necesită configurarea Vertex AI API. ' .
            'Pentru moment, te rugăm să folosești OpenAI DALL-E pentru generarea de imagini sau să configurezi Vertex AI API.');
    }
    
    // Default to OpenAI
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
        'timeout' => 90,
    ]);

    return $response;
}

// --- Google Gemini (text) ---
function call_gemini_api($api_key, $model, $prompt)
{
    if (empty($api_key)) {
        return ['error' => 'Missing Gemini API key'];
    }

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($api_key);

    $body = [
        'contents' => [
            [
                'parts' => [ ['text' => $prompt] ]
            ]
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body' => wp_json_encode($body),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code !== 200) {
        return ['error' => 'Gemini HTTP ' . $code, 'raw' => $raw];
    }

    // Extract text
    $text = '';
    if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $data['candidates'][0]['content']['parts'][0]['text'];
    }
    return [ 'choices' => [ ['message' => ['content' => $text]] ] ];
}

// --- Google Gemini (image generation via Generative Language API) ---
// Modele disponibile: gemini-2.0-flash-exp (cu responseModalities), imagen-3-generate-001 (prin endpoint separat)
function call_gemini_image_api($api_key, $model, $prompt, $feedback = '')
{
    error_log('=== GEMINI IMAGE API CALL START ===');
    error_log('Model received: ' . $model);
    error_log('API Key present: ' . (!empty($api_key) ? 'YES (length: ' . strlen($api_key) . ')' : 'NO'));
    error_log('Prompt length: ' . strlen($prompt));
    error_log('Feedback: ' . (!empty($feedback) ? $feedback : 'NONE'));
    
    if (empty($api_key)) {
        error_log('ERROR: Missing Gemini API key');
        return new WP_Error('missing_api_key', 'Missing Gemini API key');
    }

    // Adăugăm feedback-ul la prompt dacă există
    $final_prompt = $prompt;
    
    // Dacă prompt-ul este JSON, încercăm să extragem conținutul text
    $decoded_prompt = json_decode($prompt, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_prompt)) {
        // Dacă este JSON valid, încercăm să extragem textul din câmpurile relevante
        if (!empty($decoded_prompt['content'])) {
            $final_prompt = $decoded_prompt['content'];
        } elseif (!empty($decoded_prompt['prompt'])) {
            $final_prompt = $decoded_prompt['prompt'];
        } elseif (!empty($decoded_prompt['text'])) {
            $final_prompt = $decoded_prompt['text'];
        } else {
            // Dacă nu găsim un câmp text, folosim întregul JSON ca string
            $final_prompt = $prompt;
        }
        error_log('Extracted text from JSON prompt, length: ' . strlen($final_prompt));
    }
    
    if (!empty($feedback)) {
        $final_prompt .= "\n Utilizează următorul feedback de la imaginea generată anterior pentru a îmbunătăți imaginea: " . $feedback;
    }

    // Mapăm numele modelelor la numele corecte din API
    // Notă: Modelele Gemini standard nu suportă generarea de imagini prin Generative Language API
    // Imagen 3 necesită Vertex AI API, nu Generative Language API
    // Pentru moment, returnăm o eroare informativă
    $model_mapping = [
        'gemini-2.5-flash-image-exp' => null, // Nu este disponibil prin Generative Language API
        'gemini-3-pro-image-preview' => null, // Nu este disponibil prin Generative Language API
        'imagen-3' => null // Necesită Vertex AI API
    ];
    
    // Verificăm dacă modelul este disponibil prin Generative Language API
    if (isset($model_mapping[$model]) && $model_mapping[$model] === null) {
        error_log('ERROR: Model ' . $model . ' is not available through Generative Language API');
        error_log('Imagen 3 and Gemini image models require Vertex AI API, not Generative Language API');
        return new WP_Error('model_not_available', 
            'Modelul selectat (' . $model . ') nu este disponibil prin Generative Language API. ' .
            'Pentru generarea de imagini cu Gemini/Imagen, este necesară configurarea Vertex AI API. ' .
            'Alternativ, poți folosi OpenAI DALL-E pentru generarea de imagini.');
    }
    
    // Dacă ajungem aici, încercăm cu modelul mapat (dar nu ar trebui să ajungem aici)
    $api_model = $model_mapping[$model] ?? 'gemini-2.0-flash-exp';
    error_log('Mapped API model: ' . $api_model);
    
    // Încercăm cu generateContent, dar probabil va eșua
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($api_model) . ':generateContent?key=' . urlencode($api_key);
    error_log('Using endpoint: ' . str_replace($api_key, '***HIDDEN***', $endpoint));
    
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
            'responseModalities' => ['IMAGE'] // Specificăm că vrem doar imagini
        ]
    ];
    
    error_log('Request body: ' . wp_json_encode($body));

    $response = wp_remote_post($endpoint, [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body' => wp_json_encode($body),
        'timeout' => 120, // Timeout mai mare pentru generarea de imagini
    ]);

    if (is_wp_error($response)) {
        error_log('ERROR: wp_remote_post failed: ' . $response->get_error_message());
        error_log('ERROR code: ' . $response->get_error_code());
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);
    
    error_log('Response HTTP code: ' . $code);
    error_log('Response body (first 500 chars): ' . substr($raw, 0, 500));
    
    if ($code !== 200) {
        error_log('ERROR: Non-200 response code');
        error_log('Full response length: ' . strlen($raw));
        error_log('Full response: ' . ($raw ? $raw : '(EMPTY)'));
        
        // Dacă răspunsul este gol sau 404, înseamnă că endpoint-ul nu există
        if ($code === 404 && empty($raw)) {
            error_log('ERROR: 404 with empty response - endpoint does not exist');
            error_log('Imagen 3 might not be available through Generative Language API');
            error_log('Consider using Vertex AI API instead');
        }
        
        $error_msg = 'Gemini Image API Error (HTTP ' . $code . ')';
        if (!empty($raw)) {
            if (isset($data['error']['message'])) {
                $error_msg .= ': ' . $data['error']['message'];
                error_log('Error message from API: ' . $data['error']['message']);
            }
            if (isset($data['error']['status'])) {
                $error_msg .= ' [' . $data['error']['status'] . ']';
                error_log('Error status: ' . $data['error']['status']);
            }
            if (isset($data['error']['details'])) {
                error_log('Error details: ' . wp_json_encode($data['error']['details']));
            }
            if (empty($data['error']['message'])) {
                $error_msg .= ': ' . substr($raw, 0, 200);
            }
        } else {
            $error_msg .= ': Endpoint not found (empty response). Imagen 3 might require Vertex AI API instead of Generative Language API.';
        }
        error_log('=== GEMINI IMAGE API CALL END (ERROR) ===');
        return new WP_Error('gemini_api_error', $error_msg);
    }
    
    error_log('Success: Response code 200');

    // Extragem imaginea din răspuns Gemini Flash (generateContent cu responseModalities)
    error_log('Parsing response data...');
    error_log('Response keys: ' . implode(', ', array_keys($data ?? [])));
    
    $image_url = '';
    
    // Pentru generateContent cu responseModalities, răspunsul vine în candidates[0].content.parts
    if (!empty($data['candidates']) && !empty($data['candidates'][0]['content']['parts'])) {
        error_log('Found candidates with ' . count($data['candidates'][0]['content']['parts']) . ' parts');
        foreach ($data['candidates'][0]['content']['parts'] as $index => $part) {
            error_log('Processing part ' . ($index + 1) . ', keys: ' . implode(', ', array_keys($part ?? [])));
            
            // Verificăm dacă avem imagine în format base64
            if (!empty($part['inlineData']['data']) && !empty($part['inlineData']['mimeType'])) {
                error_log('Found inlineData with base64, length: ' . strlen($part['inlineData']['data']));
                $image_base64 = $part['inlineData']['data'];
                $mime_type = $part['inlineData']['mimeType'];
                error_log('MIME type: ' . $mime_type);
                
                // Determinăm extensia fișierului
                $extension = 'png';
                if (strpos($mime_type, 'jpeg') !== false || strpos($mime_type, 'jpg') !== false) {
                    $extension = 'jpg';
                } elseif (strpos($mime_type, 'webp') !== false) {
                    $extension = 'webp';
                }
                
                // Salvăm base64 într-un fișier temporar și returnăm URL-ul
                $upload_dir = wp_upload_dir();
                $temp_dir = $upload_dir['basedir'] . '/temp-gemini-images';
                
                error_log('Upload dir: ' . $upload_dir['basedir']);
                error_log('Temp dir: ' . $temp_dir);
                
                // Creăm directorul dacă nu există
                if (!file_exists($temp_dir)) {
                    wp_mkdir_p($temp_dir);
                    error_log('Created temp directory');
                }
                
                // Generăm un nume de fișier unic
                $temp_filename = 'gemini-' . time() . '-' . wp_generate_password(8, false) . '.' . $extension;
                $temp_filepath = $temp_dir . '/' . $temp_filename;
                
                error_log('Saving to: ' . $temp_filepath);
                
                // Decodăm și salvăm base64
                $image_data = base64_decode($image_base64);
                if ($image_data !== false && file_put_contents($temp_filepath, $image_data)) {
                    // Returnăm URL-ul fișierului temporar
                    $image_url = $upload_dir['baseurl'] . '/temp-gemini-images/' . $temp_filename;
                    error_log('Image saved successfully. URL: ' . $image_url);
                    break;
                } else {
                    error_log('ERROR: Failed to save image file');
                }
            }
            // Verificăm dacă avem URL direct
            elseif (!empty($part['imageUrl'])) {
                error_log('Found imageUrl: ' . $part['imageUrl']);
                $image_url = $part['imageUrl'];
                break;
            } else {
                error_log('No inlineData or imageUrl found in part ' . ($index + 1));
            }
        }
    } else {
        error_log('WARNING: No candidates or parts found in response');
        error_log('Response structure: ' . wp_json_encode($data));
    }

    if (empty($image_url)) {
        error_log('ERROR: No image URL extracted from response');
        error_log('Full response data: ' . wp_json_encode($data));
        error_log('=== GEMINI IMAGE API CALL END (NO IMAGE) ===');
        return new WP_Error('no_image', 'No image found in Gemini response. Response: ' . wp_json_encode($data));
    }

    error_log('Success: Image URL extracted: ' . $image_url);
    error_log('=== GEMINI IMAGE API CALL END (SUCCESS) ===');
    
    // Returnăm în același format ca DALL-E pentru compatibilitate
    return [
        'data' => [
            [
                'url' => $image_url
            ]
        ]
    ];
}


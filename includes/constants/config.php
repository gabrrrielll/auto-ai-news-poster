<?php

// Funcție pentru generarea promptului
const URL_API_OPENAI = 'https://api.openai.com/v1/chat/completions';
const URL_API_IMAGE = 'https://api.openai.com/v1/images/generations';
const URL_API_DEEPSEEK = 'https://api.deepseek.com/chat/completions';

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
    $prompt .= "   **IMPORTANT - PĂSTREAZĂ LINKURILE:** Dacă textul sursă conține linkuri în formatul \"Text Link (URL)\" sau liste numerotate cu linkuri (ex: \"1. ChatGPT – chat.openai.com\", \"2. Claude AI – claude.ai\"), TREBUIE să le incluzi în articolul generat. Formatează linkurile în HTML folosind tag-uri <a href=\"URL\">Text Link</a>. Nu omite linkurile din liste sau din referințele menționate în articol.\n";
    $prompt .= "   **CRITICAL - LISTE NUMEROTATE CU LINKURI:** Dacă articolul sursă conține liste numerotate cu site-uri și linkuri (ex: \"1. ChatGPT – chat.openai.com\", \"2. Claude AI – claude.ai\", \"3. Perplexity AI – perplexity.ai\"), aceste liste TREBUIE să fie incluse COMPLET în articolul generat, cu toate numerele, numele site-urilor și linkurile corespunzătoare. Nu omite niciun element din astfel de liste. Formatează fiecare element al listei cu linkul HTML corespunzător.\n";
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
    $prompt .= "  \"content\": \"Conținutul complet al articolului, formatat în HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly. NU folosi titluri explicite precum Introducere/Dezvoltare/Concluzie. INCLUDE toate linkurile din textul sursă folosind tag-uri <a href=\\\"URL\\\">Text</a>.\",\n";
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
    $use_deepseek = isset($options['use_deepseek']) && $options['use_deepseek'] === 'yes';

    if ($use_gemini) {
        $api_key = $options['gemini_api_key'] ?? '';
        $model = $options['gemini_model'] ?? 'gemini-1.5-pro';
        // Dacă modelul nu are prefixul "models/", îl adăugăm pentru compatibilitate cu API-ul
        if (strpos($model, 'models/') !== 0) {
            $model = 'models/' . $model;
        }
        error_log('[AUTO_AI_NEWS_POSTER] AI request (provider=Gemini) model=' . $model . ' prompt_len=' . strlen((string) $prompt) . ' prompt_preview=' . substr((string) $prompt, 0, 250));
        return call_gemini_api($api_key, $model, $prompt);
    } elseif ($use_deepseek) {
         // Logica pentru DeepSeek (OpenAI-compatible)
        $api_key = $options['deepseek_api_key'] ?? '';
        $model = $options['deepseek_model'] ?? 'deepseek-chat';
        error_log('[AUTO_AI_NEWS_POSTER] AI request (provider=DeepSeek) model=' . $model . ' prompt_len=' . strlen((string) $prompt) . ' prompt_preview=' . substr((string) $prompt, 0, 250));
        return call_openai_api($api_key, $prompt, $model, URL_API_DEEPSEEK);
    }
    // default to OpenAI
    $api_key = $options['chatgpt_api_key'] ?? '';
    $selected_model = $options['ai_model'] ?? 'gpt-4o';
    error_log('[AUTO_AI_NEWS_POSTER] AI request (provider=OpenAI) model=' . $selected_model . ' prompt_len=' . strlen((string) $prompt) . ' prompt_preview=' . substr((string) $prompt, 0, 250));
    return call_openai_api($api_key, $prompt);
}

function call_openai_api($api_key, $prompt, $model = null, $api_url = URL_API_OPENAI)
{

    // Obținem modelul selectat din setări (doar dacă nu e specificat explicit)
    $selected_model = $model;
    if (empty($selected_model)) {
        $options = get_option('auto_ai_news_poster_settings', []);
        $selected_model = $options['ai_model'] ?? 'gpt-4o';
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
        // Folosim Generative Language API pentru generarea de imagini
        $api_key = $options['gemini_api_key'] ?? '';
        $imagen_model = $options['imagen_model'] ?? 'gemini-2.5-flash-image';
        
        error_log('Gemini API Key present: ' . (!empty($api_key) ? 'YES' : 'NO'));
        error_log('Imagen model from settings: ' . $imagen_model);
        
        if (empty($api_key)) {
            error_log('ERROR: Gemini API key missing');
            return new WP_Error('no_image_api', 'Cheia API Gemini lipsește pentru generarea imaginii.');
        }
        
        error_log('Calling call_gemini_image_api...');
        $result = call_gemini_image_api($api_key, $imagen_model, $dalle_prompt, $feedback);
        error_log('call_gemini_image_api result: ' . (is_wp_error($result) ? 'ERROR: ' . $result->get_error_message() : 'SUCCESS'));
        error_log('=== CALL_AI_IMAGE_API END ===');
        return $result;
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

    // Debug logs: model + request payload preview (fără chei API)
    error_log('[AUTO_AI_NEWS_POSTER] Gemini request model=' . $model . ' endpoint=v1beta:generateContent prompt_len=' . strlen((string) $prompt) . ' prompt_preview=' . substr((string) $prompt, 0, 250) . ' body_preview=' . substr(wp_json_encode($body), 0, 250));

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
    
    // Simulăm structura de răspuns OpenAI pentru compatibilitate cu functiile existente
    $simulated_choices = [ 'choices' => [ ['message' => ['content' => $text]] ] ];
    
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
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-4:generateImages';
        
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
            'timeout' => 120,
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
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($api_model) . ':generateContent';
    
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
        'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:predict',
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
        'scope' => 'https://www.googleapis.com/auth/cloud-platform',
        'aud' => 'https://oauth2.googleapis.com/token',
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
    $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
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

const AI_BROWSING_SYSTEM_MESSAGE = 'You are a precise news article generator. NEVER invent information. Use ONLY the exact information provided in sources. If sources mention specific lists (movies, people, events), copy them EXACTLY without modification. Always respect the required word count.';

function generate_ai_browsing_prompt($news_sources, $category_name, $latest_titles_str, $final_instructions, $length_instruction)
{
    return "
        **Rol:** Ești un redactor de știri expert în domeniul **{$category_name}**, specializat în găsirea celor mai recente și relevante subiecte.

        **Context:** Ai la dispoziție următoarele resurse și constrângeri:
        1. **Surse de informare preferate:**
        {$news_sources}
        2. **Categorie de interes:** {$category_name}
        3. **Ultimele articole publicate pe site-ul nostru în această categorie (EVITĂ ACESTE SUBIECTE):**
        - {$latest_titles_str}

        **IMPORTANT - Folosește web browsing:**
        Pentru a găsi știri recente, FOLOSEȘTE OBLIGATORIU funcția de web browsing pentru a căuta pe site-urile specificate. Nu inventa informații - accesează direct sursele pentru a găsi știri reale din ultimele 24-48 de ore.

        **Sarcina ta:**
        1. **Cercetare:** Folosește web browsing pentru a accesa și citi articole din sursele specificate. Caută subiecte foarte recente (din ultimele 24-48 de ore), importante și relevante pentru categoria **{$category_name}**.
        2. **Verificarea unicității:** Asigură-te că subiectul ales NU este similar cu niciunul dintre titlurile deja publicate. Dacă este, alege alt subiect din browsing.
        3. **Scrierea articolului:** {$final_instructions} {$length_instruction}
        4. **Generare titlu:** Creează un titlu concis și atractiv pentru articol.
        5. **Generare etichete:** Generează între 1 și 3 etichete relevante (cuvinte_cheie) pentru articol. Fiecare cuvânt trebuie să înceapă cu majusculă.
        6. **Generare prompt pentru imagine:** Propune o descriere detaliată (un prompt) pentru o imagine reprezentativă pentru acest articol.

        **IMPORTANT - Formatarea articolului:**
        - NU folosi titluri explicite precum \"Introducere\", \"Dezvoltare\", \"Concluzie\" în text
        - Articolul trebuie să fie un text fluent și natural, fără secțiuni marcate explicit
        - Folosește formatare HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly
        - Subtitlurile H2/H3 trebuie să fie descriptive și relevante pentru conținut, nu generice
        - Fiecare paragraf să aibă sens complet și să fie bine conectat cu următorul

        **Format de răspuns OBLIGATORIU:**
        Răspunsul tău trebuie să fie EXACT UN OBIECT JSON, fără niciun alt text înainte sau după. NU adăuga mai multe obiecte JSON. NU adăuga text explicativ. Structura trebuie să fie următoarea:
        {
          \"titlu\": \"Titlul articolului generat de tine\",
          \"continut\": \"Conținutul complet al articolului, formatat în HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly. NU folosi titluri explicite precum Introducere/Dezvoltare/Concluzie.\",
          \"imagine_prompt\": \"Descrierea detaliată pentru imaginea reprezentativă.\",
          \"meta_descriere\": \"O meta descriere de maximum 160 de caractere, optimizată SEO.\",
          \"cuvinte_cheie\": [\"intre_1_si_3_etichete_relevante\"]
        }

        **PASUL 1:** Începe prin a folosi web browsing pentru a căuta pe site-urile specificate și găsi știri recente din categoria {$category_name}.
        ";
}

function generate_retry_browsing_prompt($category_name)
{
    return "Scrie un articol de știri ca un jurnalist profesionist. \r\n\r\nCategoria: {$category_name}\r\n\r\nCerințe:\r\n- Titlu atractiv și descriptiv\r\n- Conținut fluent și natural, fără secțiuni marcate explicit\r\n- NU folosi titluri precum \"Introducere\", \"Dezvoltare\", \"Concluzie\"\r\n- Formatare HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly\r\n- Generează între 1 și 3 etichete relevante (cuvinte_cheie)\r\n- Limbă română\r\n- Stil jurnalistic obiectiv și informativ\r\n\r\nReturnează DOAR acest JSON:\r\n{\r\n  \"titlu\": \"Titlul articolului\",\r\n  \"continut\": \"Conținutul complet al articolului formatat în HTML, fără titluri explicite precum Introducere/Dezvoltare/Concluzie\",\r\n  \"meta_descriere\": \"Meta descriere SEO\",\r\n  \"cuvinte_cheie\": [\"intre_1_si_3_etichete_relevante\"]\r\n}";
}

function generate_dalle_abstraction_system_message()
{
    return 'Ești un asistent AI specializat în transformarea descrierilor de text în prompturi vizuale pentru generarea de imagini fotorealiste și naturale. Sarcina ta este să creezi un prompt care să genereze o imagine care să pară o fotografie reală realizată cu ocazia evenimentului descris în text. 

IMPORTANT - Stilul imaginii:
- Imaginea trebuie să fie FOTOREALISTĂ și NATURALĂ, ca o fotografie profesională de știri
- Stil: fotografie jurnalistică, natural lighting, composition profesională, depth of field realistă
- Calitate: high resolution, sharp focus, natural colors, realistic textures
- Perspectivă: unghi natural, ca și cum ar fi o fotografie făcută de un fotograf profesionist
- Evită stiluri artistice, abstracte sau ilustrații - doar fotografie reală
- Dacă apar oameni în imagine, aceștia trebuie să aibă trăsături specifice est-europene/românești, îmbrăcăminte și stil specific României sau Europei de Est, dacă nu se specifică altfel.

IMPORTANT - Conținutul:
- Elimină orice referință directă la evenimente politice sensibile, conflicte militare, violență explicită, sau conținut sensibil
- Concentrează-te pe aspectele vizuale și scenografice ale evenimentului, fără a încălca politicile de siguranță
- Dacă textul menționează persoane publice sau evenimente politice, transformă-le în scene generale și naturale (ex: oameni într-o sală de conferințe, oameni la un eveniment public, etc.)
- NU menționa nume specifice de persoane, țări sau termeni militari dacă pot cauza probleme de safety

IMPORTANT - Formatul promptului:
- Promptul trebuie să fie în limba română
- Include detalii despre iluminare naturală, compoziție, unghi de vedere
- Descrie scenele ca și cum ar fi fotografii reale de știri
- Folosește termeni fotografici: "fotografie profesională", "iluminare naturală", "compoziție jurnalistică", etc.';
}

function generate_dalle_abstraction_user_message($original_prompt)
{
    return "Transformă următoarea descriere într-un prompt vizual pentru generarea unei imagini FOTOREALISTE și NATURALE, ca o fotografie profesională de știri realizată cu ocazia evenimentului descris. Promptul trebuie să genereze o imagine care să pară o fotografie reală, nu o ilustrație sau artă abstractă: \"{$original_prompt}\"";
}

function get_photorealism_instructions()
{
    $prefix = 'Fotografie profesională de știri, fotorealistă și naturală, realizată cu ocazia evenimentului. ';
    $suffix = ' Stil: fotografie jurnalistică profesională, iluminare naturală, compoziție profesională, culori naturale, texturi realiste, sharp focus, high resolution. Imaginea trebuie să pară o fotografie reală, nu o ilustrație sau artă abstractă. Dacă apar oameni, ei trebuie să aibă trăsături românești/est-europene.';
    
    return ['prefix' => $prefix, 'suffix' => $suffix];
}

<?php
// Funcție pentru generarea promptului
const URL_API_OPENAI = 'https://api.openai.com/v1/chat/completions';
const URL_API_IMAGE = "https://api.openai.com/v1/images/generations";

function generate_custom_source_prompt($link, $additional_instructions): string
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
        $length_instruction = "similare cu articolul sursă in privinta numarului de cuvinte";
    }
    
    $prompt = "Analizează următorul articol furnizat la acest link: $link\n";
    // $prompt .= "Generează un articol care să respecte strict informațiile din acest link, dar scris într-un mod diferit. Păstrează aceeași lungime, detalii și structura jurnalistică.\n";
    $prompt .= "Generează un articol care să respecte strict si in totalitate informațiile din acest link, dar scris într-un mod diferit, unic si poate chiar imbogatit in exprimare. Este extrem de important ca articolul sa respecte strict dimensiunea $length_instruction. Nerespectarea acestei cerințe va invalida complet rezultatul generat.\n";
    $prompt .= $additional_instructions !== "" ? "\nInstrucțiuni suplimentare: " . $additional_instructions : "";
    $prompt .= "Include următoarele informații în răspunsul tău:\n";
    $prompt .= "1. Generează un titlu relevant pentru articol, intrigant si care să stărnească curiozitatea cititorului in a citi articolul generat (title).\n";
    $prompt .= "2. Generează 1-3 etichete relevante (tags) și asigură-te că acestea sunt folosite de cel puțin două ori în conținutul articolului pentru optimizare SEO  și asigură-te că fiecare cuvânt începe cu majusculă.\n";
    $prompt .= " Etichetele sugerate pot fi din lista existentă de etichete: '$existing_tag_list'. Dacă nu există potriviri relevante, sugerează noi etichete.\n";
    $prompt .= "3. Numește numele categoriei care se potrivește mai bine din lista: '$category_list'.\n";
    $prompt .= "4. Creează un rezumat al articolului (summary).\n";
    $prompt .= "5. Generează un articol cu respectarea strictă a dimensiunii $length_instruction, detaliat, foloseste un stil descriptiv in exprimare, nu include titlul in interiorul acestuia si nu omite nici un aspect din informatiile preluate.";
    $prompt .= " Structura articolului poate include (daca consideri necesar!) una, doua sau trei subtitluri semantice de tip H2, H3 și să fie formatată în HTML pentru o structură SEO-friendly astfel încât să aibă și un design plăcut (content).\n";
    $prompt .= "6. Copiază adresele URL complete ale articolelor pe care le-ai parsat și de unde ai extras informația (sources).\n";
    $prompt .= "7. Copiază identic titlurile articolelor pe care le-ai parsat și de unde ai extras informația (source_titles).\n";
    $prompt .= "8. Copiază adresele URL complete ale imaginilor reprezentative ale articolelor de unde ai extras informația (images).\n";

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
        $length_instruction = "similare cu articolul sursă in privinta numarului de cuvinte";
    }
    
    $selected_category_id = $options['specific_search_category'] ?? '';
    $selected_category_name = '';
    $current_date = date("Y/m/d") || "azi";

    if ($selected_category_id) {
        // Extragem numele categoriei selectate dacă există
        $category = get_category($selected_category_id);
        if ($category) {
            $selected_category_name = $category->name;
        }
    }
    
    $prompt = "Urmează sa generezi un articol optimizat SEO si pentru asta urmează pasii: \n";
    
    $prompt .= "Fă browsing pe urmatoarele surse de știri, descoperă ultimele știri publicate în data curentă (" . date("Y/m/d") . ") și nu căuta infomații publicate inainte de această dată";
    
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
    $prompt .= "\n Folosește doar informația pentru a compune un nou articol unic care să respecte informatia si să conțină toate detaliile acesteia.\n";
    $prompt .= $additional_instructions !== "" ? "\n Instrucțiuni suplimentare: " . $additional_instructions : "";
    $prompt .= "\n Include următoarele informații în răspunsul tău:\n";
    $prompt .= "1. Generează un titlu relevant pentru articol, intrigant si care să stărnească curiozitatea cititorului in a citi articolul generat (title).\n";
    $prompt .= "2. Generează 1-3 etichete relevante (tags) și asigură-te că acestea sunt folosite de cel puțin două ori în conținutul articolului pentru optimizare SEO  și asigură-te că fiecare cuvânt începe cu majusculă.\n";
    $prompt .= " Etichetele sugerate pot fi din lista existentă de etichete: '$existing_tag_list'. Dacă nu există potriviri relevante, sugerează noi etichete.\n";
    $prompt .= "3. Numește numele categoriei care se potrivește mai bine din lista: '$category_list'.\n";
    $prompt .= "4. Creează un rezumat al articolului (summary).\n";
    $prompt .= "5. Generează un articol cu respectarea strictă a dimensiunii $length_instruction, detaliat, foloseste un stil jurnalistic in exprimare, nu include titlul in interiorul acestuia si nu omite nici un aspect din informatia preluată.";
    $prompt .= " Structura articolului (poate să includă daca consideri necesar - una, doua sau trei subtitluri semantice de tip H2, H3) și să fie formatată în HTML pentru o structură SEO-friendly astfel încât să aibă și un design plăcut (content).\n";
    $prompt .= "6. Copiază adresele URL complete ale articolelor pe care le-ai parsat și de unde ai extras informația (sources).\n";
    $prompt .= "7. Copiază identic titlurile articolelor pe care le-ai parsat și de unde ai extras informația (source_titles).\n";
    $prompt .= "8. Copiază adresele URL complete ale imaginilor reprezentative ale articolelor de unde ai extras informația (images).\n";
   
    return $prompt;
}

// Funcție pentru apelarea API-ului OpenAI
function call_openai_api($api_key, $prompt)
{
      error_log('call_openai_api()!! ');
    return wp_remote_post(URL_API_OPENAI, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            // 'model' => 'gpt-4o-2024-08-06',  // Model ce suportă ieșiri structurate
             'model' => 'gpt-4o-mini-2024-07-18',  // Model ce suportă ieșiri structurate ??
            'messages' => [
                ['role' => 'system', 'content' => 'You are an assistant generating news articles and images based on provided sources.'],
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
                            "title" => [
                                "type" => "string",
                                "description" => "Titlul articolului generat"
                            ],
                            "content" => [
                                "type" => "string",
                                "description" => "Conținutul complet al articolului generat"
                            ],
                            "summary" => [
                                "type" => "string",
                                "description" => "Un rezumat al articolului generat"
                            ],
                            "category" => [
                                "type" => "string",
                                "description" => "Categoria articolului generat"
                            ],
                            "tags" => [
                                "type" => "array",
                                "description" => "Etichete relevante pentru articol",
                                "items" => [
                                    "type" => "string"
                                ]
                            ],
                            "images" => [
                                "type" => "array",
                                "description" => "URL-urile imaginilor relevante din articolele sursă",
                                "items" => [
                                    "type" => "string"
                                ]
                            ],
                            "sources" => [
                                "type" => "array",
                                "description" => "URL-urile complete ale știrilor citite",
                                "items" => [
                                    "type" => "string"
                                ]
                            ],
                            "source_titles" => [
                                "type" => "array",
                                "description" => "Titlurile exacte ale articolelor parsate si citite",
                                "items" => [
                                    "type" => "string"
                                ]
                            ]
                        ],
                        'required' => ['title', 'content', 'summary', 'category', 'tags', 'images', 'sources', 'source_titles'],
                        'additionalProperties' => false
                    ]
                ],
            ],
            'max_tokens' => 9000,
        ]),
        'timeout' => 180,
    ]);
}


// Funcție pentru filtrarea promptului
function sanitize_prompt($summary)
{
    // Lista cuvintelor potențial sensibile
    $blacklist = ['sex', 'COVID-19', 'Pandemiei'];  // Adăugați termeni specifici dacă este cazul

    // Înlocuim cuvintele din lista neagră
    foreach ($blacklist as $word) {
        $summary = str_ireplace($word, '', $summary);
    }

    // Eliminăm eventualele spații multiple rămase
    $summary = preg_replace('/\s+/', ' ', trim($summary));

    return $summary;
}



// Funcție pentru apelarea API-ului OpenAI folosind DALL-E 3 pentru generarea de imagini
function call_openai_image_api($api_key, $summary, $tags = [], $feedback = '')
{
    // Creăm un prompt pentru generarea imaginii
    $prompt = "Generează o imagine cât mai naturală și realistă, fără a utiliza texte sau cuvinte în interiorul imaginii, având ca temă aceste etichete: ";
    if (!empty($tags)) {
        $prompt .=  implode(", ", $tags) . ".";
    }
     $prompt .= "Foloseste todata acest rezumat ca si context pentru a desena imaginea:'" . $summary . "'.Evită să desenezi chipurile specifice a oamenilor cănd se face referire la anumite persoane in mod direct, caz in care trebuie sa desenezi personajele din spate . ";
     
     if (!empty($feedback)) {
        $prompt .= "\n Utilizează următorul feedback de la imaginea generată anterior pentru a îmbunătăți imaginea: " . $feedback;
    }

    // Apelăm API-ul OpenAI pentru generarea imaginii
    return wp_remote_post(URL_API_IMAGE, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'dall-e-3',  // Modelul DALL-E 3 pentru imagini
            'prompt'=> $prompt,
            'size'=> "1792x1024",
            'quality'=> "standard",
            'n'=> 1,
            'style'=> 'natural'
        ]),
        'timeout' => 90,
    ]);
}



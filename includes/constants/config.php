<?php
// Funcție pentru generarea promptului
const URL_API_OPENAI = 'https://api.openai.com/v1/chat/completions';

function generate_prompt($sources, $additional_instructions, $tags): string
{
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

    $prompt = "Fa browsing pe urmatoarele surse de știri și descoperă ultima știre care apare în cele trei surse simultan. Folosește doar informația pentru a compune un nou articol unic.\n";
    $prompt .= implode("\n", $sources);
    $prompt .= "\n\nInstrucțiuni suplimentare: " . $additional_instructions;
    $prompt .= "\nInclude următoarele informații în răspunsul tău:\n";
    $prompt .= "1. Generează un titlu relevant pentru articol (title).\n";
    $prompt .= "2. Generează 2-3 etichete relevante (tags).\n";
    $prompt .= "3. Numește numele categoriei care se potrivește mai bine din lista: '$category_list'.\n";
    $prompt .= "4. Dacă găsești imagini relevante în articolele sursă, parsează codul sursă HTML și extrage URL-urile imaginilor pentru a le include în articol (images).\n";
    $prompt .= "5. Creează un rezumat al articolului (summary).\n";
    $prompt .= "6. Generează un articol formatat în HTML astfel încât să aibă un design plăcut.\n";
    $prompt .= "7. Copiază URL-urile complete ale articolelor pe care le-ai parsat și de unde ai extras informația (sources).\n";

    // Adăugăm instrucțiuni pentru generarea imaginii
    $prompt .= "8. Generează o imagine care să fie reprezentativă pentru subiectul articolului. Asigură-te că imaginea reflectă elementele principale discutate în articol și oferă un URL pentru descărcarea acesteia.\n";

    return $prompt;
}

// Funcție pentru apelarea API-ului OpenAI
function call_openai_api($api_key, $prompt)
{
    return wp_remote_post(URL_API_OPENAI, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'gpt-4o-2024-08-06',  // Model ce suportă ieșiri structurate
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
                            "generated_image" => [
                                "type" => "string",
                                "description" => "URL-ul imaginii generate de AI pe baza subiectului articolului"
                            ],
                            "sources" => [
                                "type" => "array",
                                "description" => "URL-urile complete ale știrilor citite",
                                "items" => [
                                    "type" => "string"
                                ]
                            ]
                        ],
                        'required' => ['title', 'content', 'summary', 'category', 'tags', 'images', 'generated_image', 'sources'],
                        'additionalProperties' => false
                    ]
                ],
            ],
            'max_tokens' => 2500,
        ]),
        'timeout' => 90,
    ]);
}

<?php

class Auto_Ai_News_Poster_Prompts
{
    /**
     * Generează promptul pentru analizarea unui text sursă și crearea unui articol nou.
     *
     * @param string $article_text_content Conținutul textului sursă.
     * @param string $additional_instructions Instrucțiuni suplimentare.
     * @param string $source_link Linkul sursă (opțional).
     * @return string Promptul complet.
     */
    public static function get_custom_source_prompt($article_text_content, $additional_instructions = '', $source_link = '')
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
        $prompt .= "  \"content\": \"Conținutul complet al articolului, formatat în HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly. NU folosi titluri explicite precum Introducere/Dezvoltare/Concluzie. INCLUDE toate linkurile din textul sursă folosind tag-uri <a href=\\\"URL\\\">Text Link</a>.\",\n";
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

    /**
     * Generează promptul pentru modul de "AI Browsing" (căutare și sinteză știri).
     *
     * @param array $sources Sursele de știri.
     * @param string $additional_instructions Instrucțiuni suplimentare.
     * @param string $tags Etichete (nefolosit momentan direct in string, dar păstrat pentru compatibilitate).
     * @return string Promptul complet.
     */
    public static function get_browsing_prompt($sources, $additional_instructions, $tags)
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
}

<?php

class Auto_Ai_News_Poster_Prompts
{
    private static function get_tone_instruction($tone): string
    {
        if (function_exists('auto_ai_news_poster_get_tone_instruction')) {
            return auto_ai_news_poster_get_tone_instruction($tone);
        }

        return '';
    }

    /**
     * Generează promptul pentru analizarea unui text sursă și crearea unui articol nou.
     *
     * @param string $article_text_content Conținutul textului sursă.
     * @param string $additional_instructions Instrucțiuni suplimentare.
     * @param string $source_link Linkul sursă (opțional).
     * @return string Promptul complet.
     */
    public static function get_custom_source_prompt($article_text_content, $additional_instructions = '', $source_link = '', $tone = '')
    {
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);
        $tone_instruction = self::get_tone_instruction($tone);

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
        $prompt .= "3. Scrie un articol obiectiv, bine structurat, cu un titlu captivant, un conținut informativ și o listă de etichete (tags) relevante. **ATENȚIE: Etichetele NU trebuie să conțină underscores (_)! Folosește spații naturale între cuvinte.** **Păstrează toate faptele, detaliile, numele, numerele și listele (ex: liste de filme, produse, evenimente) EXACT așa cum apar în textul sursă. Nu omite și nu adăuga elemente noi în liste.** {$length_instruction}\n";
        $prompt .= "   **REGULĂ STRICTĂ LINKURI:** Limitează numărul de linkuri din conținut la maximum 3. Include DOAR linkuri care fac referință directă la sursele citate sau la informații esențiale din text. **Formatează obligatoriu linkurile în HTML** folosind tag-uri <a href=\"URL\">Text Link</a>. **EVITĂ COMPLET** linkurile comerciale, publicitare, linkurile de afiliere sau recomandările de produse care nu sunt parte integrantă din știrea editorială.\n";
        $prompt .= "4. Articolul trebuie să fie o reformulare fidelă a textului sursă, nu un sumar sau un comentariu personal. Menține perspectiva originală.\n";
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

        if (!empty($tone_instruction)) {
            $prompt .= "\n**TON CERUT:** {$tone_instruction}\n";
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
        $prompt .= "  \"content\": \"Conținutul complet al articolului, formatat în HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly. NU folosi titluri explicite precum Introducere/Dezvoltare/Concluzie. Include MAXIMUM 3 linkuri relevante către surse, DOAR în format HTML <a href=\\\"URL\\\">Text Link</a>, evitându-le pe cele comerciale.\",\n";
        $prompt .= "  \"summary\": \"O meta descriere de maximum 160 de caractere, optimizată SEO.\",\n";
        $prompt .= "  \"tags\": [\"tag1\", \"tag2\", \"tag3\"],\n";
        $prompt .= "  \"category\": \"Numele categoriei selectate din lista de categorii existente\",\n";
        $prompt .= "  \"sources\": [\"URL-ul complet al stirii citite\"],\n";
        $prompt .= "  \"source_titles\": [\"Titlul exact al articolului parsat si citit\"]\n";
        $prompt .= "}\n\n";
        $prompt .= "⚠️ IMPORTANT: Etichetele (tags) NU trebuie să conțină underscores (_)! Folosește spații naturale (ex: \"cod CAEN\", nu \"cod_CAEN\").\n";

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
    public static function get_browsing_prompt($sources, $additional_instructions, $tags, $tone = '')
    {
        $tone_instruction = self::get_tone_instruction($tone);
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
        $options = get_option(AUTO_AI_NEWS_POSTER_SETTINGS_OPTION);

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
        if (!empty($tone_instruction)) {
            $prompt .= "\n TON CERUT: " . $tone_instruction;
        }

        // Verificăm dacă trebuie să generăm etichete
        $generate_tags_option = $options['generate_tags'] ?? 'yes';

        $prompt .= "\n Include următoarele informații în răspunsul tău:\n";
        $prompt .= "1. Generează un titlu relevant pentru articol, intrigant si care să stărnească curiozitatea cititorului in a citi articolul generat (title).\n";
        $prompt .= "2. Generează 1-3 etichete relevante (tags) și asigură-te că acestea sunt folosite de cel puțin două ori în conținutul articolului pentru optimizare SEO  și asigură-te că fiecare cuvânt începe cu majusculă.\n";
        $prompt .= " Etichetele sugerate pot fi din lista existentă de etichete: '$existing_tag_list'. Dacă nu există potriviri relevante, sugerează noi etichete.\n";
        $prompt .= "3. Numește numele categoriei care se potrivește mai bine din lista: '$category_list'.\n";
        $prompt .= "4. Creează un rezumat al articolului (summary).\n";
        $prompt .= "5. Generează un articol cu respectarea strictă a dimensiunii $length_instruction, detaliat, folosește un stil jurnalistic în exprimare, nu include titlul în interiorul acestuia și nu omite nici un aspect din informația preluată. **REGULĂ LINKURI:** Include maximum 3 linkuri relevante către sursele citate, formatate obligatoriu în HTML (<a href=\\\"URL\\\">Text Link</a>), evitând orice link comercial sau publicitar.";
        $prompt .= ' ATENȚIE: Nu adăuga informații care nu sunt în sursele de știri! Dacă sursele menționează o listă specifică (ex: filme, persoane, evenimente), copiază EXACT aceeași listă, nu o modifica sau nu adăuga alte elemente.';
        $prompt .= " Structura articolului (poate să includă dacă consideri necesar - una, două sau trei subtitluri semantice de tip H2, H3) și să fie formatată în HTML pentru o structură SEO-friendly astfel încât să aibă și un design plăcut (content).\n";
        $prompt .= "6. Copiază adresele URL complete ale articolelor pe care le-ai parsat și de unde ai extras informația (sources).\n";
        $prompt .= "7. Copiază identic titlurile articolelor pe care le-ai parsat și de unde ai extras informația (source_titles).\n";

        return $prompt;
    }
    public static function get_ai_browsing_system_message()
    {
        return 'You are a precise news article generator. NEVER invent information. Use ONLY the exact information provided in sources. If sources mention specific lists (movies, people, events), copy them EXACTLY without modification. Always respect the required word count.';
    }

    public static function get_ai_browsing_prompt($news_sources, $category_name, $latest_titles_str, $final_instructions, $length_instruction, $tone = '')
    {
        $tone_instruction = self::get_tone_instruction($tone);
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
        " . (!empty($tone_instruction) ? "\n        3.1 **Ton:** {$tone_instruction}" : "") . "
        4. **Generare titlu:** Creează un titlu concis și atractiv pentru articol.
        5. **Generare etichete:** Generează între 1 și 3 etichete relevante (cuvinte_cheie) pentru articol. **ATENȚIE: NU folosi underscores (_) în etichete! Folosește spații naturale între cuvinte.** Fiecare cuvânt trebuie să înceapă cu majusculă.
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
          \"continut\": \"Conținutul complet al articolului, formatat în HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly. NU folosi titluri explicite precum Introducere/Dezvoltare/Concluzie. Include MAXIMUM 3 linkuri către sursele citate, formatate OBLIGATORIU în HTML (<a href=\\\"URL\\\">Text Link</a>), evitând linkurile comerciale.\",
          \"imagine_prompt\": \"Descrierea detaliată pentru imaginea reprezentativă.\",
          \"meta_descriere\": \"O meta descriere de maximum 160 de caractere, optimizată SEO.\",
          \"cuvinte_cheie\": [\"cuvânt1\", \"cuvânt2\", \"cuvânt3\"]
        }

        **PASUL 1:** Începe prin a folosi web browsing pentru a căuta pe site-urile specificate și găsi știri recente din categoria {$category_name}.
        ";
    }

    /**
     * Generează promptul pentru transformarea unui TITLU în articol EVERGREEN (Mod Taskuri).
     * Articolele generate trebuie să fie detaliate, bine documentate și rezistente în timp.
     */
    public static function get_task_article_prompt($title, $category_name, $additional_instructions = '', $article_length_settings = [], $tone = '')
    {
        $tone_instruction = self::get_tone_instruction($tone);
        // Pentru TASKURI: asigură limite stricte de cuvinte (defaults 1500-2000 dacă nu sunt setate)
        $article_length_option = $article_length_settings['article_length_option'] ?? 'same_as_source';
        $min_length = intval($article_length_settings['min_length'] ?? 0);
        $max_length = intval($article_length_settings['max_length'] ?? 0);

        // Pentru taskuri, 'same_as_source' nu are sens; folosim limite stricte cu defaults
        if ($article_length_option === 'same_as_source' || $min_length === 0 || $max_length === 0) {
            $min_length = 1500;
            $max_length = 2000;
        }

        // ÎNTOTDEAUNA specifică limita exactă de cuvinte pentru taskuri
        $length_instruction = "Articolul TREBUIE SĂ AIBĂ OBLIGATORIU între {$min_length} și {$max_length} de cuvinte. Respectă strict această limită!";

        $prompt = "Ești un jurnalist expert specializat în articole EVERGREEN (conținut rezistent în timp). Trebuie să scrii un articol COMPLET, DETALIAT, BINE DOCUMENTAT și CUPRINZĂTOR pe baza următorului titlu: \"{$title}\".\n\n";
        
        $prompt .= "🔴 **CERINȚĂ CRITICĂ DE LUNGIME:** {$length_instruction} NU scrie articole scurte/sumare/superficiale! 🔴\n\n";
        
        $prompt .= "**═══════════════════════════════════════════════════════**\n";
        $prompt .= "**CERINȚE OBLIGATORII DE DOCUMENTARE ȘI CERCETARE**\n";
        $prompt .= "**═══════════════════════════════════════════════════════**\n\n";
        
        $prompt .= "1. **CERCETARE WEB OBLIGATORIE (MANDATORY WEB BROWSING):**\n";
        $prompt .= "   - TREBUIE să folosești funcția de WEB BROWSING pentru a găsi informații actuale și verificate\n";
        $prompt .= "   - Cercetează și consultă MINIMUM 3 SURSE DE ÎNCREDERE (site-uri oficiale, documentații, articole de referință)\n";
        $prompt .= "   - Prioritizează: documentații oficiale, site-uri guvernamentale, publicații academice, platforme recunoscute în domeniu\n";
        $prompt .= "   - Verifică și compară informațiile din surse multiple pentru acuratețe\n";
        $prompt .= "   - NU inventa informații - folosește doar date veridice din surse verificate\n\n";
        
        $prompt .= "2. **ADĂUGARE LINKURI CĂTRE SURSE (MANDATORY SOURCE ATTRIBUTION):**\n";
        $prompt .= "   - OBLIGATORIU: Include în articol linkuri HTML către TOATE sursele consultate\n";
        $prompt .= "   - Format: <a href=\"URL_SURSA\">Nume_Sursa</a>\n";
        $prompt .= "   - Plasează linkurile natural în context, acolo unde citeaza sau referă informația\n";
        $prompt .= "   - Exemplu: \"Conform <a href=\"https://sursa.com\">documentației oficiale</a>, procesul...\"\n";
        $prompt .= "   - Minimum 3 linkuri către surse diferite în articol\n\n";
        
        $prompt .= "3. **EXPLICAȚII DETALIATE ȘI PAȘI DETALIAȚI:**\n";
        $prompt .= "   - Furnizează explicații COMPLETE și DETALIATE pentru fiecare concept\n";
        $prompt .= "   - Dacă articolul include un proces/procedură, descrie TOȚI PAȘII necesari\n";
        $prompt .= "   - Folosește liste numerotate sau bullet points pentru claritate\n";
        $prompt .= "   - Include exemple practice unde este relevant\n";
        $prompt .= "   - Explică \"de ce\" și \"cum\", nu doar \"ce\"\n\n";
        
        $prompt .= "**═══════════════════════════════════════════════════════**\n";
        $prompt .= "**REGULI PENTRU CONȚINUT EVERGREEN (TIMELESS CONTENT)**\n";
        $prompt .= "**═══════════════════════════════════════════════════════**\n\n";
        
        $prompt .= "4. **INTERZIS - REFERINȚE TEMPORALE:**\n";
        $prompt .= "   - NU menționa NICIODATĂ anul, luna sau perioada curentă (ex: \"în 2023\", \"în 2024\", \"în 2026\", \"anul acesta\", \"luna aceasta\")\n";
        $prompt .= "   - NU folosi expresii precum: \"recent\", \"în ultimul timp\", \"în prezent\", \"momentan\", \"în acest an\"\n";
        $prompt .= "   - Scrie conținut care rămâne valabil și relevant independent de momentul citirii\n";
        $prompt .= "   - Folosește formulări neutre temporal: \"de obicei\", \"în general\", \"conform metodologiei standard\"\n\n";
        
        $prompt .= "5. **STIL DE SCRIERE - EVERGREEN:**\n";
        $prompt .= "   - " . (!empty($tone_instruction) ? $tone_instruction : 'Ton profesional, educațional și informativ') . "\n";
        $prompt .= "   - Focalizează pe informații fundamentale și proceduri standard\n";
        $prompt .= "   - Evită tendințele temporare - concentrează pe principii și practici stabile\n";
        $prompt .= "   - Articolul trebuie să fie util și relevant și peste 1-2 ani\n\n";
        
        $prompt .= "**═══════════════════════════════════════════════════════**\n";
        $prompt .= "**CERINȚE TEHNICE ȘI DE FORMATARE**\n";
        $prompt .= "**═══════════════════════════════════════════════════════**\n\n";
        
        $prompt .= "6. **LIMBA ȘI TON:**\n";
        $prompt .= "   - Scrie EXCLUSIV în limba ROMÂNĂ\n";
        $prompt .= "   - " . (!empty($tone_instruction) ? $tone_instruction : 'Ton jurnalistic profesionist, obiectiv și educațional') . "\n";
        $prompt .= "   - Vocabular accesibil dar precis tehnic\n\n";
        
        $prompt .= "7. **STRUCTURĂ HTML:**\n";
        $prompt .= "   - Folosește formatare HTML corectă: <p>, <h2>, <h3>, <ul>, <ol>, <li>\n";
        $prompt .= "   - NU folosi titluri generice precum \"Introducere\", \"Dezvoltare\", \"Concluzie\"\n";
        $prompt .= "   - Subtitluri descriptive și relevante (H2, H3)\n";
        $prompt .= "   - Paragrafe bine structurate și logice\n\n";
        
        $prompt .= "8. **LUNGIME ȘI DETALIERE (OBLIGATORIU - CRUCIAL):**\n";
        $prompt .= "   - ⚠️ LIMITA DE CUVINTE: {$length_instruction}\n";
        $prompt .= "   - ⚠️ ACEST ARTICOL TREBUIE SĂ FIE DETALIAT, NU SUMAR! Scrie un articol COMPLET și CUPRINZĂTOR!\n";
        $prompt .= "   - NU scrie articole scurte/sumare/superficiale - articolul trebuie să acopere tema ÎN PROFUNZIME\n";
        $prompt .= "   - Prioritizează CALITATEA, PROFUNZIMEA și DETALIUL informației\n";
        $prompt .= "   - Fiecare secțiune trebuie să fie COMPLETĂ, UTILĂ și bine dezvoltată\n";
        $prompt .= "   - Include exemple practice, cazuri concrete, explicații pas-cu-pas unde e relevant\n\n";
        
        $prompt .= "9. **SEO ȘI METADATA:**\n";
        $prompt .= "   - Categorie de destinație: \"{$category_name}\"\n";
        $prompt .= "   - Generează 1-3 etichete (tags) relevante și evergreen\n";
        $prompt .= "   - ⚠️ ETICHETELE NU TREBUIE SĂ CONȚINĂ UNDERSCORES (_)! Folosește spații naturale între cuvinte.\n";
        $prompt .= "   - Exemplu CORECT: \"cod CAEN\", \"datorii fiscale\", \"obligații fiscale\"\n";
        $prompt .= "   - Exemplu GREȘIT: \"cod_CAEN\", \"datorii_fiscale\", \"obligații_fiscale\"\n";
        $prompt .= "   - Meta descriere de maximum 160 caractere, atrăgătoare și optimizată SEO\n\n";
        
        if (!empty($additional_instructions)) {
            $prompt .= "10. **INSTRUCȚIUNI SUPLIMENTARE SPECIFICE:**\n";
            $prompt .= "    {$additional_instructions}\n\n";
        }
        
        $prompt .= "**═══════════════════════════════════════════════════════**\n";
        $prompt .= "**FORMAT DE RĂSPUNS - JSON OBLIGATORIU**\n";
        $prompt .= "**═══════════════════════════════════════════════════════**\n\n";
        
        $prompt .= "Returnează EXCLUSIV un obiect JSON cu următoarea structură:\n\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"Titlul final optimizat (fără referințe temporale)\",\n";
        $prompt .= "  \"content\": \"Conținutul COMPLET și DETALIAT în HTML ({$min_length}-{$max_length} cuvinte!), cu minimum 3 linkuri <a href=\\\"...\\\"> către surse, pași detaliați, explicații complete și exemple practice\",\n";
        $prompt .= "  \"summary\": \"Meta descriere SEO (max 160 caractere, evergreen)\",\n";
        $prompt .= "  \"tags\": [\"etichetă relevantă\", \"alt tag util\", \"tag evergreen\"],\n";
        $prompt .= "  \"category\": \"{$category_name}\",\n";
        $prompt .= "  \"image_url\": \"(OPȚIONAL) URL-ul unei imagini reprezentative găsite în timpul browsing-ului, dacă este relevantă și de calitate\",\n";
        $prompt .= "  \"sources\": [\"URL_SURSA_1\", \"URL_SURSA_2\"]\n";
        $prompt .= "}\n\n";
        
        $prompt .= "**⚠️ VERIFICARE FINALĂ OBLIGATORIE - ÎNAINTE DE A RĂSPUNDE:**\n";
        $prompt .= "✓ Ai folosit web browsing pentru cercetare (MANDATORY)\n";
        $prompt .= "✓ Ai consultat minimum 3 surse credibile\n";
        $prompt .= "✓ Ai inclus minimum 3 linkuri către surse în content\n";
        $prompt .= "✓ Ai furnizat explicații DETALIATE și pași COMPLETI (nu superficial!)\n";
        $prompt .= "✓ Articolul are OBLIGATORIU între {$min_length} și {$max_length} de cuvinte\n";
        $prompt .= "✓ Etichetele (tags) NU conțin underscores (_), ci doar spații naturale\n";
        $prompt .= "✓ NU ai menționat niciun an, lună sau perioadă specifică\n";
        $prompt .= "✓ Conținutul este evergreen, COMPLET, DETALIAT și va rămâne relevant în timp\n";

        return $prompt;
    }

    public static function get_retry_browsing_prompt($category_name)
    {
        return "Scrie un articol de știri ca un jurnalist profesionist. \r\n\r\nCategoria: {$category_name}\r\n\r\nCerințe:\r\n- Titlu atractiv și descriptiv\r\n- Conținut fluent și natural, fără secțiuni marcate explicit\r\n- NU folosi titluri precum \"Introducere\", \"Dezvoltare\", \"Concluzie\"\r\n- Formatare HTML cu tag-uri <p>, <h2>, <h3> pentru structură SEO-friendly\r\n- Generează între 1 și 3 etichete relevante (cuvinte_cheie) - ⚠️ FĂRĂ underscores (_)! Folosește spații naturale.\r\n- Limbă română\r\n- Stil jurnalistic obiectiv și informativ\r\n\r\nReturnează DOAR acest JSON:\r\n{\r\n  \"titlu\": \"Titlul articolului\",\r\n  \"continut\": \"Conținutul complet al articolului formatat în HTML, fără titluri explicite precum Introducere/Dezvoltare/Concluzie\",\r\n  \"meta_descriere\": \"Meta descriere SEO\",\r\n  \"cuvinte_cheie\": [\"tag1\", \"tag2\", \"tag3\"]\r\n}";
    }

    public static function get_dalle_abstraction_system_message()
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

    public static function get_dalle_abstraction_user_message($original_prompt)
    {
        return "Transformă următoarea descriere într-un prompt vizual pentru generarea unei imagini FOTOREALISTE și NATURALE, ca o fotografie profesională de știri realizată cu ocazia evenimentului descris. Promptul trebuie să genereze o imagine care să pară o fotografie reală, nu o ilustrație sau artă abstractă: \"{$original_prompt}\"";
    }

    public static function get_photorealism_instructions()
    {
        $prefix = 'Fotografie profesională de știri, fotorealistă și naturală, realizată cu ocazia evenimentului. ';
        $suffix = ' Stil: fotografie jurnalistică profesională, iluminare naturală, compoziție profesională, culori naturale, texturi realiste, sharp focus, high resolution. Imaginea trebuie să pară o fotografie reală, nu o ilustrație sau artă abstractă. Dacă apar oameni, ei trebuie să aibă trăsături românești/est-europene.';
        
        return ['prefix' => $prefix, 'suffix' => $suffix];
    }

    /**
     * Returnează mesajul de sistem universal pentru toate modelele AI (OpenAI, DeepSeek, Gemini).
     * Asigură consistența instrucțiunilor (Persona, Reguli, Formatare).
     *
     * @param bool $is_deepseek Dacă true, adaugă instrucțiuni specifice pentru JSON strict.
     * @return string Mesajul de sistem.
     */
    public static function get_universal_system_message($is_deepseek = false)
    {
        $system_content = 'You are a precise news article generator. NEVER invent information. Use ONLY the exact information provided in sources. If sources mention specific lists (movies, people, events), copy them EXACTLY without modification. Always respect the required word count.';

        // Dacă e DeepSeek, adăugăm instrucțiuni explicite despre JSON în prompt
        if ($is_deepseek) {
            $system_content .= " ERROR HANDLING: You MUST respond with valid JSON only. The JSON must follow this structure: {\"title\": \"...\", \"content\": \"...\", \"summary\": \"...\", \"category\": \"...\", \"tags\": [\"...\"], \"sources\": [\"...\"], \"source_titles\": [\"...\"]}";
        }

        return $system_content;
    }

    /**
     * Generează promptul pentru rescrierea unui articol folosind DOAR ideile existente.
     * 
     * @param string $current_title Titlul articolului curent.
     * @param string $current_content Conținutul articolului curent.
     * @param string $word_count_instruction Instrucțiuni despre numărul de cuvinte.
     * @return string Promptul complet.
     */
    public static function get_rewrite_same_ideas_prompt($current_title, $current_content, $word_count_instruction = '', $tone = '')
    {
        $tone_instruction = self::get_tone_instruction($tone);
        $tone_line = !empty($tone_instruction) ? $tone_instruction : 'Menține tonul folosit in articolul original';
        $prompt = "Ești un editor de conținut profesionist. Rescrieți articolul următor folosind STRICT DOAR ideile și informațiile deja existente în el. Nu adăuga informații noi sau cercetare externă.

Titlu actual: {$current_title}

Conținut actual:
{$current_content}

Instrucțiuni:
1. Rescrie articolul complet, păstrând toate ideile și informațiile existente
2. Folosește un stil de scriere fresh și natural, evitând formulări identice cu originalul
3. Păstrează acuratețea și contextul oricăror citate sau referințe din original
4. {$word_count_instruction}
5. {$tone_line}
6. Nu inventa sau adăuga informații care nu sunt în articolul original

Returnează răspunsul DOAR în format JSON cu următoarea structură (fără markdown, fără ```json):
{
  \"title\": \"Titlul rescris (poate fi similar sau îmbunătățit)\",
  \"content\": \"Conținutul complet rescris în HTML (cu paragrafe <p>, liste, etc.)\",
  \"summary\": \"Un rezumat scurt de 1-2 propoziții\",
  \"tags\": [\"tag1\", \"tag2\", \"tag3\"]
}";

        return $prompt;
    }

    /**
     * Generează promptul pentru îmbogățirea unui articol cu informații noi via browsing.
     * 
     * @param string $current_title Titlul articolului curent.
     * @param string $current_content Conținutul articolului curent.
     * @param string $word_count_instruction Instrucțiuni despre numărul de cuvinte.
     * @return string Promptul complet.
     */
    public static function get_rewrite_enrich_prompt($current_title, $current_content, $word_count_instruction = '', $tone = '')
    {
        $tone_instruction = self::get_tone_instruction($tone);
        $tone_line = !empty($tone_instruction) ? $tone_instruction : 'Menține tonul profesional inspirat din cel al articolului original';
        $prompt = "Ești un jurnalist profesionist. Îmbogățește articolul următor cu informații noi și actualizate găsite prin cercetare pe internet.

Titlu actual: {$current_title}

Conținut actual:
{$current_content}

Instrucțiuni:
1. Folosește AI browsing pentru a găsi informații noi și relevante legate de subiectul articolului
2. Integrează aceste informații noi în mod natural în articol
3. Păstrează ideile și informațiile de bază din articolul original
4. Adaugă context, statistici, citate sau detalii actualizate unde este relevant
5. {$word_count_instruction}
6. Asigură-te că toate informațiile noi sunt corecte și verificabile
7. {$tone_line}
8. Citează sursele pentru informațiile noi adăugate (ca link-uri în text)

Returnează răspunsul DOAR în format JSON cu următoarea structură (fără markdown, fără ```json):
{
  \"title\": \"Titlul îmbogățit\",
  \"content\": \"Conținutul complet îmbogățit în HTML (cu paragrafe <p>, liste, link-uri către surse, etc.)\",
  \"summary\": \"Un rezumat scurt de 1-2 propoziții\",
  \"tags\": [\"tag1\", \"tag2\", \"tag3\"]
}";

        return $prompt;
    }
}

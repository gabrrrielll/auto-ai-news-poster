jQuery(document).ready(function($) {
    console.log('S-a incarcat pagina!');
    $('#get-article-button').on('click', function() {
        const additionalInstructions = $('#additional-instructions').val();
        const customSourceUrl = $('#custom-source-url').val();
        const postID = $('#post_ID').val(); // ID-ul postării curente
        const button = $(this);

    // Dezactivăm butonul și adăugăm un loader
    button.prop('disabled', true);
    button.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generare...');


        console.log('Trimit cererea AJAX...', button);
        console.log('Instrucțiuni suplimentare:', additionalInstructions);
        console.log('customSourceUrl:', customSourceUrl);
        console.log('Post ID:', postID);

        $.ajax({
            url: autoAiNewsPosterAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'get_article_from_sources',
                post_id: postID,
                instructions: additionalInstructions,
                custom_source_url: customSourceUrl,
                additional_instructions: additionalInstructions, // Salvăm și în baza de date
                security: autoAiNewsPosterAjax.nonce
            },
            success: function(response) {
                console.log('Răspuns primit:', response, window.location);
                if (response.success) {
                    // alert('Articolul a fost generat și salvat.');
                    // Facem refresh automat al paginii pentru a vedea modificările
                    // Redirecționăm către editorul articolului după ce articolul a fost creat/actualizat
                    window.location.href = '/diswp/wp-admin/post.php?post=' + response.data.post_id + '&action=edit';
                } else {
                    // alert('A apărut o eroare: ' + response.data.message);
                    console.error('Eroare:', response); // Afișăm eroarea completă în consolă
                }
            },
            error: function(xhr, status, error) {
                console.error('Eroare AJAX:', error);
                console.error('Răspuns complet AJAX:', xhr.responseText); // Afișăm răspunsul complet din server
                alert('A apărut o eroare la procesarea cererii.');
            },
            complete: function() {
            // Reactivăm butonul și eliminăm loader-ul
            button.prop('disabled', false);
            button.html('Generează articol');
        }
        });
    });

   $('#generate-image-button').on('click', function() {
    const postID = $('#post_ID').val(); // ID-ul postării curente
    const button = $(this);
    const feedbackText = $('#feedback-text').val(); // Feedback-ul din câmpul text

    // Dezactivăm butonul și adăugăm un loader
    button.prop('disabled', true);
    button.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generare...');

    console.log('Trimit cererea AJAX generate_image_for_article ...');
    console.log('Post ID:', postID);
    console.log('Feedback:', feedbackText);

    $.ajax({
        url: autoAiNewsPosterAjax.ajax_url, // Folosim obiectul localizat
        type: 'POST',
        data: {
            action: 'generate_image_for_article',
            post_id: postID,
            feedback: feedbackText, // Includem feedback-ul în data trimisă
            security: autoAiNewsPosterAjax.generate_image_nonce // Folosim nonce-ul localizat
        },
        success: function (response) {
            console.log('Răspuns primit:', response);
            if (response.success) {
                location.reload();
            } else {
                console.log('Răspuns Eroare:', response);
                alert('Eroare: ' + response.data.message.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('Eroare AJAX la generarea imaginii:', error);
            console.error('Răspuns complet AJAX:', xhr.responseText);
            alert('A apărut o eroare AJAX la generarea imaginii.');
        },
        complete: function() {
            button.prop('disabled', false);
            button.html('<span class="button-icon">🎨</span>Generează imagine AI'); // Restabilim iconița
        }
    });
});

    // Elimin codul pentru trimiterea feedback-ului la imagine, deoarece funcția PHP nu mai există

    // Adaugăm iconița pentru "Imagine generata AI"
    const sursaFotoElement = $('#sursa-foto');
    console.log("sursaFotoElement--->", sursaFotoElement);
    if (sursaFotoElement.length && sursaFotoElement.text().includes('Imagine generata AI')) {
        const infoIcon = $('<span>')
            .text('i')
            .css({
                display: 'inline-block',
                width: '20px',
                height: '20px',
                lineHeight: '20px',
                textAlign: 'center',
                backgroundColor: '#0073aa',
                color: 'white',
                borderRadius: '50%',
                fontSize: '14px',
                fontWeight: 'bold',
                marginLeft: '10px',
                cursor: 'pointer'
            })
            .attr('title', 'Aceasta imagine a fost generata de AI pe baza textului din rezumat si nu reprezinta realitatea surprinsa prin intermediul unei camere foto.')
            .on('mouseenter', function() {
                const tooltip = $('<div>')
                    .text('Aceasta imagine a fost generata de AI pe baza textului din rezumat si nu reprezinta realitatea surprinsa prin intermediul unei camere foto.')
                    .css({
                        position: 'absolute',
                        backgroundColor: '#333',
                        color: 'white',
                        padding: '10px',
                        borderRadius: '5px',
                        fontSize: '12px',
                        maxWidth: '300px',
                        top: $(this).offset().top + 30,
                        left: $(this).offset().left,
                        zIndex: 9999
                    })
                    .addClass('info-tooltip');
                $('body').append(tooltip);
                $(this).on('mouseleave', function() {
                    $('.info-tooltip').remove();
                });
            });

        sursaFotoElement.append(infoIcon);
    }
});

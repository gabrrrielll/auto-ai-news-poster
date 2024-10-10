jQuery(document).ready(function($) {
    $('#get-article-button').on('click', function() {
        const additionalInstructions = $('#additional-instructions').val();
        const postID = $('#post_ID').val(); // ID-ul postării curente

        console.log('Trimit cererea AJAX...');
        console.log('Instrucțiuni suplimentare:', additionalInstructions);
        console.log('Post ID:', postID);

        $.ajax({
            url: autoAiNewsPosterAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'get_article_from_sources',
                post_id: postID,
                instructions: additionalInstructions,
                security: autoAiNewsPosterAjax.nonce
            },
            success: function(response) {
                console.log('Răspuns primit:', response);
                if (response.success) {
                    // alert('Articolul a fost generat și salvat.');
                    // Facem refresh automat al paginii pentru a vedea modificările
                    // Redirecționăm către editorul articolului după ce articolul a fost creat/actualizat
                    // window.location.href = '/wp-admin/post.php?post=' + response.data.post_id + '&action=edit';
                } else {
                    // alert('A apărut o eroare: ' + response.data.message);
                    console.error('Eroare:', response); // Afișăm eroarea completă în consolă
                }
            },
            error: function(xhr, status, error) {
                console.error('Eroare AJAX:', error);
                console.error('Răspuns complet AJAX:', xhr.responseText); // Afișăm răspunsul complet din server
                alert('A apărut o eroare la procesarea cererii.');
            }
        });
    });
});
jQuery(document).ready(function ($) {
    $('#get-article-button').on('click', function () {
        var additionalInstructions = $('#additional-instructions').val();
        var postID = $('#post_ID').val(); // ID-ul postării curente

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
            success: function (response) {
                console.log('Răspuns primit:', response);
                if (response.success) {
                    // Introduce rezultatul în editorul de articole
                    $('#content').val(response.data.article_content);
                    alert('Articolul a fost generat și inserat în editor.');
                } else {
                    alert('A apărut o eroare: ' + response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('Eroare AJAX:', error);
                alert('A apărut o eroare la procesarea cererii.');
            }
        });
    });
});

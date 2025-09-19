jQuery(document).ready(function($) {
    console.log("🚀 AANP Metabox JS: Document ready.");

    // Handler pentru butonul de generare articol (on post edit page)
    $('#get-article-button').on("click", function() {
        console.log("➡️ GENERATE ARTICLE BUTTON CLICKED!");
        
        const additionalInstructions = $('#additional-instructions').val();
        const customSourceUrl = $('#custom-source-url').val();
        const postID = $('#post_ID').val();
        const button = $(this);

        // Verificări de validare
        if (!postID) {
            alert("Eroare: ID-ul postării lipsește!");
            return;
        }

        // Dezactivăm butonul și adăugăm un loader
        button.prop("disabled", true).html("⏳ Generare...");
        console.log("Button disabled, starting AJAX call...");

        const ajaxData = {
            action: "get_article_from_sources",
            post_id: postID,
            instructions: additionalInstructions,
            custom_source_url: customSourceUrl,
            security: autoAiNewsPosterAjax.get_article_nonce
        };
        
        console.log("AJAX DATA TO SEND:", ajaxData);

        $.ajax({
            url: autoAiNewsPosterAjax.ajax_url,
            method: "POST",
            data: ajaxData,
            success: function(response) {
                console.log("✅ AJAX SUCCESS:", response);
                
                if (response.success) {
                    if (response.data && response.data.post_id) {
                        const redirectUrl = autoAiNewsPosterAjax.admin_url + "post.php?post=" + response.data.post_id + "&action=edit";
                        window.location.href = redirectUrl;
                    } else {
                        alert("Eroare: ID-ul postării nu a fost returnat.");
                    }
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : "Eroare necunoscută la generarea articolului.";
                    alert("A apărut o eroare: " + errorMsg);
                }
            },
            error: function(xhr) {
                console.error("❌ AJAX ERROR:", xhr.responseText);
                alert("A apărut o eroare la procesarea cererii. Verificați consola pentru detalii.");
            },
            complete: function() {
                // Reactivăm butonul și eliminăm loader-ul
                button.prop("disabled", false).html("<span>📄</span> Generează articol");
            }
        });
    });
    
    // Handler pentru butonul de generare imagine AI
    $('#generate-image-button').on("click", function() {
        console.log("➡️ GENERATE IMAGE BUTTON CLICKED!");
        
        const postID = $('#post_ID').val();
        const button = $(this);
        const feedbackText = $('#feedback-text').val();

        if (!postID) {
            alert("Eroare: ID-ul postării lipsește pentru generarea imaginii!");
            return;
        }
        
        button.prop("disabled", true).html("⏳ Generare imagine...");
        console.log("Image generation button disabled, starting AJAX call...");

        const ajaxData = {
            action: "generate_image_for_article",
            post_id: postID,
            feedback: feedbackText,
            security: autoAiNewsPosterAjax.generate_image_nonce
        };

        $.ajax({
            url: autoAiNewsPosterAjax.ajax_url,
            method: "POST",
            data: ajaxData,
            success: function(response) {
                console.log("✅ AJAX IMAGE SUCCESS:", response);
                
                if (response.success) {
                    location.reload();
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : "Eroare necunoscută la generarea imaginii.";
                    alert("A apărut o eroare: " + errorMsg);
                }
            },
            error: function(xhr) {
                console.error("❌ AJAX IMAGE ERROR:", xhr.responseText);
                alert("A apărut o eroare la generarea imaginii. Verificați consola pentru detalii.");
            },
            complete: function() {
                button.prop("disabled", false).html("<span>🖼️</span> Generează imagine AI");
            }
        });
    });
});

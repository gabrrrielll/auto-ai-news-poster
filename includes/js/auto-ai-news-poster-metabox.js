jQuery(document).ready(function ($) {
    console.log("🚀 AANP Metabox JS: Document ready.");

    // Handler pentru butonul de generare articol (on post edit page)
    $('#get-article-button').on("click", function () {
        console.log("➡️ GENERATE ARTICLE BUTTON CLICKED!");

        const additionalInstructions = $('#additional-instructions').val();
        const customSourceUrl = $('#custom-source-url').val();
        const postID = $('#post_ID').val();
        const tone = $('#ai-tone').val();
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
            generation_mode_metabox: autoAiNewsPosterAjax.get_generation_mode(),
            tone: tone,
            security: autoAiNewsPosterAjax.get_article_nonce
        };

        console.log("AJAX DATA TO SEND:", ajaxData);

        $.ajax({
            url: autoAiNewsPosterAjax.ajax_url,
            method: "POST",
            data: ajaxData,
            success: function (response) {
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
            error: function (xhr) {
                console.error("❌ AJAX ERROR:", xhr.responseText);
                alert("A apărut o eroare la procesarea cererii. Verificați consola pentru detalii.");
            },
            complete: function () {
                // Reactivăm butonul și eliminăm loader-ul
                button.prop("disabled", false).html("<span>📄</span> Generează articol");
            }
        });
    });

    // Handler pentru butonul de generare imagine AI
    $('#generate-image-button').on("click", function () {
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
            success: function (response) {
                console.log("✅ AJAX IMAGE SUCCESS:", response);

                if (response.success) {
                    location.reload();
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : "Eroare necunoscută la generarea imaginii.";
                    alert("A apărut o eroare: " + errorMsg);
                }
            },
            error: function (xhr) {
                console.error("❌ AJAX IMAGE ERROR:", xhr.responseText);
                alert("A apărut o eroare la generarea imaginii. Verificați consola pentru detalii.");
            },
            complete: function () {
                button.prop("disabled", false).html("<span>🖼️</span> Generează imagine AI");
            }
        });
    });

    // ========================================
    // REWRITE METABOX HANDLERS
    // ========================================

    // Handle switch option clicks for rewrite metabox
    $('.rewrite-switch-option').on('click', function () {
        const $this = $(this);
        const $container = $this.closest('.rewrite-switch-container');

        // Remove active class from siblings
        $container.find('.rewrite-switch-option').removeClass('active');

        // Add active class to clicked option
        $this.addClass('active');

        // Check the radio button
        $this.find('input[type="radio"]').prop('checked', true);

        // Handle size mode toggle
        if ($this.closest('.rewrite-option-group').find('[name="size_mode"]').length > 0) {
            const sizeMode = $this.find('input[type="radio"]').val();
            const $wordLimits = $('#rewrite-word-limits');
            const $inputs = $wordLimits.find('input[type="number"]');

            if (sizeMode === 'custom') {
                $wordLimits.removeClass('disabled');
                $inputs.prop('disabled', false);
            } else {
                $wordLimits.addClass('disabled');
                $inputs.prop('disabled', true);
            }
        }
    });

    // Handle rewrite button click
    $('#rewrite-article-btn').on('click', function () {
        const $btn = $(this);
        const $status = $('#rewrite-status');

        // Get form values
        const rewriteMode = $('input[name="rewrite_mode"]:checked').val();
        const sizeMode = $('input[name="size_mode"]:checked').val();
        const minWords = $('#rewrite_min_words').val();
        const maxWords = $('#rewrite_max_words').val();
        const postId = $('#post_ID').val();
        const rewriteTone = $('#rewrite-tone').val();

        if (!postId) {
            $status.removeClass('success processing').addClass('error')
                .text('Eroare: ID-ul postării lipsește!')
                .show();
            return;
        }

        // Validate custom size mode
        if (sizeMode === 'custom') {
            const min = parseInt(minWords);
            const max = parseInt(maxWords);

            if (min >= max) {
                $status.removeClass('success processing').addClass('error')
                    .text('Eroare: Minimul trebuie să fie mai mic decât maximul!')
                    .show();
                return;
            }
        }

        // Disable button and show processing status
        $btn.prop('disabled', true).text('⏳ Se rescrie...');
        $status.removeClass('success error').addClass('processing')
            .text('Se procesează articolul... Vă rugăm așteptați.')
            .show();

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rewrite_existing_article',
                nonce: $('#auto_ai_rewrite_nonce').val(),
                post_id: postId,
                rewrite_mode: rewriteMode,
                size_mode: sizeMode,
                min_words: minWords,
                max_words: maxWords,
                tone: rewriteTone
            },
            success: function (response) {
                if (response.success) {
                    $status.removeClass('processing error').addClass('success')
                        .text('✅ ' + response.data.message)
                        .show();

                    // Reload page after 2 seconds to show new content
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    $status.removeClass('processing success').addClass('error')
                        .text('❌ ' + (response.data.message || 'A apărut o eroare!'))
                        .show();
                    $btn.prop('disabled', false).text('🔄 Rescrie acum');
                }
            },
            error: function (xhr, status, error) {
                $status.removeClass('processing success').addClass('error')
                    .text('❌ Eroare de conexiune: ' + error)
                    .show();
                $btn.prop('disabled', false).text('🔄 Rescrie acum');
            }
        });
    });
});

// Funcii pentru pagina de setri
function toggleApiInstructions() {
    const content = document.getElementById("api-instructions-content");
    const icon = document.querySelector(".toggle-icon");

    if (content && icon) {
        if (content.style.display === "none") {
            content.style.display = "block";
            icon.textContent = "^";
        } else {
            content.style.display = "none";
            icon.textContent = "�";
        }
    }
}

function refreshModelsList() {
    const apiKey = document.getElementById("chatgpt_api_key");

    if (!apiKey || !apiKey.value) {
        alert("V rugm s introducei mai �nt�i cheia API OpenAI.");
        return;
    }

    const refreshBtn = document.querySelector("button[onclick='refreshModelsList()']");
    if (!refreshBtn) return;

    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = "? Se �ncarc...";
    refreshBtn.disabled = true;

    fetch(autoAiNewsPosterAjax.ajax_url, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            action: "refresh_openai_models",
            api_key: apiKey.value,
            nonce: autoAiNewsPosterAjax.refresh_models_nonce
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert("Eroare la actualizarea listei de modele: " + (data.data || "Eroare necunoscut"));
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Eroare la actualizarea listei de modele.");
        })
        .finally(() => {
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
        });
}

// JavaScript pentru metabox-ul de editare articol
jQuery(document).ready(function($) {
    console.log("🚀 AANP Admin JS: Document ready. Starting setup.");

    // Logic for the settings page mode switcher
    const generationModeRadios = $('input[name="auto_ai_news_poster_settings[generation_mode]"]');
    console.log(`🔍 AANP Admin JS: Found ${generationModeRadios.length} mode switch radio buttons.`);

    function setupConditionalFields() {
        console.log("🔄 AANP Admin JS: Running setupConditionalFields...");
        // Add a class to the parent row (tr) of each settings group
        $('.settings-group').each(function(index) {
            const row = $(this).closest('tr');
            let addedClass = '';
            if ($(this).hasClass('settings-group-parse_link')) {
                row.addClass('settings-row-parse_link');
                addedClass = 'settings-row-parse_link';
            }
            if ($(this).hasClass('settings-group-ai_browsing')) {
                row.addClass('settings-row-ai_browsing');
                addedClass = 'settings-row-ai_browsing';
            }
            console.log(`   - Tagging row ${index} with class: ${addedClass}`);
        });
        console.log("✅ AANP Admin JS: Finished tagging parent rows.");
    }
    
    function toggleSettingsVisibility() {
        console.log("👁️ AANP Admin JS: toggleSettingsVisibility triggered.");
        if (!generationModeRadios.length) {
            console.log("   - No radio buttons found. Exiting.");
            return;
        }

        const selectedMode = $('input[name="auto_ai_news_poster_settings[generation_mode]"]:checked').val();
        console.log(`   - Selected mode is: "${selectedMode}"`);
        
        // Hide all conditional setting rows
        $('tr[class*="settings-row-"]').hide();
        console.log("   - All conditional rows have been hidden.");

        // Show rows for the selected mode
        const classToShow = '.settings-row-' + selectedMode;
        $(classToShow).show();
        console.log(`   - Attempting to show rows with class: "${classToShow}"`);
        console.log(`   - Found ${$(classToShow).length} rows to show.`);
    }
    
    // Initial setup
    setupConditionalFields();

    // Check if the selector exists before adding listeners
    if (generationModeRadios.length) {
        console.log("🔗 AANP Admin JS: Attaching 'change' event listener to radio buttons.");
        // Run on page load
        toggleSettingsVisibility();
        
        // Run on change
        generationModeRadios.on('change', toggleSettingsVisibility);
    } else {
        console.log("⚠️ AANP Admin JS: No radio buttons found to attach event listener.");
    }

    console.log("🤖 AUTO AI NEWS POSTER - JavaScript loaded");

    // Handler pentru butonul de generare articol
    $('#get-article-button').on("click", function () {
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
});

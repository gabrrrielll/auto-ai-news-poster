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
            icon.textContent = "¡";
        }
    }
}

function refreshModelsList() {
    const apiKey = document.getElementById("chatgpt_api_key");
    
    if (!apiKey || !apiKey.value) {
        alert("V rugm s introducei mai întâi cheia API OpenAI.");
        return;
    }
    
    const refreshBtn = document.querySelector("button[onclick='refreshModelsList()']");
    if (!refreshBtn) return;
    
    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = "? Se încarc...";
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
    console.log("?? AUTO AI NEWS POSTER - JavaScript loaded");
    console.log("?? Current page URL:", window.location.href);
    console.log("?? Looking for generate button...");
    
    // Verificm toate elementele relevante
    console.log("?? Available elements:");
    console.log("   - get-article-button:", #get-article-button.length);
    console.log("   - additional-instructions:", #additional-instructions.length);
    console.log("   - custom-source-url:", #custom-source-url.length);
    console.log("   - post_ID:", #post_ID.length);
    
    const generateBtn = #get-article-button;
    if (generateBtn.length) {
        console.log("? Generate button found:", generateBtn);
        console.log("   - Button HTML:", generateBtn[0].outerHTML);
    } else {
        console.log("? Generate button NOT found!");
        console.log("?? Searching for any button with 'generate' in class or id...");
        button, input[type=button].each(function() {
            const elem = ;
            if (elem.attr("id") && elem.attr("id").toLowerCase().includes("generate")) {
                console.log("Found button with generate in ID:", elem[0].outerHTML);
            }
            if (elem.attr("class") && elem.attr("class").toLowerCase().includes("generate")) {
                console.log("Found button with generate in class:", elem[0].outerHTML);
            }
        });
    }
    
    // Handler pentru butonul de generare articol
    #get-article-button.on("click", function() {
        console.log("?? GENERATE ARTICLE BUTTON CLICKED!");
        
        const additionalInstructions = #additional-instructions.val();
        const customSourceUrl = #custom-source-url.val();
        const postID = #post_ID.val();
        const button = ;

        console.log("?? COLLECTED DATA:");
        console.log("   - Post ID:", postID);
        console.log("   - Additional Instructions:", additionalInstructions);
        console.log("   - Custom Source URL:", customSourceUrl);
        console.log("   - Button element:", button);

        // Verificri de validare
        if (!postID) {
            console.error("? POST ID is missing!");
            alert("Eroare: ID-ul postrii lipsete!");
            return;
        }

        if (!customSourceUrl && !additionalInstructions) {
            console.warn("?? Both custom URL and instructions are empty");
        }

        // Dezactivm butonul i adugm un loader
        button.prop("disabled", true);
        button.html("? Generare...");
        console.log("?? Button disabled, starting AJAX call...");

        const ajaxData = {
            action: "get_article_from_sources",
            post_id: postID,
            instructions: additionalInstructions,
            custom_source_url: customSourceUrl,
            additional_instructions: additionalInstructions,
            security: autoAiNewsPosterAjax.get_article_nonce
        };
        
        console.log("?? AJAX DATA TO SEND:", ajaxData);

        $.ajax({
            url: autoAiNewsPosterAjax.ajax_url,
            method: "POST",
            data: ajaxData,
            beforeSend: function(xhr) {
                console.log("?? AJAX Request starting...");
                console.log("   - URL:", autoAiNewsPosterAjax.ajax_url);
                console.log("   - Method: POST");
            },
            success: function(response) {
                console.log("? AJAX SUCCESS - Raw response:", response);
                console.log("?? Response type:", typeof response);
                console.log("?? Response success property:", response.success);
                
                if (response.success) {
                    console.log("?? Article generation successful!");
                    console.log("?? Response data:", response.data);
                    
                    if (response.data && response.data.post_id) {
                        const redirectUrl = autoAiNewsPosterAjax.ajax_url.replace("admin-ajax.php", "post.php") + "?post=" + response.data.post_id + "&action=edit";
                        console.log("?? Redirecting to:", redirectUrl);
                        window.location.href = redirectUrl;
                    } else {
                        console.error("? No post_id in response data");
                        alert("Eroare: ID-ul postrii nu a fost returnat");
                    }
                } else {
                    console.error("? AJAX Success but response.success is false");
                    console.error("?? Error message:", response.data ? response.data.message : "No message");
                    const errorMsg = response.data && response.data.message ? response.data.message : "Eroare necunoscut";
                    alert("A aprut o eroare: " + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error("?? AJAX ERROR occurred!");
                console.error("   - Status:", status);
                console.error("   - Error:", error);
                console.error("   - Response Text:", xhr.responseText);
                console.error("   - Status Code:", xhr.status);
                console.error("   - Ready State:", xhr.readyState);
                
                alert("A aprut o eroare la procesarea cererii. Verific consola pentru detalii.");
            },
            complete: function(xhr, status) {
                console.log("?? AJAX COMPLETE - Status:", status);
                // Reactivm butonul i eliminm loader-ul
                button.prop("disabled", false);
                button.html("<span>?</span> Genereaz articol");
                console.log("?? Button re-enabled");
            }
        });
    });
    
    // Handler pentru butonul de generare imagine AI
    #generate-image-button.on("click", function() {
        console.log("?? GENERATE IMAGE BUTTON CLICKED!");
        
        const postID = #post_ID.val();
        const button = ;
        const feedbackText = #feedback-text.val();

        console.log("?? COLLECTED DATA for image generation:");
        console.log("   - Post ID:", postID);
        console.log("   - Feedback Text:", feedbackText);

        if (!postID) {
            console.error("? POST ID is missing for image generation!");
            alert("Eroare: ID-ul postrii lipsete pentru generarea imaginii!");
            return;
        }
        
        button.prop("disabled", true);
        button.html("? Generare imagine...");
        console.log("?? Image generation button disabled, starting AJAX call...");

        const ajaxData = {
            action: "generate_image_for_article",
            post_id: postID,
            feedback: feedbackText,
            security: autoAiNewsPosterAjax.generate_image_nonce
        };

        console.log("?? AJAX DATA TO SEND for image generation:", ajaxData);

        $.ajax({
            url: autoAiNewsPosterAjax.ajax_url,
            method: "POST",
            data: ajaxData,
            beforeSend: function(xhr) {
                console.log("?? AJAX Image Request starting...");
            },
            success: function(response) {
                console.log("? AJAX SUCCESS - Raw image response:", response);
                
                if (response.success) {
                    console.log("?? Image generation successful!");
                    location.reload();
                } else {
                    console.error("? AJAX Success but image response.success is false");
                    const errorMsg = response.data && response.data.message ? response.data.message : "Eroare necunoscut la generarea imaginii.";
                    console.error("?? Image Error message:", errorMsg);
                    alert("A aprut o eroare: " + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error("?? AJAX IMAGE ERROR occurred!");
                console.error("   - Status:\", status);
                console.error("   - Error:\", error);
                console.error("   - Response Text:\", xhr.responseText);
                alert("A aprut o eroare la generarea imaginii. Verific consola pentru detalii.\");
            },
            complete: function(xhr, status) {
                console.log("?? AJAX IMAGE COMPLETE - Status:\", status);
                button.prop("disabled", false);
                button.html("<span>??</span> Genereaz imagine AI");
                console.log("?? Image generation button re-enabled");
            }
        });
    });
});

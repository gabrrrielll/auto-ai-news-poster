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
    console.log("🚀 AANP Settings JS: Document ready. Starting setup.");

    // Logic for the settings page mode switcher
    const generationModeRadios = $('input[name="auto_ai_news_poster_settings[generation_mode]"]');
    console.log(`🔍 AANP Settings JS: Found ${generationModeRadios.length} mode switch radio buttons.`);

    function setupConditionalFields() {
        console.log("🔄 AANP Settings JS: Running setupConditionalFields...");
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
        console.log("✅ AANP Settings JS: Finished tagging parent rows.");
    }
    
    function toggleSettingsVisibility() {
        console.log("👁️ AANP Settings JS: toggleSettingsVisibility triggered.");
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
    
    // Initial setup for settings page
    setupConditionalFields();

    if (generationModeRadios.length) {
        console.log("🔗 AANP Settings JS: Attaching 'change' event listener to radio buttons.");
        // Run on page load
        toggleSettingsVisibility();
        
        // Run on change
        generationModeRadios.on('change', toggleSettingsVisibility);
    } else {
        console.log("⚠️ AANP Settings JS: No radio buttons found to attach event listener.");
    }
});

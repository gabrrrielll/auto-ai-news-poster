jQuery(document).ready(function ($) {
    console.log("🚀 AANP Settings JS: Document ready. Starting setup.");

    const generationModeRadios = $('input[name="auto_ai_news_poster_settings[generation_mode]"]');
    console.log(`🔍 AANP Settings JS: Found ${generationModeRadios.length} mode switch radio buttons.`);

    function setupConditionalFields() {
        console.log("🔄 AANP Settings JS: Running setupConditionalFields...");
        $('.settings-group').each(function (index) {
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

        $('tr[class*="settings-row-"]').hide();
        console.log("   - All conditional rows have been hidden.");

        const classToShow = '.settings-row-' + selectedMode;
        $(classToShow).show();
        console.log(`   - Attempting to show rows with class: "${classToShow}"`);
        console.log(`   - Found ${$(classToShow).length} rows to show.`);
    }

    setupConditionalFields();

    if (generationModeRadios.length) {
        console.log("🔗 AANP Settings JS: Attaching 'change' event listener to radio buttons.");
        toggleSettingsVisibility();
        generationModeRadios.on('change', toggleSettingsVisibility);
    } else {
        console.log("⚠️ AANP Settings JS: No radio buttons found to attach event listener.");
    }
    // --- Site Analyzer JS Logic ---

    $('#btn_scan_site').on('click', function () {
        var url = $('#sa_target_url').val();
        var context = $('#sa_context').val();

        if (!url) {
            alert('Please enter a Target URL.');
            return;
        }

        $('#sa_loading_spinner').show();
        $('#sa_results_area').hide();
        $('#sa_results_body').empty();

        $.post(auto_ai_news_poster_ajax.ajax_url, {
            action: 'auto_ai_scan_site',
            url: url,
            context: context,
            nonce: auto_ai_news_poster_ajax.check_settings_nonce
        }, function (response) {
            $('#sa_loading_spinner').hide();

            if (response.success) {
                var candidates = response.data.candidates;
                $('#sa_result_count').text(response.data.count);

                if (candidates.length === 0) {
                    alert('AI found no relevant articles matching your context.');
                    return;
                }

                candidates.forEach(function (item, index) {
                    var row = `<tr>
                        <td><input type="checkbox" class="sa-item-checkbox" data-url="${item.url}" data-title="${item.title}" checked></td>
                        <td>${item.title}</td>
                        <td><a href="${item.url}" target="_blank">${item.url}</a></td>
                    </tr>`;
                    $('#sa_results_body').append(row);
                });

                $('#sa_results_area').fadeIn();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

    $('#sa_select_all').on('change', function () {
        $('.sa-item-checkbox').prop('checked', $(this).is(':checked'));
    });

    $('#btn_sa_import_selected').on('click', function () {
        var selected = [];
        $('.sa-item-checkbox:checked').each(function () {
            selected.push({
                url: $(this).data('url'),
                title: $(this).data('title')
            });
        });

        if (selected.length === 0) {
            alert('Please select at least one article.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('Importing...');

        $.post(auto_ai_news_poster_ajax.ajax_url, {
            action: 'auto_ai_import_selected',
            items: selected,
            nonce: auto_ai_news_poster_ajax.check_settings_nonce
        }, function (response) {
            btn.prop('disabled', false).text('Import Selected to Queue');
            if (response.success) {
                $('#sa_import_status').text('✅ ' + response.data).fadeIn().delay(3000).fadeOut();
                // Optional: Clear selection or mark as imported
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

});
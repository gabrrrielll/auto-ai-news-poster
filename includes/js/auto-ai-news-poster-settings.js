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
    // Event listener for Scan button
    $('#btn_scan_site').on('click', function () {
        var context = $('#sa_context').val();
        var maxLinks = $('#sa_max_links').val() || 10;

        // Collect all ACTIVE URLs from our new dynamic list of SOURCES
        var activeUrls = [];
        $('.scan-link-row').each(function () {
            var isActive = $(this).find('input[type="checkbox"]').is(':checked');
            var url = $(this).find('input[type="text"]').val().trim();
            if (isActive && url) {
                activeUrls.push(url);
            }
        });

        if (activeUrls.length === 0) {
            alert('Te rugăm să adaugi și să activezi cel puțin un link de sursă în tabelul "Surse principale de scanat" de mai sus.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('Scanning ' + activeUrls.length + ' sites...');
        $('#sa_loading_spinner').show();
        $('#site_analyzer_results').hide();
        $('#sa_results_body').empty();

        $.post(auto_ai_news_poster_ajax.ajax_url, {
            action: 'auto_ai_scan_site',
            urls: activeUrls,
            context: context,
            max_links: maxLinks,
            nonce: auto_ai_news_poster_ajax.check_settings_nonce
        }, function (response) {
            $('#sa_loading_spinner').hide();
            btn.prop('disabled', false).html('🚀 Scan & Analyze');

            if (response.success) {
                var candidates = response.data.candidates;
                $('#sa_result_count').text(candidates.length);

                if (candidates.length > 0) {
                    var html = '';
                    candidates.forEach(function (item, index) {
                        html += '<tr>' +
                            '<td class="check-column"><input type="checkbox" class="sa-select-item" value="' + item.url + '" data-title="' + item.title + '"></td>' +
                            '<td>' + item.title + '</td>' +
                            '<td><a href="' + item.url + '" target="_blank">' + item.url + '</a></td>' +
                            '</tr>';
                    });
                    $('#sa_results_body').html(html);
                    $('#site_analyzer_results').fadeIn();
                } else {
                    alert('Nu am găsit niciun link relevant conform criteriilor AI.');
                }
            } else {
                alert('Eroare: ' + response.data);
            }
        });
    });

    // Select all logic
    $(document).on('change', '#sa_select_all', function () {
        $('.sa-select-item').prop('checked', $(this).is(':checked'));
    });

    // Import logic
    $('#btn_sa_import_selected').on('click', function () {
        var selected = [];
        $('.sa-select-item:checked').each(function () {
            selected.push({
                url: $(this).val(),
                title: $(this).data('title')
            });
        });

        if (selected.length === 0) {
            alert('Selectați cel puțin un link pentru import.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('Importing...');
        $('#sa_import_status').text('');

        $.post(auto_ai_news_poster_ajax.ajax_url, {
            action: 'auto_ai_import_selected',
            items: selected,
            nonce: auto_ai_news_poster_ajax.check_settings_nonce
        }, function (response) {
            btn.prop('disabled', false).text('Import Selected to Queue');
            if (response.success) {
                $('#sa_import_status').text('Import reușit!').fadeIn().delay(3000).fadeOut();

                // Update the textarea UI in real-time
                var $textarea = $('#bulk_custom_source_urls');
                var currentVal = $textarea.val().trim();
                var newLinks = selected.map(function (item) { return item.url; });

                // Add new links to textarea if not already there
                var existingLinks = currentVal ? currentVal.split("\n").map(function (s) { return s.trim(); }) : [];
                newLinks.forEach(function (link) {
                    if (existingLinks.indexOf(link) === -1) {
                        existingLinks.push(link);
                    }
                });

                $textarea.val(existingLinks.join("\n"));

                // Scroll to the queue
                $('html, body').animate({
                    scrollTop: $textarea.offset().top - 100
                }, 500);

                // Visual feedback for the textarea
                $textarea.css('background-color', '#e7f9ed').delay(1000).queue(function (next) {
                    $(this).css('background-color', '');
                    next();
                });

                // Clear selection
                $('.sa-select-item').prop('checked', false);
                $('#sa_select_all').prop('checked', false);
            } else {
                alert('Eroare la import: ' + response.data);
            }
        });
    });

});
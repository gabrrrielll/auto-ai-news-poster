jQuery(document).ready(function ($) {
    console.log("🚀 AANP Settings JS: Document ready. Starting setup.");

    const modeTabs = $('.mode-tab');
    const generationModeHidden = $('#generation_mode_hidden');

    function setupConditionalFields() {
        console.log("🔄 AANP Settings JS: Running setupConditionalFields...");
        $('.settings-group').each(function (index) {
            const row = $(this).closest('tr');
            if ($(this).hasClass('settings-group-parse_link')) {
                row.addClass('settings-row-parse_link');
            }
            if ($(this).hasClass('settings-group-ai_browsing')) {
                row.addClass('settings-row-ai_browsing');
            }
            if ($(this).hasClass('settings-group-tasks')) {
                row.addClass('settings-row-tasks');
            }
        });
        console.log("✅ AANP Settings JS: Finished tagging parent rows.");
    }

    function toggleSettingsVisibility() {
        const selectedMode = generationModeHidden.val() || 'parse_link';
        console.log(`👁️ AANP Settings JS: Toggling visibility for mode: "${selectedMode}"`);

        // Hide all conditional rows
        $('tr[class*="settings-row-"]').hide();
        $('.settings-group').removeClass('active');
        $('.tab-description').hide();

        // Show active mode groups
        const rowsToShow = '.settings-row-' + selectedMode;
        $(rowsToShow).show();
        $('.settings-group-' + selectedMode).addClass('active');
        $('#tab-description-' + selectedMode).fadeIn();

        console.log(`   - Showing rows with class: "${selectedMode}"`);
    }

    // Tab Click Handler
    modeTabs.on('click', function () {
        const mode = $(this).data('mode');
        console.log("️ AANP Settings JS: Tab clicked:", mode);

        modeTabs.removeClass('active');
        $(this).addClass('active');

        generationModeHidden.val(mode);
        toggleSettingsVisibility();
    });

    setupConditionalFields();
    toggleSettingsVisibility();

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

    // === TASKS MANAGEMENT LOGIC ===

    // Switch Tasks AI Provider wrappers
    $(document).on('change', '#tasks_api_provider', function () {
        const provider = $(this).val();
        $('.tasks-provider-wrapper').hide();
        $('#tasks-wrapper-' + provider).fadeIn();
    });

    // Add New Task List
    $('#add-task-list-btn').on('click', function () {
        const container = $('#task-lists-container');
        const template = $('#task-list-template').html();
        const index = container.find('.task-list-item').length;
        const id = Math.random().toString(36).substr(2, 9);

        const html = template
            .replace(/{{INDEX}}/g, index)
            .replace(/{{ID}}/g, id);

        container.find('.no-task-lists').remove();
        container.append(html);

        // Visual feedback
        container.find('.task-list-item').last().hide().fadeIn();
    });

    // Remove Task List
    $(document).on('click', '.remove-task-list', function () {
        if (confirm('Sigur vrei să ștergi această listă de taskuri?')) {
            $(this).closest('.task-list-item').fadeOut(300, function () {
                $(this).remove();
                // Re-index lists
                reindexTaskLists();
            });
        }
    });

    function reindexTaskLists() {
        $('#task-lists-container .task-list-item').each(function (index) {
            $(this).find('[name^="auto_ai_news_poster_settings[task_lists]"]').each(function () {
                const name = $(this).attr('name');
                const newName = name.replace(/task_lists\]\[\d+\]/, 'task_lists][' + index + ']');
                $(this).attr('name', newName);
            });
        });

        if ($('#task-lists-container .task-list-item').length === 0) {
            $('#task-lists-container').html('<div class="no-task-lists alert alert-light" style="text-align: center; border: 1px dashed #ccc; padding: 40px;">Nu ai nicio listă de taskuri creată. Apasă butonul de mai sus pentru a începe.</div>');
        }
    }

    // Run Task List Now (AJAX)
    $(document).on('click', '.run-task-list-now', function () {
        const btn = $(this);
        const listItem = btn.closest('.task-list-item');
        const listId = listItem.data('id');
        const textarea = listItem.find('textarea[name*="[titles]"]');
        const titles = textarea.val().trim();

        if (!titles) {
            alert('Te rugăm să adaugi cel puțin un titlu în listă.');
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Se generează...');
        listItem.css('opacity', '0.7');

        $.post(auto_ai_news_poster_ajax.ajax_url, {
            action: 'auto_ai_run_task_list_item',
            list_id: listId,
            nonce: auto_ai_news_poster_ajax.check_settings_nonce
        }, function (response) {
            btn.prop('disabled', false).html('<i class="fas fa-magic"></i> Generează acum');
            listItem.css('opacity', '1');

            if (response.success) {
                // Success feedback
                alert('Succes: ' + response.data.message);

                // Update titles in textarea (remove first one)
                const lines = titles.split('\n');
                lines.shift();
                textarea.val(lines.join('\n'));

                // If it was the last title, maybe visual feedback
                if (lines.length === 0) {
                    textarea.css('border-color', '#28a745');
                }
            } else {
                alert('Eroare: ' + response.data.message);
            }
        });
    });

    // --- Unified AI Config Card Handles ---

    // Toggle Provider Wrappers
    $(document).on('change', '.api-provider-select', function () {
        const card = $(this).closest('.ai-config-card');
        const provider = $(this).val();
        card.find('.provider-wrapper').hide();
        card.find('.wrapper-' + provider).fadeIn();
    });

    // Refresh Models
    $(document).on('click', '.refresh-models-btn', function () {
        const btn = $(this);
        const provider = btn.data('provider');
        const card = btn.closest('.ai-config-card');
        const context = card.data('context');
        const apiKey = card.find('.wrapper-' + provider + ' .api-key-input').val();

        if (!apiKey) {
            alert('Te rugăm să introduci cheia API pentru ' + provider);
            return;
        }

        const originalText = btn.html();
        btn.prop('disabled', true).html('⏳ ...');

        let action = 'refresh_openai_models';
        let nonce = auto_ai_news_poster_ajax.refresh_models_nonce;

        if (provider === 'gemini') {
            action = 'refresh_gemini_models';
            nonce = auto_ai_news_poster_ajax.refresh_gemini_nonce;
        } else if (provider === 'deepseek') {
            action = 'refresh_deepseek_models';
            nonce = auto_ai_news_poster_ajax.refresh_deepseek_nonce;
        }

        $.post(auto_ai_news_poster_ajax.ajax_url, {
            action: action,
            api_key: apiKey,
            nonce: nonce,
            context: context
        }, function (response) {
            if (response.success) {
                alert('Success: Lista de modele a fost actualizată.');
                location.reload();
            } else {
                alert('Eroare: ' + (response.data || response.statusText || 'Necunoscută'));
            }
        }).fail(function (err) {
            alert('Request Failed: ' + err.statusText);
        }).always(() => {
            btn.prop('disabled', false).html(originalText);
        });
    });

});

/**
 * WP AI Theme Editor — Admin JavaScript
 *
 * Handles: file list loading, CodeMirror viewer, AI chat (question + edit modes),
 * diff display via diff2html, apply/discard, live preview, and backup restore.
 *
 * Dependencies (loaded by class-admin-page.php):
 *   jQuery, CodeMirror 5 (php + css modes), jsDiff, diff2html-ui
 *
 * Data from PHP (via wp_localize_script):
 *   wpaiData.nonce    — wp_rest nonce for X-WP-Nonce header
 *   wpaiData.restUrl  — base URL e.g. https://site.com/wp-json/wpai/v1/
 *   wpaiData.siteUrl  — home URL (unused currently, available for extensions)
 */

(function ($) {
    'use strict';

    // ── State ─────────────────────────────────────────────────────────────────

    var state = {
        fileKey:          null,    // Currently loaded file key
        originalContent:  null,    // Content as loaded from server
        modifiedContent:  null,    // Content returned by Claude edit (pending apply)
        mode:             'question',
        abortController:  null,    // For cancelling in-flight Claude requests
        editor:           null,    // CodeMirror instance
        isDirty:          false,   // True when editor has unsaved manual edits
    };

    // ── Init ──────────────────────────────────────────────────────────────────

    $(document).ready(function () {
        initEditor();
        loadFileList();
        bindEvents();
    });

    // ── CodeMirror ────────────────────────────────────────────────────────────

    function initEditor() {
        state.editor = CodeMirror.fromTextArea(
            document.getElementById('wpai-code-editor'),
            {
                lineNumbers:  true,
                theme:        'monokai',
                mode:         'text/x-php',
                readOnly:     false,
                lineWrapping: false,
                tabSize:      4,
                extraKeys:    { 'Ctrl-F': 'findPersistent' },
            }
        );
        state.editor.setSize('100%', '520px');

        // Track unsaved manual edits
        state.editor.on('change', function () {
            if (state.originalContent === null) return;
            var currentVal = state.editor.getValue();
            var dirty = (currentVal !== state.originalContent);
            if (dirty !== state.isDirty) {
                state.isDirty = dirty;
                setDirty(dirty);
            }
        });
    }

    function setEditorMode(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        state.editor.setOption('mode', ext === 'css' ? 'css' : 'text/x-php');
    }

    function clearHighlights() {
        state.editor.eachLine(function (line) {
            state.editor.removeLineClass(line, 'background', 'wpai-highlight');
        });
    }

    function highlightLines(lineNumbers) {
        clearHighlights();
        var scrolledToFirst = false;
        lineNumbers.forEach(function (lineNum) {
            var idx = lineNum - 1; // CodeMirror is 0-indexed
            if (idx >= 0 && idx < state.editor.lineCount()) {
                state.editor.addLineClass(idx, 'background', 'wpai-highlight');
                if (!scrolledToFirst) {
                    state.editor.scrollIntoView({ line: idx, ch: 0 }, 120);
                    scrolledToFirst = true;
                }
            }
        });
    }

    // ── Dirty state (manual edits) ────────────────────────────────────────────

    function setDirty(dirty) {
        state.isDirty = dirty;
        if (dirty) {
            $('#wpai-dirty-indicator').show();
            $('#wpai-save-btn').show();
        } else {
            $('#wpai-dirty-indicator').hide();
            $('#wpai-save-btn').hide();
        }
    }

    function saveDirectEdit() {
        if (!state.fileKey || !state.isDirty) return;

        var content = state.editor.getValue();

        // eslint-disable-next-line no-alert
        if (!confirm('Save your edits to the live file?\n\nA backup of the current version will be created automatically.')) return;

        showLoading('Saving file…');
        wpaiFetch('apply', {
            file_key: state.fileKey,
            content:  content,
        }).then(function () {
            hideLoading();
            state.originalContent = content;
            state.editor.clearHistory();
            setDirty(false);
            showNotice('success', 'File saved. A backup of the previous version was created automatically.');
        }).catch(function (err) {
            hideLoading();
            showNotice('error', 'Could not save file: ' + err);
        });
    }

    // ── File list ─────────────────────────────────────────────────────────────

    function loadFileList() {
        wpaiFetch('files', {}).then(function (data) {
            var $select = $('#wpai-file-select');
            $select.empty().append($('<option>').val('').text('— Select a file to edit —'));

            var groups = {};
            (data.files || []).forEach(function (f) {
                if (!groups[f.group]) groups[f.group] = [];
                groups[f.group].push(f);
            });

            Object.keys(groups).sort().forEach(function (group) {
                var $optgroup = $('<optgroup>').attr('label', group);
                groups[group].forEach(function (f) {
                    $optgroup.append($('<option>').val(f.key).text(f.label));
                });
                $select.append($optgroup);
            });

            $('#wpai-load-file').prop('disabled', false);

        }).catch(function (err) {
            showNotice('error', 'Could not load file list: ' + err);
            $('#wpai-file-select').empty().append($('<option>').text('— Error loading files —'));
        });
    }

    // ── Load file ─────────────────────────────────────────────────────────────

    function loadFile(fileKey) {
        if (!fileKey) return;

        showLoading('Loading file…');
        wpaiFetch('read', { file_key: fileKey }).then(function (data) {
            hideLoading();

            state.fileKey        = fileKey;
            state.originalContent = data.content;
            state.modifiedContent = null;

            var filename = getFilename(fileKey);
            setEditorMode(filename);
            state.editor.setValue(data.content);
            state.editor.clearHistory();
            clearHighlights();

            $('#wpai-current-filename').text(filename);
            $('#wpai-file-info').text(
                data.line_count + ' lines · ' + formatBytes(data.size_bytes)
            ).show();

            setDirty(false);
            $('#wpai-workspace').show();
            $('#wpai-diff-panel').hide();
            $('#wpai-chat-input').prop('disabled', false);
            $('#wpai-send-btn').prop('disabled', false);

            if (data.was_truncated) {
                showNotice('warning', 'This file is large and was partially sent to the AI. Very long sections in the middle may not be editable in this session.');
            }

        }).catch(function (err) {
            hideLoading();
            showNotice('error', 'Could not load file: ' + err);
        });
    }

    // ── Send message to Claude ────────────────────────────────────────────────

    function sendMessage() {
        var message = $('#wpai-chat-input').val().trim();
        if (!message || !state.fileKey) return;

        $('#wpai-chat-input').val('');
        $('#wpai-chat-intro').remove(); // Hide intro text after first message
        appendMessage('user', escapeHtml(message));

        var $thinking = appendMessage('ai', '<em>Thinking…</em>');
        setSendState(true); // show cancel, hide send

        if (window.AbortController) {
            state.abortController = new AbortController();
        }

        var signal = state.abortController ? state.abortController.signal : null;

        wpaiFetch('ask', {
            file_key: state.fileKey,
            message:  message,
            mode:     state.mode,
        }, signal).then(function (data) {
            state.abortController = null;
            setSendState(false);
            $thinking.remove();

            if (data.type === 'answer') {
                handleQuestionResponse(data);
            } else if (data.type === 'edit') {
                handleEditResponse(data);
            }

        }).catch(function (err) {
            state.abortController = null;
            setSendState(false);
            $thinking.remove();

            if (err === 'aborted') {
                appendMessage('ai', '<em>Request cancelled.</em>');
            } else {
                appendMessage('ai', '<strong>Error:</strong> ' + escapeHtml(String(err)));
            }
        });
    }

    function handleQuestionResponse(data) {
        // Format answer: bold line references for visibility
        var formatted = escapeHtml(data.answer)
            .replace(/\[Lines?\s+[\d\s\-–,]+\]/gi, function (match) {
                return '<strong>' + match + '</strong>';
            });

        appendMessage('ai', formatted);

        if (data.highlighted_lines && data.highlighted_lines.length > 0) {
            highlightLines(data.highlighted_lines);
            appendMessage('ai', '<em>↑ ' + data.highlighted_lines.length + ' line(s) highlighted in the code viewer.</em>');
        }

        if (data.was_truncated) {
            appendMessage('ai', '<em>⚠ Note: this file was partially truncated before sending. The answer reflects only the portion that was sent.</em>');
        }
    }

    function handleEditResponse(data) {
        state.modifiedContent = data.modified_content;

        appendMessage('ai',
            '<strong>Edit ready.</strong> ' + escapeHtml(data.summary) +
            ' <em>Review the diff below, then apply or discard.</em>'
        );

        showDiff(
            data.original_content || state.originalContent,
            data.modified_content,
            data.summary,
            getFilename(state.fileKey)
        );

        $('#wpai-diff-panel').show();
        $('html, body').animate({ scrollTop: $('#wpai-diff-panel').offset().top - 60 }, 300);

        if (data.was_truncated) {
            appendMessage('ai', '<em>⚠ This file was large and partially truncated. Only the visible portion was edited.</em>');
        }
    }

    // ── Diff display ──────────────────────────────────────────────────────────

    function showDiff(original, modified, summary, filename) {
        $('#wpai-diff-summary').text(summary || '');

        filename = filename || 'file';

        // Generate unified diff using jsDiff
        var patch = Diff.createPatch(filename, original, modified, 'Before', 'After');

        var container = document.getElementById('wpai-diff-container');
        container.innerHTML = ''; // clear previous

        var diff2htmlUi = new Diff2HtmlUI(container, patch, {
            drawFileList:  false,
            matching:      'lines',
            outputFormat:  'side-by-side',
        });
        diff2htmlUi.draw();
    }

    // ── Apply / Discard ───────────────────────────────────────────────────────

    function applyChanges() {
        if (!state.fileKey || !state.modifiedContent) return;

        // eslint-disable-next-line no-alert
        if (!confirm('Apply these changes to the live file?\n\nA backup will be created automatically before saving.')) return;

        showLoading('Saving file…');
        wpaiFetch('apply', {
            file_key: state.fileKey,
            content:  state.modifiedContent,
        }).then(function () {
            hideLoading();
            state.originalContent = state.modifiedContent;
            state.modifiedContent = null;
            state.editor.setValue(state.originalContent);
            state.editor.clearHistory();
            clearHighlights();
            setDirty(false);
            $('#wpai-diff-panel').hide();
            showNotice('success', 'File saved successfully. A backup was created automatically.');
        }).catch(function (err) {
            hideLoading();
            showNotice('error', 'Could not save file: ' + err);
        });
    }

    function discardChanges() {
        state.modifiedContent = null;
        $('#wpai-diff-panel').slideUp(150);
        appendMessage('ai', '<em>Changes discarded.</em>');
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    function openPreview() {
        if (!state.fileKey || !state.modifiedContent) return;

        showLoading('Creating preview link…');
        wpaiFetch('preview', {
            file_key: state.fileKey,
            content:  state.modifiedContent,
        }).then(function (data) {
            hideLoading();
            window.open(data.preview_url, '_blank');
            showNotice('success', 'Preview opened in a new tab. It will expire in 30 minutes. Return here to Apply or Discard.');
        }).catch(function (err) {
            hideLoading();
            showNotice('error', 'Could not create preview: ' + err);
        });
    }

    // ── Backups ───────────────────────────────────────────────────────────────

    function loadBackups() {
        if (!state.fileKey) return;

        var $inner = $('#wpai-backups-dropdown .wpai-dropdown-inner');
        $inner.html('<p class="wpai-dropdown-loading">Loading backups…</p>');

        wpaiFetch('backups', { file_key: state.fileKey }).then(function (data) {
            var backups = data.backups || [];

            if (backups.length === 0) {
                $inner.html('<p class="wpai-dropdown-empty">No backups yet. Backups are created automatically each time you apply a change.</p>');
                return;
            }

            var $list = $('<ul class="wpai-backup-list">');
            backups.forEach(function (b) {
                var $item = $('<li class="wpai-backup-item">');
                $item.append('<span class="wpai-backup-label">' + escapeHtml(b.label) + '</span>');
                var $restore = $('<a href="#" class="wpai-restore-link">Restore</a>')
                    .data('path', b.path);
                $item.append($restore);
                $list.append($item);
            });

            $inner.empty().append($list);

        }).catch(function (err) {
            $inner.html('<p class="wpai-dropdown-error">Error loading backups: ' + escapeHtml(err) + '</p>');
        });
    }

    function restoreBackup(backupPath) {
        // eslint-disable-next-line no-alert
        if (!confirm('Restore this backup? The current live file will be backed up first, so this is reversible.')) return;

        $('#wpai-backups-dropdown').hide();
        showLoading('Restoring backup…');

        wpaiFetch('restore', {
            file_key:    state.fileKey,
            backup_path: backupPath,
        }).then(function (data) {
            hideLoading();

            if (data.restored_content !== undefined) {
                state.originalContent = data.restored_content;
                state.modifiedContent = null;
                state.editor.setValue(data.restored_content);
                state.editor.clearHistory();
                clearHighlights();
                setDirty(false);
                $('#wpai-diff-panel').hide();
            }

            showNotice('success', 'Backup restored. The previous version has been saved as a new backup.');
            appendMessage('ai', '<em>Backup restored. The editor now shows the restored version.</em>');

        }).catch(function (err) {
            hideLoading();
            showNotice('error', 'Could not restore backup: ' + err);
        });
    }

    // ── New File / Child Theme modal ──────────────────────────────────────────

    var modal = {
        childThemesLoaded: false,
    };

    function openNewModal() {
        $('#wpai-new-modal').fadeIn(150);
        // Load theme list for child-theme tab if not already loaded
        if (!modal.childThemesLoaded) {
            loadThemeList();
        }
        // Reset to New File tab
        switchModalTab('new-file');
    }

    function closeNewModal() {
        $('#wpai-new-modal').fadeOut(150);
    }

    function switchModalTab(tab) {
        $('.wpai-modal-tab').attr('aria-selected', 'false').removeClass('wpai-modal-tab-active');
        $('[data-tab="' + tab + '"]').attr('aria-selected', 'true').addClass('wpai-modal-tab-active');
        $('.wpai-modal-body').hide();
        $('[data-tab-panel="' + tab + '"]').show();
    }

    function loadThemeList() {
        wpaiFetch('list-themes', {}).then(function (data) {
            modal.childThemesLoaded = true;
            var $select = $('#wpai-parent-theme');
            $select.empty().append($('<option>').val('').text('— Select parent theme —'));
            (data.themes || []).forEach(function (t) {
                if (!t.is_child) { // Only non-child themes can be parents
                    $select.append($('<option>').val(t.slug).text(t.name));
                }
            });
        }).catch(function () {
            $('#wpai-parent-theme').empty().append($('<option>').text('— Could not load themes —'));
        });
    }

    function createNewFile() {
        var destination  = $('input[name="wpai_new_destination"]:checked').val();
        var filename     = $('#wpai-new-filename').val().trim();
        var contentType  = $('input[name="wpai_new_content_type"]:checked').val();
        var description  = $('#wpai-new-file-description').val().trim();

        if (!filename) {
            showNotice('error', 'Please enter a filename.');
            return;
        }

        if (contentType === 'ai' && !description) {
            showNotice('error', 'Please describe what this file should do so the AI can generate it.');
            return;
        }

        var $btn = $('#wpai-create-file-btn').prop('disabled', true).text('Working…');

        var doCreate = function (content) {
            wpaiFetch('create-file', {
                destination: destination,
                filename:    filename,
                content:     content,
            }).then(function (data) {
                $btn.prop('disabled', false).text('Create File');
                closeNewModal();
                showNotice('success', 'File created: ' + escapeHtml(filename) + '. Refreshing file list…');
                loadFileList();
                // Auto-load the new file after the list refreshes
                setTimeout(function () {
                    loadFile(data.file_key);
                }, 800);
            }).catch(function (err) {
                $btn.prop('disabled', false).text('Create File');
                showNotice('error', 'Could not create file: ' + escapeHtml(String(err)));
            });
        };

        if (contentType === 'ai') {
            showLoading('Asking AI to generate file…');
            var themeName = wp_get_theme_name(); // resolved below
            wpaiFetch('generate-content', {
                type:        'file',
                filename:    filename,
                description: description,
                theme_name:  themeName,
            }).then(function (data) {
                hideLoading();
                doCreate(data.content || '');
            }).catch(function (err) {
                hideLoading();
                $btn.prop('disabled', false).text('Create File');
                showNotice('error', 'AI generation failed: ' + escapeHtml(String(err)));
            });
        } else {
            doCreate('');
        }
    }

    function createChildTheme() {
        var childName    = $('#wpai-child-name').val().trim();
        var parentSlug   = $('#wpai-parent-theme').val();
        var parentName   = $('#wpai-parent-theme option:selected').text();
        var contentType  = $('input[name="wpai_child_content_type"]:checked').val();
        var description  = $('#wpai-child-description').val().trim();

        if (!childName) {
            showNotice('error', 'Please enter a name for the child theme.');
            return;
        }
        if (!parentSlug) {
            showNotice('error', 'Please select a parent theme.');
            return;
        }
        if (contentType === 'ai' && !description) {
            showNotice('error', 'Please describe the customisations you plan to make.');
            return;
        }

        var $btn = $('#wpai-create-child-btn').prop('disabled', true).text('Working…');

        var doCreate = function (files) {
            wpaiFetch('create-child-theme', {
                theme_name: childName,
                files:      files,
            }).then(function (data) {
                $btn.prop('disabled', false).text('Create Child Theme');
                closeNewModal();
                showNotice(
                    'success',
                    'Child theme "' + escapeHtml(childName) + '" created. ' +
                    '<a href="' + escapeHtml(data.activate_url) + '" target="_blank">Go to Themes page to activate it.</a>'
                );
            }).catch(function (err) {
                $btn.prop('disabled', false).text('Create Child Theme');
                showNotice('error', 'Could not create child theme: ' + escapeHtml(String(err)));
            });
        };

        if (contentType === 'ai') {
            showLoading('Asking AI to generate starter files…');
            wpaiFetch('generate-content', {
                type:        'child-theme',
                child_name:  childName,
                theme_name:  parentName,
                parent_slug: parentSlug,
                description: description,
            }).then(function (data) {
                hideLoading();
                doCreate(data.files || {});
            }).catch(function (err) {
                hideLoading();
                $btn.prop('disabled', false).text('Create Child Theme');
                showNotice('error', 'AI generation failed: ' + escapeHtml(String(err)));
            });
        } else {
            // Build minimal stubs
            var minimalFiles = buildMinimalChildThemeFiles(childName, parentSlug);
            doCreate(minimalFiles);
        }
    }

    function buildMinimalChildThemeFiles(childName, parentSlug) {
        return {
            'style.css': (
                '/*\n' +
                'Theme Name: ' + childName + '\n' +
                'Template:   ' + parentSlug + '\n' +
                'Version:    1.0.0\n' +
                '*/\n'
            ),
            'functions.php': (
                '<?php\n' +
                '/**\n' +
                ' * ' + childName + ' child theme functions.\n' +
                ' */\n\n' +
                'add_action( \'wp_enqueue_scripts\', function () {\n' +
                '    wp_enqueue_style(\n' +
                '        \'parent-style\',\n' +
                '        get_template_directory_uri() . \'/style.css\'\n' +
                '    );\n' +
                '} );\n'
            ),
            'index.php': (
                '<?php\n' +
                '// Silence is golden.\n'
            ),
        };
    }

    // Helper: read active theme name from the file dropdown optgroup labels
    function wp_get_theme_name() {
        var label = $('#wpai-file-select optgroup:first').attr('label') || '';
        // Label format: "Theme: My Theme Name"
        return label.replace(/^Theme:\s*/i, '') || 'WordPress Theme';
    }

    // ── Event binding ─────────────────────────────────────────────────────────

    function bindEvents() {

        // New File modal
        $('#wpai-new-file-btn').on('click', openNewModal);
        $('#wpai-modal-close, .wpai-modal-cancel-btn').on('click', closeNewModal);
        $('#wpai-new-modal').on('click', function (e) {
            if ($(e.target).is('#wpai-new-modal')) closeNewModal();
        });

        // Modal tab switching
        $(document).on('click', '.wpai-modal-tab', function () {
            switchModalTab($(this).data('tab'));
        });

        // Toggle AI description rows
        $(document).on('change', 'input[name="wpai_new_content_type"]', function () {
            var show = $(this).val() === 'ai';
            $('#wpai-new-file-ai-row').toggle(show);
        });
        $(document).on('change', 'input[name="wpai_child_content_type"]', function () {
            var show = $(this).val() === 'ai';
            $('#wpai-child-ai-row').toggle(show);
        });

        // Create buttons
        $('#wpai-create-file-btn').on('click', createNewFile);
        $('#wpai-create-child-btn').on('click', createChildTheme);

        // Close modal on Escape
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#wpai-new-modal').is(':visible')) closeNewModal();
        });

        // Help section toggle
        $('#wpai-help-toggle').on('click', function () {
            var $section = $('#wpai-help-section');
            var open = $section.is(':visible');
            $section.slideToggle(180);
            $(this).text(open ? '? How to use' : '✕ Close help');
        });

        // Save direct edits
        $('#wpai-save-btn').on('click', saveDirectEdit);

        // Load file button
        $('#wpai-load-file').on('click', function () {
            var key = $('#wpai-file-select').val();
            if (key) loadFile(key);
        });

        // Also load on Enter key in the select
        $('#wpai-file-select').on('keydown', function (e) {
            if (e.key === 'Enter') {
                var key = $(this).val();
                if (key) loadFile(key);
            }
        });

        // Mode toggle
        $(document).on('change', 'input[name="wpai_mode"]', function () {
            state.mode = $(this).val();

            // Update active label style
            $('.wpai-mode-label').removeClass('wpai-mode-active');
            $(this).closest('.wpai-mode-label').addClass('wpai-mode-active');

            // Update placeholder and button color
            if (state.mode === 'edit') {
                $('#wpai-chat-input').attr('placeholder', 'Describe a change to make to this file…');
                $('#wpai-send-btn')
                    .removeClass('wpai-btn-primary')
                    .addClass('wpai-btn-edit')
                    .text('Send Edit');
            } else {
                $('#wpai-chat-input').attr('placeholder', 'Ask a question about this file…');
                $('#wpai-send-btn')
                    .removeClass('wpai-btn-edit')
                    .addClass('wpai-btn-primary')
                    .text('Send');
            }
        });

        // Send button
        $('#wpai-send-btn').on('click', sendMessage);

        // Send on Ctrl+Enter / Cmd+Enter
        $('#wpai-chat-input').on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });

        // Cancel in-flight request
        $('#wpai-cancel-btn').on('click', function () {
            if (state.abortController) {
                state.abortController.abort();
                state.abortController = null;
            }
        });

        // Copy code
        $('#wpai-copy-btn').on('click', function () {
            var content = state.editor ? state.editor.getValue() : '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(content).then(function () {
                    showNotice('success', 'Code copied to clipboard.');
                });
            } else {
                // Fallback for older browsers
                var $tmp = $('<textarea>').val(content).appendTo('body');
                $tmp[0].select();
                document.execCommand('copy');
                $tmp.remove();
                showNotice('success', 'Code copied to clipboard.');
            }
        });

        // Backups dropdown toggle
        $('#wpai-backups-btn').on('click', function (e) {
            e.stopPropagation();
            var $dropdown = $('#wpai-backups-dropdown');
            if ($dropdown.is(':hidden')) {
                loadBackups();
                $dropdown.show();
            } else {
                $dropdown.hide();
            }
        });

        // Close dropdown on outside click
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.wpai-backup-wrap').length) {
                $('#wpai-backups-dropdown').hide();
            }
        });

        // Restore backup link
        $(document).on('click', '.wpai-restore-link', function (e) {
            e.preventDefault();
            restoreBackup($(this).data('path'));
        });

        // Diff panel actions
        $('#wpai-apply-btn').on('click', applyChanges);
        $('#wpai-discard-btn').on('click', discardChanges);
        $('#wpai-preview-btn').on('click', openPreview);
    }

    // ── Shared fetch helper ───────────────────────────────────────────────────

    function wpaiFetch(endpoint, body, signal) {
        var opts = {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   wpaiData.nonce,
            },
            body: JSON.stringify(body),
        };
        if (signal) opts.signal = signal;

        return fetch(wpaiData.restUrl + endpoint, opts).then(function (res) {
            if (!res.ok) {
                return res.json().then(function (errBody) {
                    throw errBody.error || errBody.message || ('HTTP ' + res.status);
                }).catch(function (thrown) {
                    if (typeof thrown === 'string') throw thrown;
                    throw 'HTTP ' + res.status;
                });
            }
            return res.json();
        }).catch(function (err) {
            if (err && err.name === 'AbortError') throw 'aborted';
            if (typeof err === 'string') throw err;
            throw 'Request failed. Check your internet connection.';
        });
    }

    // ── UI helpers ────────────────────────────────────────────────────────────

    function appendMessage(role, html) {
        var $msg = $('<div>')
            .addClass('wpai-chat-message wpai-chat-' + role)
            .html(html);
        var $messages = $('#wpai-chat-messages');
        $messages.append($msg);
        $messages.scrollTop($messages[0].scrollHeight);
        return $msg;
    }

    function setSendState(loading) {
        if (loading) {
            $('#wpai-send-btn').hide();
            $('#wpai-cancel-btn').show();
        } else {
            $('#wpai-send-btn').show();
            $('#wpai-cancel-btn').hide();
        }
    }

    function showLoading(msg) {
        if (!$('#wpai-loading').length) {
            $('body').append(
                '<div id="wpai-loading" class="wpai-loading-overlay">' +
                '<div class="wpai-loading-inner">' +
                '<span class="spinner is-active"></span>' +
                '<span id="wpai-loading-msg"></span>' +
                '</div></div>'
            );
        }
        $('#wpai-loading-msg').text(msg || 'Working…');
        $('#wpai-loading').show();
    }

    function hideLoading() {
        $('#wpai-loading').hide();
    }

    function showNotice(type, message) {
        // Map 'success'/'error'/'warning'/'info' to WP notice classes
        var cls = 'notice notice-' + (type === 'info' ? 'info' : type) + ' is-dismissible wpai-notice';
        var $notice = $('<div>').addClass(cls).html('<p>' + escapeHtml(message) + '</p>');
        var $dismiss = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>');
        $dismiss.on('click', function () { $notice.fadeOut(200, function () { $notice.remove(); }); });
        $notice.append($dismiss);
        $('#wpai-notices').prepend($notice);

        // Auto-dismiss non-errors after 6 seconds
        if (type !== 'error') {
            setTimeout(function () {
                $notice.fadeOut(400, function () { $notice.remove(); });
            }, 6000);
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getFilename(fileKey) {
        // "theme:includes/nav.php" → "nav.php"
        // "plugin:core-blocks:includes/helpers.php" → "helpers.php"
        var parts = (fileKey || '').split(':');
        var rel   = parts[parts.length - 1] || fileKey;
        return rel.split('/').pop();
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

})(jQuery);

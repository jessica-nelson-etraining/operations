/**
 * Course Landing Blocks — Admin JavaScript
 *
 * Handles all the interactive behaviour in the WordPress editor:
 *  - Showing/hiding the block picker
 *  - Adding new blocks (via AJAX to get server-rendered field HTML)
 *  - Toggling block field panels open/closed
 *  - Removing blocks
 *  - Adding/removing repeater items
 *  - Drag-and-drop reordering (via jQuery UI Sortable)
 *  - Collecting all field data and saving it to the hidden JSON input
 */

(function ($) {
    'use strict';

    // ── Initialise ─────────────────────────────────────────────────────────────

    $(document).ready(function () {
        bindEvents();
        initSortable('#clb-blocks-list');
    });

    // ── Event bindings ─────────────────────────────────────────────────────────

    function bindEvents() {

        // Show block type picker
        $(document).on('click', '#clb-add-block-btn', function () {
            $('#clb-block-picker').slideDown(200);
            $(this).prop('disabled', true).css('opacity', 0.5);
        });

        // Cancel adding a block
        $(document).on('click', '#clb-cancel-add', function () {
            $('#clb-block-picker').slideUp(200);
            $('#clb-add-block-btn').prop('disabled', false).css('opacity', 1);
        });

        // User chose a block type — add it
        $(document).on('click', '.clb-block-option', function () {
            var blockType = $(this).data('type');
            addBlock(blockType);
        });

        // Toggle a block's field panel open or closed
        $(document).on('click', '.clb-btn-toggle-fields', function () {
            var $btn    = $(this);
            var $fields = $btn.closest('.clb-block-row').find('.clb-block-fields');
            var isOpen  = $fields.is(':visible');

            $fields.slideToggle(200);
            $btn.text(isOpen ? '✏️ Edit' : '▲ Close');
            $btn.attr('aria-expanded', isOpen ? 'false' : 'true');
        });

        // Remove a block
        $(document).on('click', '.clb-btn-remove', function () {
            if (!confirm('Remove this section from the page?\n\nThis will not affect any content in WordPress — it only removes the section from this landing page layout.')) {
                return;
            }
            $(this).closest('.clb-block-row').remove();
            reindexBlocks();
            saveToInput();
            showEmptyStateIfNeeded();
        });

        // Add a repeater item
        $(document).on('click', '.clb-btn-add-item', function () {
            var $repeater       = $(this).closest('.clb-repeater');
            var $itemsContainer = $repeater.find('.clb-repeater-items');
            var subFields       = JSON.parse($repeater.attr('data-sub-fields') || '{}');
            var currentCount    = $itemsContainer.find('.clb-repeater-item').length;

            // Remove "no items yet" message if present
            $itemsContainer.find('.clb-repeater-empty').remove();

            // Build and append a new empty repeater item
            var $newItem = buildRepeaterItemHtml(subFields, currentCount);
            $itemsContainer.append($newItem);
            saveToInput();
        });

        // Remove a repeater item
        $(document).on('click', '.clb-btn-remove-item', function () {
            var $repeater       = $(this).closest('.clb-repeater');
            var $itemsContainer = $repeater.find('.clb-repeater-items');
            $(this).closest('.clb-repeater-item').remove();

            // Renumber remaining items
            $itemsContainer.find('.clb-repeater-item').each(function (i) {
                $(this).find('.clb-repeater-item-num').text('Item ' + (i + 1));
            });

            // Show empty message if no items left
            if ($itemsContainer.find('.clb-repeater-item').length === 0) {
                $itemsContainer.append('<p class="clb-repeater-empty">No items yet. Click the button below to add one.</p>');
            }

            saveToInput();
        });

        // Toggle label update when a checkbox changes
        $(document).on('change', '.clb-checkbox', function () {
            var $label = $(this).closest('.clb-toggle').find('.clb-toggle-label');
            $label.text($(this).is(':checked') ? 'Enabled' : 'Disabled');
            saveToInput();
        });

        // Save whenever any field value changes
        $(document).on('change keyup', '.clb-field-value, .clb-sub-field-value', function () {
            saveToInput();
        });
    }

    // ── Add a new block ─────────────────────────────────────────────────────────

    function addBlock(blockType) {
        // Hide picker, re-enable button
        $('#clb-block-picker').slideUp(200);
        $('#clb-add-block-btn').prop('disabled', false).css('opacity', 1);

        // Remove empty state
        $('#clb-empty-state').remove();

        // Get the next available index
        var nextIndex = $('#clb-blocks-list .clb-block-row').length;

        // Show a loading placeholder while we fetch the field HTML from the server
        var $placeholder = $('<div class="clb-block-row clb-loading-row" style="padding:16px;color:#757575;">' +
            '⏳ Adding section…</div>');
        $('#clb-blocks-list').append($placeholder);

        // Ask the server to render the block editor row (PHP renders the fields)
        $.post(clbData.ajaxUrl, {
            action:      'clb_get_block_fields',
            nonce:       clbData.nonce,
            block_type:  blockType,
            block_index: nextIndex,
        }, function (response) {
            $placeholder.remove();

            if (response.success && response.data.html) {
                var $newRow = $(response.data.html);
                $('#clb-blocks-list').append($newRow);

                // Open the fields panel automatically so the user can fill it in
                $newRow.find('.clb-block-fields').show();
                $newRow.find('.clb-btn-toggle-fields').text('▲ Close').attr('aria-expanded', 'true');

                // Scroll to the new block
                $('html, body').animate({
                    scrollTop: $newRow.offset().top - 80
                }, 300);

                // Reinitialise sortable to include the new row
                initSortable('#clb-blocks-list');
                saveToInput();
            } else {
                alert('Something went wrong adding that section. Please try again.');
            }
        }).fail(function () {
            $placeholder.remove();
            alert('Could not connect to WordPress. Please refresh and try again.');
        });
    }

    // ── Drag-and-drop reordering ────────────────────────────────────────────────

    function initSortable(selector) {
        $(selector).sortable({
            handle:      '.clb-drag-handle',
            placeholder: 'clb-block-row-placeholder',
            tolerance:   'pointer',
            update: function () {
                reindexBlocks();
                saveToInput();
            }
        });
    }

    // After reordering, update the data-index attributes to reflect new positions
    function reindexBlocks() {
        $('#clb-blocks-list .clb-block-row').each(function (newIndex) {
            $(this).attr('data-index', newIndex);
        });
    }

    // ── Collect data from DOM and save to hidden input ─────────────────────────

    /**
     * Walk through every block row in the editor, collect all field values,
     * and write the result as JSON to the hidden input field.
     * WordPress saves that hidden input when the page is published/updated.
     */
    function saveToInput() {
        var blocks = [];

        $('#clb-blocks-list .clb-block-row').each(function () {
            var blockType = $(this).data('type');
            var fields    = {};

            // Collect simple (non-repeater) fields
            $(this).find('.clb-field').each(function () {
                var fieldKey = $(this).data('field-key');
                var $value   = $(this).find('> .clb-field-value, > label.clb-toggle .clb-field-value');

                if ($value.length === 0) {
                    // Could be a repeater — handled separately below
                    return;
                }

                if ($value.attr('type') === 'checkbox') {
                    fields[fieldKey] = $value.is(':checked');
                } else {
                    fields[fieldKey] = $value.val() || '';
                }
            });

            // Collect repeater fields
            $(this).find('.clb-repeater').each(function () {
                var fieldKey = $(this).data('field-key');
                var items    = [];

                $(this).find('.clb-repeater-item').each(function () {
                    var item = {};
                    $(this).find('.clb-sub-field').each(function () {
                        var subKey = $(this).data('sub-key');
                        item[subKey] = $(this).find('.clb-sub-field-value').val() || '';
                    });
                    items.push(item);
                });

                fields[fieldKey] = items;
            });

            blocks.push({
                type:   blockType,
                fields: fields,
            });
        });

        $('#clb-blocks-config').val(JSON.stringify(blocks));
    }

    // ── Utility functions ───────────────────────────────────────────────────────

    function showEmptyStateIfNeeded() {
        if ($('#clb-blocks-list .clb-block-row').length === 0) {
            $('#clb-blocks-list').html(
                '<div class="clb-empty-state" id="clb-empty-state">' +
                '<div class="clb-empty-icon">📄</div>' +
                '<p><strong>No sections added yet.</strong></p>' +
                '<p>Click <strong>"＋ Add a Section"</strong> below to start building this page.</p>' +
                '</div>'
            );
        }
    }

    /**
     * Build the HTML for a new empty repeater item.
     * Called when the user clicks "＋ Add Item" inside a repeater field.
     *
     * @param {Object} subFields  Sub-field definitions from data-sub-fields attribute
     * @param {number} index      Current item count (used for labelling)
     * @returns {jQuery}          The new item as a jQuery element
     */
    function buildRepeaterItemHtml(subFields, index) {
        var $item = $('<div class="clb-repeater-item"></div>');

        // Item header
        $item.append(
            '<div class="clb-repeater-item-header">' +
            '<span class="clb-drag-handle" title="Drag to reorder">⠿</span>' +
            '<span class="clb-repeater-item-num">Item ' + (index + 1) + '</span>' +
            '<button type="button" class="clb-btn-remove-item">✕ Remove</button>' +
            '</div>'
        );

        // Sub-fields
        $.each(subFields, function (subKey, subField) {
            var $subField = $('<div class="clb-sub-field" data-sub-key="' + subKey + '"></div>');
            $subField.append('<label>' + subField.label + '</label>');

            if (subField.type === 'icon_picker') {
                // For icon pickers, we need the full options list.
                // We request a minimal select from the server via AJAX.
                // For simplicity, provide a plain text input as fallback —
                // the user can type the Font Awesome class directly.
                $subField.append(
                    '<input type="text" class="clb-input clb-sub-field-value" ' +
                    'placeholder="e.g. fas fa-check-circle" value="" />' +
                    '<small style="color:#757575;font-size:11px;">Type a Font Awesome icon class, or leave blank for no icon.</small>'
                );
            } else {
                $subField.append(
                    '<input type="text" class="clb-input clb-sub-field-value" value="" />'
                );
            }

            $item.append($subField);
        });

        return $item;
    }

})(jQuery);

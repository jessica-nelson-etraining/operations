/**
 * eTraining Page Builder — Admin JavaScript
 *
 * Handles all interactive behaviour in the WordPress editor:
 *  - Showing/hiding the block picker with category tab filtering
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
        initSortable('#etpb-blocks-list');
    });

    // ── Event bindings ─────────────────────────────────────────────────────────

    function bindEvents() {

        // Show block type picker
        $(document).on('click', '#etpb-add-block-btn', function () {
            // Reset category tabs to "All" each time the picker opens
            $('.etpb-cat-tab').removeClass('active');
            $('.etpb-cat-tab[data-category="all"]').addClass('active');
            $('.etpb-block-option').show();

            $('#etpb-block-picker').slideDown(200);
            $(this).prop('disabled', true).css('opacity', 0.5);
        });

        // Cancel adding a block
        $(document).on('click', '#etpb-cancel-add', function () {
            $('#etpb-block-picker').slideUp(200);
            $('#etpb-add-block-btn').prop('disabled', false).css('opacity', 1);
        });

        // Category tab switching
        $(document).on('click', '.etpb-cat-tab', function () {
            var cat = $(this).data('category');
            $('.etpb-cat-tab').removeClass('active');
            $(this).addClass('active');
            if (cat === 'all') {
                $('.etpb-block-option').show();
            } else {
                $('.etpb-block-option').each(function () {
                    $(this).toggle($(this).data('category') === cat);
                });
            }
        });

        // User chose a block type — add it
        $(document).on('click', '.etpb-block-option', function () {
            var blockType = $(this).data('type');
            addBlock(blockType);
        });

        // Toggle a block's field panel open or closed
        $(document).on('click', '.etpb-btn-toggle-fields', function () {
            var $btn    = $(this);
            var $fields = $btn.closest('.etpb-block-row').find('.etpb-block-fields');
            var isOpen  = $fields.is(':visible');

            $fields.slideToggle(200);
            $btn.text(isOpen ? '✏️ Edit' : '▲ Close');
            $btn.attr('aria-expanded', isOpen ? 'false' : 'true');
        });

        // Remove a block
        $(document).on('click', '.etpb-btn-remove', function () {
            if (!confirm('Remove this section from the page?\n\nThis will not affect any content in WordPress — it only removes the section from this page layout.')) {
                return;
            }
            $(this).closest('.etpb-block-row').remove();
            reindexBlocks();
            saveToInput();
            showEmptyStateIfNeeded();
        });

        // Add a repeater item
        $(document).on('click', '.etpb-btn-add-item', function () {
            var $repeater       = $(this).closest('.etpb-repeater');
            var $itemsContainer = $repeater.find('.etpb-repeater-items');
            var subFields       = JSON.parse($repeater.attr('data-sub-fields') || '{}');
            var currentCount    = $itemsContainer.find('.etpb-repeater-item').length;

            $itemsContainer.find('.etpb-repeater-empty').remove();

            var $newItem = buildRepeaterItemHtml(subFields, currentCount);
            $itemsContainer.append($newItem);
            saveToInput();
        });

        // Remove a repeater item
        $(document).on('click', '.etpb-btn-remove-item', function () {
            var $repeater       = $(this).closest('.etpb-repeater');
            var $itemsContainer = $repeater.find('.etpb-repeater-items');
            $(this).closest('.etpb-repeater-item').remove();

            $itemsContainer.find('.etpb-repeater-item').each(function (i) {
                $(this).find('.etpb-repeater-item-num').text('Item ' + (i + 1));
            });

            if ($itemsContainer.find('.etpb-repeater-item').length === 0) {
                $itemsContainer.append('<p class="etpb-repeater-empty">No items yet. Click the button below to add one.</p>');
            }

            saveToInput();
        });

        // Toggle label update when a checkbox changes
        $(document).on('change', '.etpb-checkbox', function () {
            var $label = $(this).closest('.etpb-toggle').find('.etpb-toggle-label');
            $label.text($(this).is(':checked') ? 'Enabled' : 'Disabled');
            saveToInput();
        });

        // Save whenever any field value changes
        $(document).on('change keyup', '.etpb-field-value, .etpb-sub-field-value', function () {
            saveToInput();
        });

        // Column layout change — update column count in the builder
        $(document).on('change', '.etpb-field[data-field-key="column_layout"] .etpb-field-value', function () {
            var $block      = $(this).closest('.etpb-block-row');
            var layout      = $(this).val();
            var colCount    = (layout.match(/_/g) || []).length + 1;
            var $colsWrap   = $block.find('.etpb-layout-columns-wrap');
            var currentCols = $colsWrap.find('.etpb-layout-column').length;

            if ( colCount > currentCols ) {
                for ( var i = currentCols; i < colCount; i++ ) {
                    $colsWrap.append( buildColumnSlot(i) );
                }
            } else if ( colCount < currentCols ) {
                $colsWrap.find('.etpb-layout-column').slice(colCount).remove();
            }
            // Update labels
            $colsWrap.find('.etpb-layout-column').each(function(i) {
                $(this).find('.etpb-col-label').text('Column ' + (i + 1));
                $(this).attr('data-col-index', i);
                $(this).find('.etpb-add-nested-block-btn').attr('data-col-index', i);
            });
            saveToInput();
        });

        // Toggle nested block fields
        $(document).on('click', '.etpb-nested-btn-toggle', function () {
            var $btn    = $(this);
            var $fields = $btn.closest('.etpb-nested-block-row').find('.etpb-nested-block-fields');
            var isOpen  = $fields.is(':visible');
            $fields.slideToggle(150);
            $btn.text( isOpen ? '✏️ Edit' : '▲ Close' );
        });

        // Remove nested block
        $(document).on('click', '.etpb-nested-btn-remove', function () {
            $(this).closest('.etpb-nested-block-row').remove();
            saveToInput();
        });

        // Open nested block picker for a column
        $(document).on('click', '.etpb-add-nested-block-btn', function () {
            var $btn      = $(this);
            var colIndex  = $btn.data('col-index');
            var $block    = $btn.closest('.etpb-block-row');

            // Close any other open nested pickers
            $('.etpb-nested-picker').remove();

            // Build and show picker
            var $picker = buildNestedBlockPicker( colIndex );
            $btn.before( $picker );
            $picker.slideDown(150);
        });

        // User chose a block type in the nested picker
        $(document).on('click', '.etpb-nested-block-option', function () {
            var $picker    = $(this).closest('.etpb-nested-picker');
            var $col       = $picker.closest('.etpb-layout-column');
            var blockType  = $(this).data('type');

            $picker.slideUp(150, function() { $(this).remove(); });

            var nextIndex = $col.find('.etpb-nested-block-row').length;

            // AJAX to get fields HTML
            $.post( etpbData.ajaxUrl, {
                action:       'etpb_get_block_fields',
                nonce:        etpbData.nonce,
                block_type:   blockType,
                block_index:  nextIndex,
            }, function(response) {
                if ( response.success && response.data.html ) {
                    var $newRow = $( response.data.html );
                    // Convert the top-level block row into a nested block row
                    var $nestedRow = $('<div class="etpb-nested-block-row" data-type="' + blockType + '"></div>');
                    var $nestedHeader = $newRow.find('.etpb-block-header').clone();
                    var $nestedFields = $newRow.find('.etpb-block-fields').clone();

                    // Restyle header for nested context
                    $nestedHeader.removeClass('etpb-block-header').addClass('etpb-nested-block-header');
                    $nestedHeader.find('.etpb-btn-toggle-fields').removeClass('etpb-btn-toggle-fields').addClass('etpb-nested-btn-toggle').text('✏️ Edit');
                    $nestedHeader.find('.etpb-btn-remove').removeClass('etpb-btn-remove').addClass('etpb-nested-btn-remove').text('🗑️');
                    $nestedHeader.find('.clb-drag-handle, .etpb-drag-handle').addClass('etpb-nested-drag-handle');

                    $nestedFields.removeClass('etpb-block-fields').addClass('etpb-nested-block-fields').show();

                    $nestedRow.append($nestedHeader).append($nestedFields);
                    $col.find('.etpb-nested-blocks-list').append($nestedRow);
                    saveToInput();
                }
            });
        });

        // Cancel nested picker
        $(document).on('click', '.etpb-nested-picker-cancel', function () {
            $(this).closest('.etpb-nested-picker').slideUp(150, function() { $(this).remove(); });
        });

        // ── Background group toggle ─────────────────────────────────────────────────
        $(document).on('click', '.etpb-bg-group-toggle', function () {
            var $btn   = $(this);
            var $inner = $btn.siblings('.etpb-bg-group-inner');
            var isOpen = $inner.is(':visible');
            $inner.slideToggle(150);
            $btn.attr('aria-expanded', isOpen ? 'false' : 'true');
            $btn.find('.etpb-bg-toggle-arrow').text(isOpen ? '▼' : '▲');
        });

        // ── Background type change: show/hide conditional fields ─────────────────────
        $(document).on('change', '[data-field-key="bg_type"] .etpb-field-value, [data-field-key="bg_type"] .clb-field-value', function () {
            var val   = $(this).val();
            var $group = $(this).closest('.etpb-bg-group-inner');

            $group.find('[data-bg-field="bg_preset"]').toggle(val === 'preset');
            $group.find('[data-bg-field="bg_custom_color"]').toggle(val === 'custom');

            var imageFields = ['bg_image_url', 'bg_image_position', 'bg_image_size', 'bg_overlay_color', 'bg_overlay_opacity'];
            imageFields.forEach(function(key) {
                $group.find('[data-bg-field="' + key + '"]').toggle(val === 'image');
            });
        });

        // ── Colour picker sync: swatch ↔ hex text ────────────────────────────────────
        $(document).on('input change', '.etpb-color-swatch', function () {
            var hex = $(this).val();
            $(this).siblings('.etpb-color-hex').val(hex).trigger('change');
        });

        $(document).on('input change', '.etpb-color-hex', function () {
            var hex = $(this).val();
            if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
                $(this).siblings('.etpb-color-swatch').val(hex);
            }
            saveToInput();
        });

        // ── Media Library picker ─────────────────────────────────────────────────────
        $(document).on('click', '.etpb-media-pick-btn', function (e) {
            e.preventDefault();
            var $btn      = $(this);
            var $wrap     = $btn.closest('.etpb-media-picker-wrap');
            var $urlInput = $wrap.find('.etpb-media-url-input');
            var $preview  = $btn.closest('.etpb-field').find('.etpb-media-preview');
            var $thumb    = $preview.find('.etpb-media-thumb');

            if (typeof wp !== 'undefined' && wp.media) {
                var frame = wp.media({
                    title:    'Select Background Image',
                    button:   { text: 'Use this image' },
                    multiple: false,
                    library:  { type: 'image' },
                });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $urlInput.val(attachment.url).trigger('change');
                    $thumb.attr('src', attachment.url);
                    $preview.show();
                    saveToInput();
                });
                frame.open();
            } else {
                // Fallback: focus the URL field for manual entry
                $urlInput.focus();
            }
        });

        // ── Media preview clear ──────────────────────────────────────────────────────
        $(document).on('click', '.etpb-media-clear-btn', function () {
            var $field    = $(this).closest('.etpb-field');
            var $urlInput = $field.find('.etpb-media-url-input');
            var $preview  = $field.find('.etpb-media-preview');
            $urlInput.val('').trigger('change');
            $preview.hide().find('.etpb-media-thumb').attr('src', '');
            saveToInput();
        });

        // ── Media URL typed manually: update preview ─────────────────────────────────
        $(document).on('change', '.etpb-media-url-input', function () {
            var url      = $(this).val().trim();
            var $preview = $(this).closest('.etpb-field').find('.etpb-media-preview');
            var $thumb   = $preview.find('.etpb-media-thumb');
            if (url) {
                $thumb.attr('src', url);
                $preview.show();
            } else {
                $preview.hide();
            }
            saveToInput();
        });
    }

    // ── Add a new block ─────────────────────────────────────────────────────────

    function addBlock(blockType) {
        $('#etpb-block-picker').slideUp(200);
        $('#etpb-add-block-btn').prop('disabled', false).css('opacity', 1);

        $('#etpb-empty-state').remove();

        var nextIndex = $('#etpb-blocks-list .etpb-block-row').length;

        var $placeholder = $('<div class="etpb-block-row etpb-loading-row" style="padding:16px;color:#757575;">' +
            '⏳ Adding section…</div>');
        $('#etpb-blocks-list').append($placeholder);

        $.post(etpbData.ajaxUrl, {
            action:      'etpb_get_block_fields',
            nonce:       etpbData.nonce,
            block_type:  blockType,
            block_index: nextIndex,
        }, function (response) {
            $placeholder.remove();

            if (response.success && response.data.html) {
                var $newRow = $(response.data.html);
                $('#etpb-blocks-list').append($newRow);

                $newRow.find('.etpb-block-fields').show();
                $newRow.find('.etpb-btn-toggle-fields').text('▲ Close').attr('aria-expanded', 'true');

                $('html, body').animate({
                    scrollTop: $newRow.offset().top - 80
                }, 300);

                initSortable('#etpb-blocks-list');
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
            handle:      '.etpb-drag-handle',
            placeholder: 'etpb-block-row-placeholder',
            tolerance:   'pointer',
            update: function () {
                reindexBlocks();
                saveToInput();
            }
        });
    }

    function reindexBlocks() {
        $('#etpb-blocks-list .etpb-block-row').each(function (newIndex) {
            $(this).attr('data-index', newIndex);
        });
    }

    // ── Collect data from DOM and save to hidden input ─────────────────────────

    function saveToInput() {
        var blocks = [];

        $('#etpb-blocks-list .etpb-block-row').each(function () {
            var blockType = $(this).data('type');
            var fields    = {};

            // Collect simple (non-repeater) fields
            $(this).find('.etpb-field').each(function () {
                var fieldKey = $(this).data('field-key');
                var $value   = $(this).find('> .etpb-field-value, > label.etpb-toggle .etpb-field-value, > .etpb-color-picker-wrap .etpb-field-value, > .etpb-media-picker-wrap .etpb-field-value');

                if ($value.length === 0) {
                    return; // Repeater — handled below
                }

                if ($value.attr('type') === 'checkbox') {
                    fields[fieldKey] = $value.is(':checked');
                } else {
                    fields[fieldKey] = $value.val() || '';
                }
            });

            // Collect repeater fields
            $(this).find('.etpb-repeater').each(function () {
                var fieldKey = $(this).data('field-key');
                var items    = [];

                $(this).find('.etpb-repeater-item').each(function () {
                    var item = {};
                    $(this).find('.etpb-sub-field').each(function () {
                        var subKey = $(this).data('sub-key');
                        item[subKey] = $(this).find('.etpb-sub-field-value').val() || '';
                    });
                    items.push(item);
                });

                fields[fieldKey] = items;
            });

            // Collect nested layout column data
            if (blockType === 'layout_section') {
                var columnsData = [];
                $(this).find('.etpb-layout-column').each(function () {
                    var colBlocks = [];
                    $(this).find('.etpb-nested-block-row').each(function () {
                        var nestedType   = $(this).data('type');
                        var nestedFields = {};
                        $(this).find('.etpb-nested-block-fields .etpb-field, .etpb-nested-block-fields .clb-field').each(function () {
                            var fKey   = $(this).data('field-key');
                            var $val   = $(this).find('> .etpb-field-value, > .clb-field-value, > label .etpb-field-value, > label .clb-field-value');
                            if ($val.length === 0) return;
                            if ($val.attr('type') === 'checkbox') {
                                nestedFields[fKey] = $val.is(':checked');
                            } else {
                                nestedFields[fKey] = $val.val() || '';
                            }
                        });
                        // Collect nested repeaters
                        $(this).find('.etpb-nested-block-fields .etpb-repeater, .etpb-nested-block-fields .clb-repeater').each(function () {
                            var rKey  = $(this).data('field-key');
                            var items = [];
                            $(this).find('.etpb-repeater-item, .clb-repeater-item').each(function () {
                                var item = {};
                                $(this).find('.etpb-sub-field, .clb-sub-field').each(function () {
                                    var sKey = $(this).data('sub-key');
                                    item[sKey] = $(this).find('.etpb-sub-field-value, .clb-sub-field-value').val() || '';
                                });
                                items.push(item);
                            });
                            nestedFields[rKey] = items;
                        });
                        colBlocks.push({type: nestedType, fields: nestedFields});
                    });
                    columnsData.push({blocks: colBlocks});
                });
                fields['columns'] = columnsData;
            }

            blocks.push({
                type:   blockType,
                fields: fields,
            });
        });

        $('#etpb-blocks-config').val(JSON.stringify(blocks));
    }

    // ── Utility functions ───────────────────────────────────────────────────────

    function showEmptyStateIfNeeded() {
        if ($('#etpb-blocks-list .etpb-block-row').length === 0) {
            $('#etpb-blocks-list').html(
                '<div class="etpb-empty-state" id="etpb-empty-state">' +
                '<div class="etpb-empty-icon">📄</div>' +
                '<p><strong>No sections added yet.</strong></p>' +
                '<p>Click <strong>"＋ Add a Section"</strong> below to start building this page.</p>' +
                '</div>'
            );
        }
    }

    function buildColumnSlot(index) {
        return $('<div class="etpb-layout-column" data-col-index="' + index + '">' +
            '<div class="etpb-layout-col-header"><span class="etpb-col-label">Column ' + (index + 1) + '</span></div>' +
            '<div class="etpb-nested-blocks-list"></div>' +
            '<button type="button" class="etpb-add-nested-block-btn" data-col-index="' + index + '">＋ Add block to this column</button>' +
            '</div>');
    }

    function buildNestedBlockPicker(colIndex) {
        var definitions = etpbData.blockDefinitions;
        var $picker = $('<div class="etpb-nested-picker"></div>');
        $picker.append('<h4>Add a block to Column ' + (colIndex + 1) + '</h4>');

        // Category tabs
        var cats = ['All', 'Course', 'General', 'Media', 'Social Proof'];
        var $tabs = $('<div class="etpb-nested-tabs"></div>');
        cats.forEach(function(cat) {
            $tabs.append('<button type="button" class="etpb-nested-cat-tab ' + (cat === 'All' ? 'active' : '') + '" data-category="' + cat + '">' + cat + '</button>');
        });
        $picker.append($tabs);

        // Block grid
        var $grid = $('<div class="etpb-nested-picker-grid"></div>');
        $.each(definitions, function(type, def) {
            var $option = $('<div class="etpb-nested-block-option" data-type="' + type + '" data-category="' + def.category + '">' +
                '<span class="etpb-nested-option-icon">' + def.icon + '</span>' +
                '<span class="etpb-nested-option-label">' + def.label + '</span>' +
                '</div>');
            $grid.append($option);
        });
        $picker.append($grid);
        $picker.append('<button type="button" class="etpb-nested-picker-cancel">✕ Cancel</button>');
        $picker.hide();

        // Category tab switching within picker
        $picker.on('click', '.etpb-nested-cat-tab', function() {
            var cat = $(this).data('category');
            $picker.find('.etpb-nested-cat-tab').removeClass('active');
            $(this).addClass('active');
            if (cat === 'All') {
                $picker.find('.etpb-nested-block-option').show();
            } else {
                $picker.find('.etpb-nested-block-option').each(function() {
                    $(this).toggle($(this).data('category') === cat);
                });
            }
        });

        return $picker;
    }

    /**
     * Build the HTML for a new empty repeater item.
     */
    function buildRepeaterItemHtml(subFields, index) {
        var $item = $('<div class="etpb-repeater-item"></div>');

        $item.append(
            '<div class="etpb-repeater-item-header">' +
            '<span class="etpb-drag-handle" title="Drag to reorder">⠿</span>' +
            '<span class="etpb-repeater-item-num">Item ' + (index + 1) + '</span>' +
            '<button type="button" class="etpb-btn-remove-item">✕ Remove</button>' +
            '</div>'
        );

        $.each(subFields, function (subKey, subField) {
            var $subField = $('<div class="etpb-sub-field" data-sub-key="' + subKey + '"></div>');
            $subField.append('<label>' + subField.label + '</label>');

            if (subField.type === 'icon_picker') {
                $subField.append(
                    '<input type="text" class="etpb-input etpb-sub-field-value" ' +
                    'placeholder="e.g. fas fa-check-circle" value="" />' +
                    '<small style="color:#757575;font-size:11px;">Type a Font Awesome icon class, or leave blank for no icon.</small>'
                );
            } else if (subField.type === 'textarea') {
                $subField.append(
                    '<textarea class="etpb-textarea etpb-sub-field-value" rows="3"></textarea>'
                );
            } else {
                $subField.append(
                    '<input type="text" class="etpb-input etpb-sub-field-value" value="" />'
                );
            }

            $item.append($subField);
        });

        return $item;
    }

})(jQuery);

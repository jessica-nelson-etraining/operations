/**
 * Core Blocks — Admin JavaScript
 * Handles block picker, field toggling, repeaters, drag-and-drop, and JSON saving.
 * Uses cb- prefixed IDs and classes to avoid conflicts with Course Landing Blocks.
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        bindEvents();
        initSortable('#cb-blocks-list');
    });

    function bindEvents() {

        // Show block picker
        $(document).on('click', '#cb-add-block-btn', function () {
            $('#cb-block-picker').slideDown(200);
            $(this).prop('disabled', true).css('opacity', 0.5);
        });

        // Cancel adding a block
        $(document).on('click', '#cb-cancel-add', function () {
            $('#cb-block-picker').slideUp(200);
            $('#cb-add-block-btn').prop('disabled', false).css('opacity', 1);
        });

        // User chose a block type
        $(document).on('click', '.cb-block-option', function () {
            addBlock($(this).data('type'));
        });

        // Toggle fields open/closed
        $(document).on('click', '.cb-btn-toggle-fields', function () {
            var $btn    = $(this);
            var $fields = $btn.closest('.cb-block-row').find('.cb-block-fields');
            var isOpen  = $fields.is(':visible');
            $fields.slideToggle(200);
            $btn.text(isOpen ? '✏️ Edit' : '▲ Close');
        });

        // Remove a block
        $(document).on('click', '.cb-btn-remove', function () {
            if (!confirm('Remove this section from the page?')) return;
            $(this).closest('.cb-block-row').remove();
            reindexBlocks();
            saveToInput();
            showEmptyStateIfNeeded();
        });

        // Add repeater item
        $(document).on('click', '.cb-btn-add-item', function () {
            var $repeater       = $(this).closest('.cb-repeater');
            var $itemsContainer = $repeater.find('.cb-repeater-items');
            var subFields       = JSON.parse($repeater.attr('data-sub-fields') || '{}');
            var currentCount    = $itemsContainer.find('.cb-repeater-item').length;
            $itemsContainer.find('.cb-repeater-empty').remove();
            $itemsContainer.append(buildRepeaterItemHtml(subFields, currentCount));
            saveToInput();
        });

        // Remove repeater item
        $(document).on('click', '.cb-btn-remove-item', function () {
            var $repeater       = $(this).closest('.cb-repeater');
            var $itemsContainer = $repeater.find('.cb-repeater-items');
            $(this).closest('.cb-repeater-item').remove();
            $itemsContainer.find('.cb-repeater-item').each(function (i) {
                $(this).find('.cb-repeater-item-num').text('Item ' + (i + 1));
            });
            if ($itemsContainer.find('.cb-repeater-item').length === 0) {
                $itemsContainer.append('<p class="cb-repeater-empty">No items yet. Click the button below to add one.</p>');
            }
            saveToInput();
        });

        // Save on field change
        $(document).on('change keyup', '.cb-field-value, .cb-sub-field-value', function () {
            saveToInput();
        });
    }

    function addBlock(blockType) {
        $('#cb-block-picker').slideUp(200);
        $('#cb-add-block-btn').prop('disabled', false).css('opacity', 1);
        $('#cb-empty-state').remove();

        var nextIndex = $('#cb-blocks-list .cb-block-row').length;
        var $placeholder = $('<div class="cb-block-row" style="padding:16px;color:#757575;">⏳ Adding section…</div>');
        $('#cb-blocks-list').append($placeholder);

        $.post(cbData.ajaxUrl, {
            action:      'cb_get_block_fields',
            nonce:       cbData.nonce,
            block_type:  blockType,
            block_index: nextIndex,
        }, function (response) {
            $placeholder.remove();
            if (response.success && response.data.html) {
                var $newRow = $(response.data.html);
                $('#cb-blocks-list').append($newRow);
                $newRow.find('.cb-block-fields').show();
                $newRow.find('.cb-btn-toggle-fields').text('▲ Close');
                $('html, body').animate({ scrollTop: $newRow.offset().top - 80 }, 300);
                initSortable('#cb-blocks-list');
                saveToInput();
            } else {
                alert('Something went wrong. Please try again.');
            }
        });
    }

    function initSortable(selector) {
        $(selector).sortable({
            handle:      '.cb-drag-handle',
            placeholder: 'cb-block-row-placeholder',
            tolerance:   'pointer',
            update: function () {
                reindexBlocks();
                saveToInput();
            }
        });
    }

    function reindexBlocks() {
        $('#cb-blocks-list .cb-block-row').each(function (i) {
            $(this).attr('data-index', i);
        });
    }

    function saveToInput() {
        var blocks = [];
        $('#cb-blocks-list .cb-block-row').each(function () {
            var blockType = $(this).data('type');
            var fields    = {};

            $(this).find('.cb-field').each(function () {
                var fieldKey = $(this).data('field-key');
                var $value   = $(this).find('> .cb-field-value, > label.cb-toggle .cb-field-value');
                if ($value.length === 0) return;
                fields[fieldKey] = $value.attr('type') === 'checkbox' ? $value.is(':checked') : ($value.val() || '');
            });

            $(this).find('.cb-repeater').each(function () {
                var fieldKey = $(this).data('field-key');
                var items    = [];
                $(this).find('.cb-repeater-item').each(function () {
                    var item = {};
                    $(this).find('.cb-sub-field').each(function () {
                        item[$(this).data('sub-key')] = $(this).find('.cb-sub-field-value').val() || '';
                    });
                    items.push(item);
                });
                fields[fieldKey] = items;
            });

            blocks.push({ type: blockType, fields: fields });
        });
        var json = JSON.stringify(blocks);
        $('#cb-blocks-config').val(json);
        console.log('[Core Blocks] Saved ' + blocks.length + ' block(s) to hidden field:', json.substring(0, 100) + '...');
    }

    function showEmptyStateIfNeeded() {
        if ($('#cb-blocks-list .cb-block-row').length === 0) {
            $('#cb-blocks-list').html(
                '<div class="cb-empty-state" id="cb-empty-state">' +
                '<div class="cb-empty-icon">📄</div>' +
                '<p><strong>No sections added yet.</strong></p>' +
                '<p>Click <strong>"＋ Add a Section"</strong> below to get started.</p>' +
                '</div>'
            );
        }
    }

    function buildRepeaterItemHtml(subFields, index) {
        var $item = $('<div class="cb-repeater-item"></div>');
        $item.append(
            '<div class="cb-repeater-item-header">' +
            '<span class="cb-drag-handle">⠿</span>' +
            '<span class="cb-repeater-item-num">Item ' + (index + 1) + '</span>' +
            '<button type="button" class="cb-btn-remove-item">✕ Remove</button>' +
            '</div>'
        );
        $.each(subFields, function (subKey, subField) {
            var $sf = $('<div class="cb-sub-field" data-sub-key="' + subKey + '"></div>');
            $sf.append('<label>' + subField.label + '</label>');
            $sf.append('<input type="text" class="cb-input cb-sub-field-value" value="" />');
            $item.append($sf);
        });
        return $item;
    }

})(jQuery);

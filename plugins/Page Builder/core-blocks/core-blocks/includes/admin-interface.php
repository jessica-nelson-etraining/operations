<?php
/**
 * admin-interface.php — Core Blocks
 *
 * WordPress admin panel for the Core Blocks page builder.
 * Adds a meta box to all page edit screens.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the meta box.
 */
function cb_add_meta_box() {
    add_meta_box(
        'cb_page_builder',
        '🧩 Page Builder — General Blocks',
        'cb_render_meta_box',
        'page',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'cb_add_meta_box' );

/**
 * Render the meta box content.
 */
function cb_render_meta_box( $post ) {
    $blocks      = cb_get_page_blocks( $post->ID );
    $definitions = cb_get_block_definitions();

    wp_nonce_field( 'cb_save_blocks', 'cb_nonce' );
    ?>

    <div id="cb-editor" class="cb-editor">

        <div class="cb-intro">
            <strong>Page Builder — General Blocks</strong><br>
            Add general-purpose sections to this page. These blocks work on any page type.
            Drag <strong>⠿</strong> to reorder, click <strong>✏️ Edit</strong> to configure,
            and click <strong>Update</strong> to save.
        </div>

        <!-- Block list -->
        <div class="cb-blocks-list" id="cb-blocks-list">
            <?php if ( empty( $blocks ) ) : ?>
                <div class="cb-empty-state" id="cb-empty-state">
                    <div class="cb-empty-icon">📄</div>
                    <p><strong>No sections added yet.</strong></p>
                    <p>Click <strong>"＋ Add a Section"</strong> below to get started.</p>
                </div>
            <?php else : ?>
                <?php foreach ( $blocks as $index => $block ) : ?>
                    <?php cb_render_block_editor_row( $index, $block, $definitions ); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Add Section button -->
        <div class="cb-add-block-wrap">
            <button type="button" class="cb-btn-add" id="cb-add-block-btn">
                ＋ Add a Section
            </button>
        </div>

        <!-- Block type picker -->
        <div class="cb-block-picker" id="cb-block-picker" style="display:none;">
            <h3>Choose a Section Type</h3>
            <p class="cb-picker-sub">Click a type to add it to the page.</p>
            <div class="cb-block-picker-grid">
                <?php foreach ( $definitions as $type => $def ) : ?>
                    <div class="cb-block-option" data-type="<?php echo esc_attr( $type ); ?>">
                        <div class="cb-block-option-icon"><?php echo $def['icon']; ?></div>
                        <div class="cb-block-option-label"><?php echo esc_html( $def['label'] ); ?></div>
                        <div class="cb-block-option-desc"><?php echo esc_html( $def['description'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="cb-btn-cancel" id="cb-cancel-add">✕ Cancel</button>
        </div>

        <!-- Hidden config field -->
        <input type="hidden"
               name="cb_blocks_config"
               id="cb-blocks-config"
               value="<?php echo esc_attr( json_encode( $blocks ) ); ?>" />

        <!-- ── Admin diagnostic panel ── -->
        <!-- Only visible to admins. Shows save status so we can confirm blocks are saving. -->
        <div style="margin-top:20px; padding:12px 16px; background:#f9f9f9; border:1px solid #dcdcde; border-radius:4px; font-size:12px; color:#757575;">
            <strong style="color:#1d2327;">🔍 Save Status (only visible to admins)</strong><br><br>
            <?php
            $saved_raw = get_post_meta( $post->ID, 'cb_blocks_config', true );
            $saved_blocks = json_decode( $saved_raw, true );
            $block_count  = is_array( $saved_blocks ) ? count( $saved_blocks ) : 0;

            if ( empty( $saved_raw ) ) :
                echo '<span style="color:#b32d2e;">⚠️ No blocks saved yet. Add sections above and click <strong>Update</strong>.</span>';
            else :
                echo '<span style="color:#00a32a;">✅ ' . $block_count . ' block(s) saved successfully.</span>';
                echo '<br><small>Block types: ';
                if ( is_array( $saved_blocks ) ) {
                    echo implode( ', ', array_column( $saved_blocks, 'type' ) );
                }
                echo '</small>';
            endif;

            // Check if our template would be used
            $clb_raw = get_post_meta( $post->ID, 'clb_blocks_config', true );
            echo '<br><br>';
            if ( ! empty( $saved_raw ) && empty( $clb_raw ) ) {
                echo '<span style="color:#00a32a;">✅ Core Blocks template is active on this page.</span>';
            } elseif ( ! empty( $clb_raw ) ) {
                echo '<span style="color:#996800;">⚠️ Course Landing Blocks is also active — it takes priority on this page.</span>';
            } else {
                echo '<span style="color:#b32d2e;">⚠️ Template not yet active — save blocks first.</span>';
            }
            ?>
        </div>

    </div>
    <?php
}

/**
 * Render a single block row in the editor.
 */
function cb_render_block_editor_row( $index, $block, $definitions ) {
    $type = $block['type'] ?? '';
    $def  = $definitions[ $type ] ?? null;
    if ( ! $def ) return;
    ?>
    <div class="cb-block-row" data-index="<?php echo (int) $index; ?>" data-type="<?php echo esc_attr( $type ); ?>">
        <div class="cb-block-header">
            <span class="cb-drag-handle" title="Drag to reorder">⠿</span>
            <span class="cb-block-icon"><?php echo $def['icon']; ?></span>
            <span class="cb-block-title"><?php echo esc_html( $def['label'] ); ?></span>
            <div class="cb-block-actions">
                <button type="button" class="cb-btn-toggle-fields">✏️ Edit</button>
                <button type="button" class="cb-btn-remove">🗑️ Remove</button>
            </div>
        </div>
        <div class="cb-block-fields" style="display:none;">
            <p class="cb-fields-desc"><?php echo esc_html( $def['description'] ); ?></p>
            <?php foreach ( $def['fields'] as $field_key => $field ) : ?>
                <?php
                $saved_value = $block['fields'][ $field_key ] ?? null;
                cb_render_field( $index, $field_key, $field, $saved_value );
                ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Render a single field.
 */
function cb_render_field( $block_index, $field_key, $field, $value ) {
    $field_id = 'cb_block_' . $block_index . '_' . $field_key;
    $type     = $field['type'] ?? 'text';
    ?>
    <div class="cb-field" data-field-key="<?php echo esc_attr( $field_key ); ?>">
        <label class="cb-field-label" for="<?php echo esc_attr( $field_id ); ?>">
            <?php echo esc_html( $field['label'] ); ?>
        </label>
        <?php if ( ! empty( $field['description'] ) ) : ?>
            <p class="cb-field-desc"><?php echo esc_html( $field['description'] ); ?></p>
        <?php endif; ?>
        <?php
        $display_value = $value ?? $field['default'] ?? '';

        switch ( $type ) :
            case 'text': ?>
                <input type="text" id="<?php echo esc_attr( $field_id ); ?>"
                       class="cb-input cb-field-value"
                       value="<?php echo esc_attr( $display_value ); ?>" />
                <?php break;

            case 'textarea': ?>
                <textarea id="<?php echo esc_attr( $field_id ); ?>"
                          class="cb-textarea cb-field-value"
                          rows="3"><?php echo esc_textarea( $display_value ); ?></textarea>
                <?php break;

            case 'select': ?>
                <select id="<?php echo esc_attr( $field_id ); ?>"
                        class="cb-select cb-field-value">
                    <?php foreach ( $field['options'] as $opt_val => $opt_label ) : ?>
                        <option value="<?php echo esc_attr( $opt_val ); ?>"
                            <?php selected( $display_value, $opt_val ); ?>>
                            <?php echo esc_html( $opt_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php break;

            case 'icon_picker': ?>
                <select id="<?php echo esc_attr( $field_id ); ?>"
                        class="cb-select cb-field-value">
                    <?php foreach ( cb_get_available_icons() as $icon_val => $icon_label ) : ?>
                        <option value="<?php echo esc_attr( $icon_val ); ?>"
                            <?php selected( $display_value, $icon_val ); ?>>
                            <?php echo esc_html( $icon_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php break;

            case 'repeater':
                $items = is_array( $value ) ? $value : array();
                ?>
                <div class="cb-repeater"
                     data-field-key="<?php echo esc_attr( $field_key ); ?>"
                     data-sub-fields="<?php echo esc_attr( json_encode( $field['sub_fields'] ) ); ?>">
                    <div class="cb-repeater-items">
                        <?php foreach ( $items as $item_index => $item_data ) : ?>
                            <?php cb_render_repeater_item( $field['sub_fields'], $item_index, $item_data ); ?>
                        <?php endforeach; ?>
                        <?php if ( empty( $items ) ) : ?>
                            <p class="cb-repeater-empty">No items yet. Click the button below to add one.</p>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="cb-btn-add-item">＋ Add Item</button>
                </div>
                <?php break;

        endswitch;
        ?>
    </div>
    <?php
}

/**
 * Render a repeater item.
 */
function cb_render_repeater_item( $sub_fields, $index, $values = array() ) {
    ?>
    <div class="cb-repeater-item">
        <div class="cb-repeater-item-header">
            <span class="cb-drag-handle">⠿</span>
            <span class="cb-repeater-item-num">Item <?php echo (int) $index + 1; ?></span>
            <button type="button" class="cb-btn-remove-item">✕ Remove</button>
        </div>
        <?php foreach ( $sub_fields as $sub_key => $sub_field ) : ?>
            <div class="cb-sub-field" data-sub-key="<?php echo esc_attr( $sub_key ); ?>">
                <label><?php echo esc_html( $sub_field['label'] ); ?></label>
                <?php if ( $sub_field['type'] === 'icon_picker' ) : ?>
                    <select class="cb-select cb-sub-field-value">
                        <?php foreach ( cb_get_available_icons() as $icon_val => $icon_label ) : ?>
                            <option value="<?php echo esc_attr( $icon_val ); ?>"
                                <?php selected( $values[ $sub_key ] ?? '', $icon_val ); ?>>
                                <?php echo esc_html( $icon_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="text"
                           class="cb-input cb-sub-field-value"
                           value="<?php echo esc_attr( $values[ $sub_key ] ?? '' ); ?>" />
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Save block configuration on page save.
 */
function cb_save_blocks( $post_id ) {
    if ( ! isset( $_POST['cb_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['cb_nonce'], 'cb_save_blocks' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( 'page' !== get_post_type( $post_id ) ) return;

    if ( isset( $_POST['cb_blocks_config'] ) ) {
        $raw     = wp_unslash( $_POST['cb_blocks_config'] );
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            update_post_meta( $post_id, 'cb_blocks_config', wp_slash( $raw ) );
        }
    }
}
add_action( 'save_post', 'cb_save_blocks' );

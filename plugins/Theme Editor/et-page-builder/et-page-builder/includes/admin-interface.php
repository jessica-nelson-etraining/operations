<?php
/**
 * admin-interface.php — eTraining Page Builder
 *
 * Everything related to the WordPress admin editor panel.
 * This file:
 *  - Adds the "eTraining Page Builder" meta box to the page editor
 *  - Renders the block list, category tabs, "Add a Section" picker, and all field inputs
 *  - Saves the block configuration when the page is saved
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the meta box that appears on all page edit screens.
 */
function etpb_add_meta_box() {
    add_meta_box(
        'etpb_block_editor',
        '🧱 eTraining Page Builder',
        'etpb_render_meta_box',
        'page',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'etpb_add_meta_box' );

/**
 * Render the entire meta box content.
 */
function etpb_render_meta_box( $post ) {
    $blocks      = etpb_get_page_blocks( $post->ID );
    $definitions = etpb_get_block_definitions();

    // Security nonce
    wp_nonce_field( 'etpb_save_blocks', 'etpb_nonce' );

    ?>

    <div id="etpb-editor" class="etpb-editor">

        <div class="etpb-intro">
            <strong>eTraining Page Builder</strong><br>
            Use the sections below to build this page.
            Add sections using the button below, drag the <strong>⠿</strong> handle to reorder them,
            and click <strong>✏️ Edit</strong> to configure each one.
            Click <strong>Update</strong> in the top right to save your changes.
        </div>

        <!-- ── Active blocks list ── -->
        <div class="etpb-blocks-list" id="etpb-blocks-list">
            <?php if ( empty( $blocks ) ) : ?>
                <div class="etpb-empty-state" id="etpb-empty-state">
                    <div class="etpb-empty-icon">📄</div>
                    <p><strong>No sections added yet.</strong></p>
                    <p>Click <strong>"＋ Add a Section"</strong> below to start building this page.</p>
                </div>
            <?php else : ?>
                <?php foreach ( $blocks as $index => $block ) : ?>
                    <?php etpb_render_block_editor_row( $index, $block, $definitions ); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ── Add Section button ── -->
        <div class="etpb-add-block-wrap">
            <button type="button" class="etpb-btn etpb-btn-add" id="etpb-add-block-btn">
                ＋ Add a Section
            </button>
        </div>

        <!-- ── Block type picker (hidden until "Add a Section" is clicked) ── -->
        <div class="etpb-block-picker" id="etpb-block-picker" style="display:none;">
            <h3>Choose a Section Type</h3>
            <p class="etpb-picker-sub">Filter by category, then click a section type to add it.</p>

            <!-- Category filter tabs -->
            <div class="etpb-category-tabs">
                <button type="button" class="etpb-cat-tab active" data-category="all">All</button>
                <button type="button" class="etpb-cat-tab" data-category="Course">Course</button>
                <button type="button" class="etpb-cat-tab" data-category="General">General</button>
                <button type="button" class="etpb-cat-tab" data-category="Media">Media</button>
                <button type="button" class="etpb-cat-tab" data-category="Social Proof">Social Proof</button>
            </div>

            <div class="etpb-block-picker-grid">
                <?php foreach ( $definitions as $type => $def ) : ?>
                    <div class="etpb-block-option"
                         data-type="<?php echo esc_attr( $type ); ?>"
                         data-category="<?php echo esc_attr( $def['category'] ); ?>">
                        <div class="etpb-block-option-icon"><?php echo $def['icon']; ?></div>
                        <div class="etpb-block-option-label"><?php echo esc_html( $def['label'] ); ?></div>
                        <div class="etpb-block-option-desc"><?php echo esc_html( $def['description'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="etpb-btn etpb-btn-cancel" id="etpb-cancel-add">
                ✕ Cancel
            </button>
        </div>

        <!-- ── Hidden field: stores all block data as JSON ── -->
        <input type="hidden"
               name="etpb_blocks_config"
               id="etpb-blocks-config"
               value="<?php echo esc_attr( json_encode( $blocks ) ); ?>" />

    </div><!-- /#etpb-editor -->
    <?php
}

/**
 * Render a single block row in the editor.
 */
function etpb_render_block_editor_row( $index, $block, $definitions ) {
    $type = $block['type'] ?? '';
    $def  = $definitions[ $type ] ?? null;
    if ( ! $def ) return;
    ?>
    <div class="etpb-block-row" data-index="<?php echo (int) $index; ?>" data-type="<?php echo esc_attr( $type ); ?>">

        <div class="etpb-block-header">
            <span class="etpb-drag-handle" title="Drag to reorder this section">⠿</span>
            <span class="etpb-block-icon"><?php echo $def['icon']; ?></span>
            <span class="etpb-block-title"><?php echo esc_html( $def['label'] ); ?></span>
            <div class="etpb-block-actions">
                <button type="button" class="etpb-btn-toggle-fields" aria-expanded="false">
                    ✏️ Edit
                </button>
                <button type="button" class="etpb-btn-remove" title="Remove this section">
                    🗑️ Remove
                </button>
            </div>
        </div>

        <div class="etpb-block-fields" style="display:none;" aria-hidden="true">
            <p class="etpb-fields-desc"><?php echo esc_html( $def['description'] ); ?></p>

            <?php
            $bg_field_keys = array( 'bg_type', 'bg_preset', 'bg_custom_color', 'bg_image_url', 'bg_image_position', 'bg_image_size', 'bg_overlay_color', 'bg_overlay_opacity' );
            $regular_fields = array();
            $bg_fields_found = array();

            foreach ( $def['fields'] as $field_key => $field ) {
                if ( in_array( $field_key, $bg_field_keys ) ) {
                    $bg_fields_found[ $field_key ] = $field;
                } else {
                    $regular_fields[ $field_key ] = $field;
                }
            }

            // Render regular fields
            foreach ( $regular_fields as $field_key => $field ) :
                $saved_value = $block['fields'][ $field_key ] ?? null;
                etpb_render_field( $index, $field_key, $field, $saved_value );
            endforeach;

            // Render background group (if this block has bg fields)
            if ( ! empty( $bg_fields_found ) ) :
                $saved_bg_type = $block['fields']['bg_type'] ?? 'preset';
                ?>
                <div class="etpb-bg-group">
                    <button type="button" class="etpb-bg-group-toggle" aria-expanded="false">
                        🎨 Background Settings <span class="etpb-bg-toggle-arrow">▼</span>
                    </button>
                    <div class="etpb-bg-group-inner" style="display:none;">
                        <?php foreach ( $bg_fields_found as $field_key => $field ) :
                            $saved_value = $block['fields'][ $field_key ] ?? null;
                            $show_style  = '';
                            // Show/hide based on bg_type
                            if ( $field_key === 'bg_preset' ) {
                                $show_style = $saved_bg_type !== 'preset' ? 'display:none;' : '';
                            } elseif ( $field_key === 'bg_custom_color' ) {
                                $show_style = $saved_bg_type !== 'custom' ? 'display:none;' : '';
                            } elseif ( in_array( $field_key, array( 'bg_image_url', 'bg_image_position', 'bg_image_size', 'bg_overlay_color', 'bg_overlay_opacity' ) ) ) {
                                $show_style = $saved_bg_type !== 'image' ? 'display:none;' : '';
                            }
                            ?>
                            <div class="etpb-bg-field-wrap" data-bg-field="<?php echo esc_attr( $field_key ); ?>" style="<?php echo $show_style; ?>">
                                <?php etpb_render_field( $index, $field_key, $field, $saved_value ); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
    <?php
}

/**
 * Render a single field inside a block's edit area.
 */
function etpb_render_field( $block_index, $field_key, $field, $value ) {
    $field_id = 'etpb_block_' . $block_index . '_' . $field_key;
    $type     = $field['type'] ?? 'text';
    ?>
    <div class="etpb-field" data-field-key="<?php echo esc_attr( $field_key ); ?>">

        <label class="etpb-field-label" for="<?php echo esc_attr( $field_id ); ?>">
            <?php echo esc_html( $field['label'] ); ?>
        </label>

        <?php if ( ! empty( $field['description'] ) ) : ?>
            <p class="etpb-field-desc"><?php echo esc_html( $field['description'] ); ?></p>
        <?php endif; ?>

        <?php
        $display_value = $value ?? $field['default'] ?? '';

        switch ( $type ) :

            case 'text': ?>
                <input type="text"
                       id="<?php echo esc_attr( $field_id ); ?>"
                       class="etpb-input etpb-field-value"
                       value="<?php echo esc_attr( $display_value ); ?>" />
                <?php break;

            case 'textarea': ?>
                <textarea id="<?php echo esc_attr( $field_id ); ?>"
                          class="etpb-textarea etpb-field-value"
                          rows="3"><?php echo esc_textarea( $display_value ); ?></textarea>
                <?php break;

            case 'select': ?>
                <select id="<?php echo esc_attr( $field_id ); ?>"
                        class="etpb-select etpb-field-value">
                    <?php foreach ( $field['options'] as $opt_val => $opt_label ) : ?>
                        <option value="<?php echo esc_attr( $opt_val ); ?>"
                            <?php selected( $display_value, $opt_val ); ?>>
                            <?php echo esc_html( $opt_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php break;

            case 'toggle':
                $is_checked = isset( $value ) ? (bool) $value : (bool) ( $field['default'] ?? false );
                ?>
                <label class="etpb-toggle">
                    <input type="checkbox"
                           class="etpb-checkbox etpb-field-value"
                           <?php checked( $is_checked ); ?> />
                    <span class="etpb-toggle-slider"></span>
                    <span class="etpb-toggle-label">
                        <?php echo $is_checked ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </label>
                <?php break;

            case 'icon_picker': ?>
                <select id="<?php echo esc_attr( $field_id ); ?>"
                        class="etpb-select etpb-field-value">
                    <?php foreach ( etpb_get_available_icons() as $icon_val => $icon_label ) : ?>
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
                <div class="etpb-repeater"
                     data-field-key="<?php echo esc_attr( $field_key ); ?>"
                     data-sub-fields="<?php echo esc_attr( json_encode( $field['sub_fields'] ) ); ?>">

                    <div class="etpb-repeater-items">
                        <?php foreach ( $items as $item_index => $item_data ) : ?>
                            <?php etpb_render_repeater_item( $field['sub_fields'], $item_index, $item_data ); ?>
                        <?php endforeach; ?>
                        <?php if ( empty( $items ) ) : ?>
                            <p class="etpb-repeater-empty">No items yet. Click the button below to add one.</p>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="etpb-btn-add-item">
                        ＋ Add Item
                    </button>
                </div>
                <?php break;

            case 'color_picker':
                $hex_val = esc_attr( $display_value ?: '#ffffff' );
                ?>
                <div class="etpb-color-picker-wrap">
                    <input type="color"
                           class="etpb-color-swatch"
                           value="<?php echo $hex_val; ?>"
                           aria-label="Color swatch" />
                    <input type="text"
                           id="<?php echo esc_attr( $field_id ); ?>"
                           class="clb-input etpb-color-hex etpb-field-value clb-field-value"
                           value="<?php echo $hex_val; ?>"
                           placeholder="#000000"
                           maxlength="7" />
                </div>
                <?php
                break;

            case 'media_picker':
                ?>
                <div class="etpb-media-picker-wrap">
                    <input type="text"
                           id="<?php echo esc_attr( $field_id ); ?>"
                           class="clb-input etpb-field-value clb-field-value etpb-media-url-input"
                           value="<?php echo esc_attr( $display_value ); ?>"
                           placeholder="Paste URL or click Pick Image →" />
                    <button type="button" class="etpb-media-pick-btn">🖼️ Pick Image</button>
                </div>
                <?php if ( $display_value ) : ?>
                    <div class="etpb-media-preview">
                        <img src="<?php echo esc_url( $display_value ); ?>" alt="Preview" class="etpb-media-thumb" />
                        <button type="button" class="etpb-media-clear-btn">✕ Remove</button>
                    </div>
                <?php else : ?>
                    <div class="etpb-media-preview" style="display:none;">
                        <img src="" alt="Preview" class="etpb-media-thumb" />
                        <button type="button" class="etpb-media-clear-btn">✕ Remove</button>
                    </div>
                <?php endif; ?>
                <?php
                break;

            case 'column_builder':
                $columns_saved = is_array( $value ) ? $value : array();
                ?>
                <div class="etpb-column-builder" data-field-key="<?php echo esc_attr( $field_key ); ?>">
                    <div class="etpb-column-builder-inner">
                        <p class="etpb-col-builder-note">
                            <strong>Note:</strong> Column count is determined by the Column Layout setting above.
                            Add blocks to each column using the buttons below.
                        </p>
                        <div class="etpb-layout-columns-wrap">
                            <?php foreach ( $columns_saved as $col_idx => $col_data ) :
                                $col_blocks = $col_data['blocks'] ?? array();
                                ?>
                                <div class="etpb-layout-column" data-col-index="<?php echo (int) $col_idx; ?>">
                                    <div class="etpb-layout-col-header">
                                        <span class="etpb-col-label">Column <?php echo (int) $col_idx + 1; ?></span>
                                    </div>
                                    <div class="etpb-nested-blocks-list">
                                        <?php foreach ( $col_blocks as $nb_idx => $nested_block ) :
                                            $nb_type = $nested_block['type']   ?? '';
                                            $nb_def  = etpb_get_block_definitions()[ $nb_type ] ?? null;
                                            if ( ! $nb_def ) continue;
                                            ?>
                                            <div class="etpb-nested-block-row" data-type="<?php echo esc_attr( $nb_type ); ?>">
                                                <div class="etpb-nested-block-header">
                                                    <span class="etpb-nested-drag-handle" title="Drag to reorder">⠿</span>
                                                    <span class="etpb-nested-icon"><?php echo $nb_def['icon']; ?></span>
                                                    <span class="etpb-nested-label"><?php echo esc_html( $nb_def['label'] ); ?></span>
                                                    <div class="etpb-nested-actions">
                                                        <button type="button" class="etpb-nested-btn-toggle">✏️ Edit</button>
                                                        <button type="button" class="etpb-nested-btn-remove">🗑️</button>
                                                    </div>
                                                </div>
                                                <div class="etpb-nested-block-fields" style="display:none;">
                                                    <p class="clb-fields-desc"><?php echo esc_html( $nb_def['description'] ); ?></p>
                                                    <?php foreach ( $nb_def['fields'] as $nf_key => $nf_field ) :
                                                        $nf_value = $nested_block['fields'][ $nf_key ] ?? null;
                                                        etpb_render_field( 0, $nf_key, $nf_field, $nf_value );
                                                    endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="etpb-add-nested-block-btn" data-col-index="<?php echo (int) $col_idx; ?>">
                                        ＋ Add block to this column
                                    </button>
                                </div>
                            <?php endforeach; ?>
                            <?php if ( empty( $columns_saved ) ) :
                                for ( $i = 0; $i < 2; $i++ ) : ?>
                                    <div class="etpb-layout-column" data-col-index="<?php echo $i; ?>">
                                        <div class="etpb-layout-col-header">
                                            <span class="etpb-col-label">Column <?php echo $i + 1; ?></span>
                                        </div>
                                        <div class="etpb-nested-blocks-list"></div>
                                        <button type="button" class="etpb-add-nested-block-btn" data-col-index="<?php echo $i; ?>">
                                            ＋ Add block to this column
                                        </button>
                                    </div>
                                <?php endfor;
                            endif; ?>
                        </div>
                    </div>
                </div>
                <?php break;

        endswitch;
        ?>
    </div>
    <?php
}

/**
 * Render a single item inside a repeater field.
 */
function etpb_render_repeater_item( $sub_fields, $index, $values = array() ) {
    ?>
    <div class="etpb-repeater-item">
        <div class="etpb-repeater-item-header">
            <span class="etpb-drag-handle" title="Drag to reorder">⠿</span>
            <span class="etpb-repeater-item-num">Item <?php echo (int) $index + 1; ?></span>
            <button type="button" class="etpb-btn-remove-item">✕ Remove</button>
        </div>
        <?php foreach ( $sub_fields as $sub_key => $sub_field ) : ?>
            <div class="etpb-sub-field" data-sub-key="<?php echo esc_attr( $sub_key ); ?>">
                <label><?php echo esc_html( $sub_field['label'] ); ?></label>
                <?php if ( $sub_field['type'] === 'icon_picker' ) : ?>
                    <select class="etpb-select etpb-sub-field-value">
                        <?php foreach ( etpb_get_available_icons() as $icon_val => $icon_label ) : ?>
                            <option value="<?php echo esc_attr( $icon_val ); ?>"
                                <?php selected( $values[ $sub_key ] ?? '', $icon_val ); ?>>
                                <?php echo esc_html( $icon_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ( isset( $sub_field['type'] ) && $sub_field['type'] === 'textarea' ) : ?>
                    <textarea class="etpb-textarea etpb-sub-field-value"
                              rows="3"><?php echo esc_textarea( $values[ $sub_key ] ?? '' ); ?></textarea>
                <?php else : ?>
                    <input type="text"
                           class="etpb-input etpb-sub-field-value"
                           value="<?php echo esc_attr( $values[ $sub_key ] ?? '' ); ?>" />
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Save block configuration when a page is saved.
 */
function etpb_save_blocks( $post_id ) {
    if ( ! isset( $_POST['etpb_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['etpb_nonce'], 'etpb_save_blocks' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( 'page' !== get_post_type( $post_id ) ) return;

    if ( isset( $_POST['etpb_blocks_config'] ) ) {
        $raw     = wp_unslash( $_POST['etpb_blocks_config'] );
        $decoded = json_decode( $raw, true );

        if ( is_array( $decoded ) ) {
            update_post_meta( $post_id, 'etpb_blocks_config', wp_slash( $raw ) );

            // Extract woo_product block data and save to post meta
            foreach ( $decoded as $block ) {
                if ( ( $block['type'] ?? '' ) === 'woo_product' ) {
                    $pid  = sanitize_text_field( $block['fields']['product_id']   ?? '' );
                    $slug = sanitize_title( $block['fields']['product_slug'] ?? '' );
                    update_post_meta( $post_id, 'Product ID',   $pid );
                    update_post_meta( $post_id, 'Product SLUG', $slug );
                    break;
                }
            }

            // Extract course_outline block data and save to post meta
            foreach ( $decoded as $block ) {
                if ( ( $block['type'] ?? '' ) === 'course_outline' ) {
                    $outline = wp_kses_post( $block['fields']['outline_content'] ?? '' );
                    update_post_meta( $post_id, 'Course Outline', $outline );
                    break;
                }
            }
        }
    }
}
add_action( 'save_post', 'etpb_save_blocks' );

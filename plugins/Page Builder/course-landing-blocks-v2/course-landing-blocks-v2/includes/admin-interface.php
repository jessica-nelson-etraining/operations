<?php
/**
 * admin-interface.php
 *
 * Everything related to the WordPress admin editor panel.
 * This file:
 *  - Adds the "Course Landing Page Builder" meta box to the page editor
 *  - Renders the block list, the "Add a Section" picker, and all field inputs
 *  - Saves the block configuration when the page is saved
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the meta box that appears on all page edit screens.
 */
function clb_add_meta_box() {
    add_meta_box(
        'clb_block_editor',
        '🧱 Course Landing Page Builder',
        'clb_render_meta_box',
        'page',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'clb_add_meta_box' );

/**
 * Render the entire meta box content.
 * This is what the non-developer sees when editing a page.
 */
function clb_render_meta_box( $post ) {
    $blocks      = clb_get_page_blocks( $post->ID );
    $definitions = clb_get_block_definitions();

    // Security nonce — WordPress uses this to verify the save request is legitimate
    wp_nonce_field( 'clb_save_blocks', 'clb_nonce' );

    // Get saved product connection values
    $saved_product_id   = get_post_meta( $post->ID, 'Product ID',   true );
    $saved_product_slug = get_post_meta( $post->ID, 'Product SLUG', true );
    ?>

    <div id="clb-editor" class="clb-editor">

        <div class="clb-intro">
            <strong>Course Landing Page Builder</strong><br>
            Use the sections below to build this course landing page.
            Add sections using the button below, drag the <strong>⠿</strong> handle to reorder them,
            and click <strong>✏️ Edit</strong> to configure each one.
            Click <strong>Update</strong> in the top right to save your changes.
        </div>

        <!-- ── WooCommerce Product Connection ── -->
        <div class="clb-product-connection">
            <div class="clb-product-connection-title">
                🔗 WooCommerce Product Connection
            </div>
            <p class="clb-field-desc">
                Connect this page to a WooCommerce product so the price and enroll button
                link update automatically. Both fields are required for dynamic pricing to work.
            </p>
            <div class="clb-product-fields">
                <div class="clb-product-field">
                    <label class="clb-field-label" for="clb_product_id_input">
                        Product ID
                    </label>
                    <p class="clb-field-desc">
                        The WooCommerce product number. Find it by editing the product in
                        WooCommerce — it appears in the browser URL as <code>post=123</code>.
                    </p>
                    <input type="text"
                           id="clb_product_id_input"
                           name="clb_product_id"
                           class="clb-input"
                           value="<?php echo esc_attr( $saved_product_id ); ?>"
                           placeholder="e.g. 123" />
                </div>
                <div class="clb-product-field">
                    <label class="clb-field-label" for="clb_product_slug_input">
                        Product SLUG
                    </label>
                    <p class="clb-field-desc">
                        The URL-friendly product name. Find it in WooCommerce under
                        Product data → Advanced → Slug. Example: <code>hazwoper-9-hour-refresher</code>
                    </p>
                    <input type="text"
                           id="clb_product_slug_input"
                           name="clb_product_slug"
                           class="clb-input"
                           value="<?php echo esc_attr( $saved_product_slug ); ?>"
                           placeholder="e.g. hazwoper-9-hour-refresher" />
                </div>
            </div>
            <?php if ( $saved_product_id && function_exists( 'wc_get_product' ) ) :
                $preview_product = wc_get_product( (int) $saved_product_id );
                if ( $preview_product ) : ?>
                    <div class="clb-product-connected">
                        ✅ Connected to: <strong><?php echo esc_html( $preview_product->get_name() ); ?></strong>
                        — Price: <strong><?php echo wc_price( $preview_product->get_price() ); ?></strong>
                    </div>
                <?php else : ?>
                    <div class="clb-product-not-found">
                        ⚠️ No product found with ID <strong><?php echo esc_html( $saved_product_id ); ?></strong>.
                        Double-check the ID in WooCommerce.
                    </div>
                <?php endif;
            endif; ?>
        </div>

        <!-- ── Course Outline ── -->
        <?php $saved_outline = get_post_meta( $post->ID, 'Course Outline', true ); ?>
        <div class="clb-course-outline-wrap">
            <div class="clb-product-connection-title">
                📋 Course Outline
            </div>
            <p class="clb-field-desc">
                This content appears in the popup when a visitor clicks the
                <strong>Course Outline Button</strong> block. Use the editor below to add
                bullet points, numbered lists, headings, or any formatted content.
                Leave blank to hide the Course Outline Button automatically.
            </p>
            <?php
            // wp_editor() outputs a full rich text editor — the same one used in the
            // WordPress post editor. This lets non-developers format lists and headings
            // without writing any HTML.
            wp_editor(
                $saved_outline,
                'clb_course_outline',
                array(
                    'textarea_name' => 'clb_course_outline',
                    'textarea_rows' => 10,
                    'media_buttons' => false,
                    'teeny'         => false,
                    'tinymce'       => array(
                        'toolbar1' => 'bold,italic,bullist,numlist,blockquote,hr,link,unlink,undo,redo',
                        'toolbar2' => '',
                    ),
                    'quicktags'     => array(
                        'buttons' => 'strong,em,ul,ol,li,close',
                    ),
                )
            );
            ?>
        </div>

        <!-- ── Active blocks list ── -->
        <div class="clb-blocks-list" id="clb-blocks-list">
            <?php if ( empty( $blocks ) ) : ?>
                <div class="clb-empty-state" id="clb-empty-state">
                    <div class="clb-empty-icon">📄</div>
                    <p><strong>No sections added yet.</strong></p>
                    <p>Click <strong>"＋ Add a Section"</strong> below to start building this page.</p>
                </div>
            <?php else : ?>
                <?php foreach ( $blocks as $index => $block ) : ?>
                    <?php clb_render_block_editor_row( $index, $block, $definitions ); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ── Add Section button ── -->
        <div class="clb-add-block-wrap">
            <button type="button" class="clb-btn clb-btn-add" id="clb-add-block-btn">
                ＋ Add a Section
            </button>
        </div>

        <!-- ── Block type picker (hidden until "Add a Section" is clicked) ── -->
        <div class="clb-block-picker" id="clb-block-picker" style="display:none;">
            <h3>Choose a Section Type</h3>
            <p class="clb-picker-sub">Click a section type to add it to the page.</p>
            <div class="clb-block-picker-grid">
                <?php foreach ( $definitions as $type => $def ) : ?>
                    <div class="clb-block-option" data-type="<?php echo esc_attr( $type ); ?>">
                        <div class="clb-block-option-icon"><?php echo $def['icon']; ?></div>
                        <div class="clb-block-option-label"><?php echo esc_html( $def['label'] ); ?></div>
                        <div class="clb-block-option-desc"><?php echo esc_html( $def['description'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="clb-btn clb-btn-cancel" id="clb-cancel-add">
                ✕ Cancel
            </button>
        </div>

        <!-- ── Hidden field: stores all block data as JSON, submitted with the page save ── -->
        <input type="hidden"
               name="clb_blocks_config"
               id="clb-blocks-config"
               value="<?php echo esc_attr( json_encode( $blocks ) ); ?>" />

    </div><!-- /#clb-editor -->
    <?php
}

/**
 * Render a single block row in the editor.
 * Each row has a header (always visible) and a fields area (shown when Edit is clicked).
 *
 * @param int    $index       Position of this block in the list (0-based)
 * @param array  $block       The block data: ['type' => '...', 'fields' => [...]]
 * @param array  $definitions The full block definitions from clb_get_block_definitions()
 */
function clb_render_block_editor_row( $index, $block, $definitions ) {
    $type = $block['type'] ?? '';
    $def  = $definitions[ $type ] ?? null;
    if ( ! $def ) return; // Skip unknown block types
    ?>
    <div class="clb-block-row" data-index="<?php echo (int) $index; ?>" data-type="<?php echo esc_attr( $type ); ?>">

        <!-- Block header: always visible -->
        <div class="clb-block-header">
            <span class="clb-drag-handle" title="Drag to reorder this section">⠿</span>
            <span class="clb-block-icon"><?php echo $def['icon']; ?></span>
            <span class="clb-block-title"><?php echo esc_html( $def['label'] ); ?></span>
            <div class="clb-block-actions">
                <button type="button" class="clb-btn-toggle-fields" aria-expanded="false">
                    ✏️ Edit
                </button>
                <button type="button" class="clb-btn-remove" title="Remove this section">
                    🗑️ Remove
                </button>
            </div>
        </div>

        <!-- Block fields: hidden until Edit is clicked -->
        <div class="clb-block-fields" style="display:none;" aria-hidden="true">
            <p class="clb-fields-desc"><?php echo esc_html( $def['description'] ); ?></p>
            <?php foreach ( $def['fields'] as $field_key => $field ) : ?>
                <?php
                $saved_value = $block['fields'][ $field_key ] ?? null;
                clb_render_field( $index, $field_key, $field, $saved_value );
                ?>
            <?php endforeach; ?>
        </div>

    </div>
    <?php
}

/**
 * Render a single field inside a block's edit area.
 *
 * @param int    $block_index  Which block this field belongs to
 * @param string $field_key    The field's identifier (e.g. 'eyebrow', 'button_text')
 * @param array  $field        Field definition (type, label, description, etc.)
 * @param mixed  $value        The currently saved value for this field
 */
function clb_render_field( $block_index, $field_key, $field, $value ) {
    $field_id = 'clb_block_' . $block_index . '_' . $field_key;
    $type     = $field['type'] ?? 'text';
    ?>
    <div class="clb-field" data-field-key="<?php echo esc_attr( $field_key ); ?>">

        <label class="clb-field-label" for="<?php echo esc_attr( $field_id ); ?>">
            <?php echo esc_html( $field['label'] ); ?>
        </label>

        <?php if ( ! empty( $field['description'] ) ) : ?>
            <p class="clb-field-desc"><?php echo esc_html( $field['description'] ); ?></p>
        <?php endif; ?>

        <?php
        // Determine what value to show: saved value → default → empty
        $display_value = $value ?? $field['default'] ?? '';

        switch ( $type ) :

            case 'text': ?>
                <input type="text"
                       id="<?php echo esc_attr( $field_id ); ?>"
                       class="clb-input clb-field-value"
                       value="<?php echo esc_attr( $display_value ); ?>" />
                <?php break;

            case 'textarea': ?>
                <textarea id="<?php echo esc_attr( $field_id ); ?>"
                          class="clb-textarea clb-field-value"
                          rows="3"><?php echo esc_textarea( $display_value ); ?></textarea>
                <?php break;

            case 'select': ?>
                <select id="<?php echo esc_attr( $field_id ); ?>"
                        class="clb-select clb-field-value">
                    <?php foreach ( $field['options'] as $opt_val => $opt_label ) : ?>
                        <option value="<?php echo esc_attr( $opt_val ); ?>"
                            <?php selected( $display_value, $opt_val ); ?>>
                            <?php echo esc_html( $opt_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php break;

            case 'toggle':
                // For toggles, value is boolean; default might be true/false
                $is_checked = isset( $value ) ? (bool) $value : (bool) ( $field['default'] ?? false );
                ?>
                <label class="clb-toggle">
                    <input type="checkbox"
                           class="clb-checkbox clb-field-value"
                           <?php checked( $is_checked ); ?> />
                    <span class="clb-toggle-slider"></span>
                    <span class="clb-toggle-label">
                        <?php echo $is_checked ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </label>
                <?php break;

            case 'icon_picker': ?>
                <select id="<?php echo esc_attr( $field_id ); ?>"
                        class="clb-select clb-field-value">
                    <?php foreach ( clb_get_available_icons() as $icon_val => $icon_label ) : ?>
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
                <div class="clb-repeater"
                     data-field-key="<?php echo esc_attr( $field_key ); ?>"
                     data-sub-fields="<?php echo esc_attr( json_encode( $field['sub_fields'] ) ); ?>">

                    <div class="clb-repeater-items">
                        <?php foreach ( $items as $item_index => $item_data ) : ?>
                            <?php clb_render_repeater_item( $field['sub_fields'], $item_index, $item_data ); ?>
                        <?php endforeach; ?>
                        <?php if ( empty( $items ) ) : ?>
                            <p class="clb-repeater-empty">No items yet. Click the button below to add one.</p>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="clb-btn-add-item">
                        ＋ Add Item
                    </button>
                </div>
                <?php break;

        endswitch;
        ?>
    </div>
    <?php
}

/**
 * Render a single item inside a repeater field.
 *
 * @param array $sub_fields  Definition of sub-fields (icon, text, etc.)
 * @param int   $index       Item position in the list
 * @param array $values      Saved values for this item's sub-fields
 */
function clb_render_repeater_item( $sub_fields, $index, $values = array() ) {
    ?>
    <div class="clb-repeater-item">
        <div class="clb-repeater-item-header">
            <span class="clb-drag-handle" title="Drag to reorder">⠿</span>
            <span class="clb-repeater-item-num">Item <?php echo (int) $index + 1; ?></span>
            <button type="button" class="clb-btn-remove-item">✕ Remove</button>
        </div>
        <?php foreach ( $sub_fields as $sub_key => $sub_field ) : ?>
            <div class="clb-sub-field" data-sub-key="<?php echo esc_attr( $sub_key ); ?>">
                <label><?php echo esc_html( $sub_field['label'] ); ?></label>
                <?php if ( $sub_field['type'] === 'icon_picker' ) : ?>
                    <select class="clb-select clb-sub-field-value">
                        <?php foreach ( clb_get_available_icons() as $icon_val => $icon_label ) : ?>
                            <option value="<?php echo esc_attr( $icon_val ); ?>"
                                <?php selected( $values[ $sub_key ] ?? '', $icon_val ); ?>>
                                <?php echo esc_html( $icon_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="text"
                           class="clb-input clb-sub-field-value"
                           value="<?php echo esc_attr( $values[ $sub_key ] ?? '' ); ?>" />
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Save block configuration when a page is saved.
 * WordPress calls this function automatically on every page save.
 */
function clb_save_blocks( $post_id ) {
    // Verify this is a legitimate save request, not an autosave or forged request
    if ( ! isset( $_POST['clb_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['clb_nonce'], 'clb_save_blocks' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( 'page' !== get_post_type( $post_id ) ) return;

    if ( isset( $_POST['clb_blocks_config'] ) ) {
        $raw     = wp_unslash( $_POST['clb_blocks_config'] );
        $decoded = json_decode( $raw, true );

        // Only save if it's valid JSON that decoded to an array
        if ( is_array( $decoded ) ) {
            update_post_meta( $post_id, 'clb_blocks_config', wp_slash( $raw ) );
        }
    }

    // Save Product ID — connects this page to a WooCommerce product
    if ( isset( $_POST['clb_product_id'] ) ) {
        $product_id = sanitize_text_field( wp_unslash( $_POST['clb_product_id'] ) );
        update_post_meta( $post_id, 'Product ID', $product_id );
    }

    // Save Product SLUG — used to build the enroll button URL
    if ( isset( $_POST['clb_product_slug'] ) ) {
        $product_slug = sanitize_title( wp_unslash( $_POST['clb_product_slug'] ) );
        update_post_meta( $post_id, 'Product SLUG', $product_slug );
    }

    // Save Course Outline — used by the Course Outline Button block popup
    if ( isset( $_POST['clb_course_outline'] ) ) {
        $outline = wp_kses_post( wp_unslash( $_POST['clb_course_outline'] ) );
        update_post_meta( $post_id, 'Course Outline', $outline );
    }
}
add_action( 'save_post', 'clb_save_blocks' );

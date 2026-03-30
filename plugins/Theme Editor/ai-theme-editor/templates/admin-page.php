<?php
/**
 * Admin page template — WP AI Theme Editor
 * Variables available: $api_key_missing (bool)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wrap" id="wpai-editor">

    <?php if ( $api_key_missing ) : ?>
        <div class="notice notice-warning" style="margin:16px 0 0;">
            <p>
                <strong>API key not set.</strong>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpai-settings' ) ); ?>">Go to Settings</a>
                to add your Anthropic API key before using the editor.
            </p>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="wpai-header">
        <h1 class="wpai-title">AI Theme Editor</h1>
        <div class="wpai-header-links">
            <button id="wpai-help-toggle" class="wpai-settings-link" type="button">? How to use</button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpai-settings' ) ); ?>" class="wpai-settings-link">
                ⚙ Settings
            </a>
        </div>
    </div>

    <!-- How to use (collapsible) -->
    <div id="wpai-help-section" class="wpai-help-section" style="display:none;">

        <h3 class="wpai-help-title">How to use the AI Theme Editor</h3>

        <div class="wpai-help-grid">

            <div class="wpai-help-item">
                <div class="wpai-help-icon">1</div>
                <div>
                    <strong>Select and load a file</strong>
                    Use the dropdown to pick any file from your active theme or an allowed plugin, then click <em>Load File</em>. The full file contents appear in the code viewer on the left.
                </div>
            </div>

            <div class="wpai-help-item">
                <div class="wpai-help-icon">2</div>
                <div>
                    <strong>Edit the code directly</strong>
                    Click anywhere in the code viewer and type — it works like a basic text editor. When you've made your changes, a yellow <em>● Unsaved</em> badge appears. Click <em>💾 Save File</em> to save. A backup is created automatically first.
                </div>
            </div>

            <div class="wpai-help-item">
                <div class="wpai-help-icon">3</div>
                <div>
                    <strong>Ask the AI a question (Question mode)</strong>
                    Type a plain-English question in the chat panel and hit Send — for example: <em>"Where is the font size for the body text set?"</em> or <em>"Show me all the CTA button styles."</em> The AI will explain the code and highlight the matching lines in yellow.
                </div>
            </div>

            <div class="wpai-help-item">
                <div class="wpai-help-icon">4</div>
                <div>
                    <strong>Ask the AI to make a change (Edit mode)</strong>
                    Switch to <em>Edit mode</em> using the toggle in the AI panel header, then describe what you want changed — for example: <em>"Change the social icon color to white"</em> or <em>"Update the logo image URL to /wp-content/uploads/new-logo.png."</em> The AI returns a before/after diff for you to review before anything is saved.
                </div>
            </div>

            <div class="wpai-help-item">
                <div class="wpai-help-icon">5</div>
                <div>
                    <strong>Review the diff, then apply or discard</strong>
                    After an AI edit, the <em>Proposed Changes</em> panel shows exactly what will change — green lines are additions, red lines are removals. Click <em>✓ Apply Changes</em> to save, <em>👁 Preview in New Tab</em> to test it live first, or <em>✕ Discard</em> to throw it away.
                </div>
            </div>

            <div class="wpai-help-item">
                <div class="wpai-help-icon">6</div>
                <div>
                    <strong>Restore a previous version</strong>
                    Every save (whether manual or AI) creates a timestamped backup automatically. Click <em>💾 Backups ▾</em> in the code viewer header to see a list of all saved versions for the current file. Click <em>Restore</em> next to any entry to roll back — the current version is backed up first, so you can always undo the restore too.
                </div>
            </div>

        </div>

        <div class="wpai-help-tips">
            <strong>Tips:</strong>
            Press <kbd>Ctrl+Enter</kbd> (or <kbd>⌘+Enter</kbd> on Mac) to send a message without clicking Send. &nbsp;·&nbsp;
            If the AI times out on Edit mode, try a more specific instruction — shorter requests return faster. &nbsp;·&nbsp;
            Use Question mode first to find the right lines, then edit them manually or switch to Edit mode with a targeted instruction.
        </div>

    </div>

    <!-- File Browser -->
    <div class="wpai-file-browser">
        <div class="wpai-file-browser-row">
            <select id="wpai-file-select" class="wpai-select">
                <option value="">— Loading files… —</option>
            </select>
            <button id="wpai-load-file" class="wpai-btn wpai-btn-primary" disabled>
                Load File
            </button>
            <button id="wpai-new-file-btn" class="wpai-btn wpai-btn-secondary">
                ＋ New
            </button>
        </div>
        <div id="wpai-file-info" class="wpai-file-info" style="display:none;"></div>
    </div>

    <!-- New File / Child Theme Modal -->
    <div id="wpai-new-modal" class="wpai-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="wpai-modal-title">
        <div class="wpai-modal">
            <div class="wpai-modal-header">
                <h2 id="wpai-modal-title" class="wpai-modal-title">Create New</h2>
                <button id="wpai-modal-close" class="wpai-modal-close" aria-label="Close">&times;</button>
            </div>

            <!-- Tab bar -->
            <div class="wpai-modal-tabs" role="tablist">
                <button class="wpai-modal-tab wpai-modal-tab-active" data-tab="new-file" role="tab" aria-selected="true">
                    New File
                </button>
                <button class="wpai-modal-tab" data-tab="child-theme" role="tab" aria-selected="false">
                    New Child Theme
                </button>
            </div>

            <!-- Tab: New File -->
            <div class="wpai-modal-body" data-tab-panel="new-file">

                <div class="wpai-form-row">
                    <label class="wpai-form-label">Add file to</label>
                    <div class="wpai-radio-group">
                        <label class="wpai-radio-label">
                            <input type="radio" name="wpai_new_destination" value="theme" checked>
                            Active Theme
                        </label>
                        <label class="wpai-radio-label" id="wpai-dest-child-label">
                            <input type="radio" name="wpai_new_destination" value="child">
                            Child Theme
                        </label>
                    </div>
                </div>

                <div class="wpai-form-row">
                    <label class="wpai-form-label" for="wpai-new-filename">Filename</label>
                    <input type="text" id="wpai-new-filename" class="wpai-form-input"
                        placeholder="e.g. page-about.php or template-parts/hero.php">
                    <p class="wpai-form-hint">Relative path within the theme. Only .php and .css files allowed.</p>
                </div>

                <div class="wpai-form-row">
                    <label class="wpai-form-label">Starting content</label>
                    <div class="wpai-radio-group">
                        <label class="wpai-radio-label">
                            <input type="radio" name="wpai_new_content_type" value="blank" checked>
                            Blank file
                        </label>
                        <label class="wpai-radio-label">
                            <input type="radio" name="wpai_new_content_type" value="ai">
                            AI-generated
                        </label>
                    </div>
                </div>

                <div class="wpai-form-row" id="wpai-new-file-ai-row" style="display:none;">
                    <label class="wpai-form-label" for="wpai-new-file-description">Describe what this file should do</label>
                    <textarea id="wpai-new-file-description" class="wpai-form-textarea" rows="3"
                        placeholder="e.g. A page template for the About page with a hero image and team member grid"></textarea>
                </div>

                <div class="wpai-modal-actions">
                    <button id="wpai-create-file-btn" class="wpai-btn wpai-btn-primary">
                        Create File
                    </button>
                    <button class="wpai-btn wpai-btn-secondary wpai-modal-cancel-btn">Cancel</button>
                </div>

            </div><!-- [new-file] -->

            <!-- Tab: New Child Theme -->
            <div class="wpai-modal-body" data-tab-panel="child-theme" style="display:none;">

                <div class="wpai-form-row">
                    <label class="wpai-form-label" for="wpai-child-name">Child theme name</label>
                    <input type="text" id="wpai-child-name" class="wpai-form-input"
                        placeholder="e.g. My Custom Theme">
                </div>

                <div class="wpai-form-row">
                    <label class="wpai-form-label" for="wpai-parent-theme">Parent theme</label>
                    <select id="wpai-parent-theme" class="wpai-form-select">
                        <option value="">— Loading themes… —</option>
                    </select>
                </div>

                <div class="wpai-form-row">
                    <label class="wpai-form-label">Starter files</label>
                    <div class="wpai-radio-group">
                        <label class="wpai-radio-label">
                            <input type="radio" name="wpai_child_content_type" value="blank" checked>
                            Minimal (standard WordPress stubs)
                        </label>
                        <label class="wpai-radio-label">
                            <input type="radio" name="wpai_child_content_type" value="ai">
                            AI-generated
                        </label>
                    </div>
                </div>

                <div class="wpai-form-row" id="wpai-child-ai-row" style="display:none;">
                    <label class="wpai-form-label" for="wpai-child-description">Describe the customisations you plan to make</label>
                    <textarea id="wpai-child-description" class="wpai-form-textarea" rows="3"
                        placeholder="e.g. Override the header to use a sticky nav, add custom fonts, and change the primary colour to navy blue"></textarea>
                </div>

                <div class="wpai-modal-actions">
                    <button id="wpai-create-child-btn" class="wpai-btn wpai-btn-primary">
                        Create Child Theme
                    </button>
                    <button class="wpai-btn wpai-btn-secondary wpai-modal-cancel-btn">Cancel</button>
                </div>

            </div><!-- [child-theme] -->

        </div><!-- .wpai-modal -->
    </div><!-- #wpai-new-modal -->

    <!-- Main Workspace (hidden until a file is loaded) -->
    <div id="wpai-workspace" style="display:none;">

        <div class="wpai-columns">

            <!-- LEFT: Code Viewer -->
            <div class="wpai-col-code">
                <div class="wpai-panel">
                    <div class="wpai-panel-header">
                        <span id="wpai-current-filename" class="wpai-filename">—</span>
                        <span id="wpai-dirty-indicator" class="wpai-dirty-indicator" style="display:none;">● Unsaved</span>
                        <div class="wpai-panel-actions">
                            <button id="wpai-save-btn" class="wpai-btn wpai-btn-success wpai-btn-small" style="display:none;" title="Save your edits to the live file (backup created automatically)">
                                💾 Save File
                            </button>
                            <button id="wpai-copy-btn" class="wpai-btn wpai-btn-small" title="Copy file contents to clipboard">
                                📋 Copy
                            </button>
                            <div class="wpai-backup-wrap">
                                <button id="wpai-backups-btn" class="wpai-btn wpai-btn-small">
                                    💾 Backups ▾
                                </button>
                                <div id="wpai-backups-dropdown" class="wpai-dropdown" style="display:none;">
                                    <div class="wpai-dropdown-inner">
                                        <p class="wpai-dropdown-loading">Loading backups…</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="wpai-editor-wrap" class="wpai-editor-wrap">
                        <textarea id="wpai-code-editor"></textarea>
                    </div>
                </div>
            </div>

            <!-- RIGHT: AI Chat Panel -->
            <div class="wpai-col-chat">
                <div class="wpai-panel wpai-chat-panel">
                    <div class="wpai-panel-header">
                        <span>AI Assistant</span>
                        <div class="wpai-mode-toggle">
                            <label class="wpai-mode-label wpai-mode-active" id="wpai-mode-question-label">
                                <input type="radio" name="wpai_mode" value="question" checked>
                                Question
                            </label>
                            <label class="wpai-mode-label" id="wpai-mode-edit-label">
                                <input type="radio" name="wpai_mode" value="edit">
                                Edit
                            </label>
                        </div>
                    </div>

                    <div id="wpai-chat-messages" class="wpai-chat-messages">
                        <div class="wpai-chat-intro" id="wpai-chat-intro">
                            <p>
                                <strong>Question mode:</strong> Ask what any part of this file does — the AI will explain it in plain English and highlight the relevant lines.
                            </p>
                            <p>
                                <strong>Edit mode:</strong> Describe a change you want to make — the AI will show you exactly what will change before saving anything.
                            </p>
                            <p class="wpai-chat-tip">
                                Tip: Press <kbd>Ctrl+Enter</kbd> (or <kbd>⌘+Enter</kbd> on Mac) to send.
                            </p>
                        </div>
                    </div>

                    <div class="wpai-chat-input-wrap">
                        <textarea
                            id="wpai-chat-input"
                            class="wpai-chat-input"
                            placeholder="Ask a question about this file…"
                            rows="3"
                            disabled
                        ></textarea>
                        <div class="wpai-chat-controls">
                            <button id="wpai-send-btn" class="wpai-btn wpai-btn-primary" disabled>
                                Send
                            </button>
                            <button id="wpai-cancel-btn" class="wpai-btn wpai-btn-secondary" style="display:none;">
                                ✕ Cancel
                            </button>
                        </div>
                    </div>

                </div><!-- .wpai-chat-panel -->
            </div><!-- .wpai-col-chat -->

        </div><!-- .wpai-columns -->

        <!-- Diff Panel (hidden until an edit response arrives) -->
        <div id="wpai-diff-panel" class="wpai-panel wpai-diff-panel" style="display:none;">
            <div class="wpai-panel-header">
                <span>Proposed Changes</span>
                <span id="wpai-diff-summary" class="wpai-diff-summary-text"></span>
            </div>
            <div id="wpai-diff-container" class="wpai-diff-container"></div>
            <div class="wpai-diff-actions">
                <button id="wpai-apply-btn" class="wpai-btn wpai-btn-success">
                    ✓ Apply Changes
                </button>
                <button id="wpai-preview-btn" class="wpai-btn wpai-btn-secondary">
                    👁 Preview in New Tab
                </button>
                <button id="wpai-discard-btn" class="wpai-btn wpai-btn-danger">
                    ✕ Discard
                </button>
            </div>
        </div>

    </div><!-- #wpai-workspace -->

    <!-- Notices injected here by JS -->
    <div id="wpai-notices" class="wpai-notices"></div>

    <!-- Loading overlay (injected by JS when needed) -->

</div><!-- #wpai-editor -->

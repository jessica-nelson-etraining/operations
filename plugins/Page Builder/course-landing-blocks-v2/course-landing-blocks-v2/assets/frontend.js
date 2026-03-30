/**
 * Course Landing Blocks — Frontend JavaScript
 *
 * Handles interactive behaviour on the public-facing course landing page:
 *  - Opening and closing the Course Versions modal popup
 *  - Closing modal on overlay click or Escape key
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // ── Course Versions modal ───────────────────────────────────────────────

        // Open modal when a trigger button is clicked
        document.querySelectorAll('.clb-versions-trigger').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modalId = btn.getAttribute('data-modal');
                var modal   = document.getElementById(modalId);
                if ( modal ) {
                    modal.classList.add('open');
                    document.body.style.overflow = 'hidden'; // Prevent background scrolling
                }
            });
        });

        // Close modal when the × button is clicked
        document.querySelectorAll('.clb-modal-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                closeAllModals();
            });
        });

        // Close modal when clicking outside the modal box (on the overlay)
        document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
            overlay.addEventListener('click', function (e) {
                if ( e.target === overlay ) {
                    closeAllModals();
                }
            });
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if ( e.key === 'Escape' ) {
                closeAllModals();
            }
        });

        function closeAllModals() {
            document.querySelectorAll('.modal-overlay.open').forEach(function (modal) {
                modal.classList.remove('open');
            });
            document.body.style.overflow = ''; // Restore scrolling
        }

    });

})();

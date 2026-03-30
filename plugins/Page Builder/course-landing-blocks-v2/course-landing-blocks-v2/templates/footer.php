<?php
/**
 * footer.php — Plugin footer template
 *
 * Self-contained footer that matches footer-v1.php from the staging theme.
 * Uses absolute image URLs from staging so assets display correctly on the test site.
 * Footer widget columns fall back to placeholder links if no widgets are configured.
 *
 * NOTE FOR DEVELOPER:
 * When deploying to the live/staging site, replace the absolute staging image URLs
 * with get_template_directory_uri() calls, and ensure footer widget areas are
 * registered and populated in WordPress → Appearance → Widgets.
 */

// Images hosted on staging — displayed here via absolute URL for the test site presentation
$staging_img = 'https://niallstg.wpengine.com/wp-content/themes/etraintoday/V1/images/';
?>

<!-- ══════════════════════════════════════
     NEWSLETTER SECTION
     Shown on all pages except Why Us and About Us.
     Matches the newsletter block in footer-v1.php.
══════════════════════════════════════ -->
<div class="container-fluid newsletter-main">
    <div class="container">
        <div class="row justify-div align-items-center">
            <div class="col-md-5">
                <h1>Your go-to source for workplace safety updates.</h1>
                <p>Join our monthly newsletter covering industry stories, news &amp; timely updates,
                and get a free 110-page OSHA manual covering the latest training requirements.</p>
                <div class="form">
                    <div class="d-flex justify-content-between flex-column flex-lg-row gap-2">
                        <div class="email-style flex-grow-1">
                            <input type="email"
                                   placeholder="Email Address"
                                   class="form-control" />
                        </div>
                        <div>
                            <button type="button" class="btn-secondary-solid">
                                SIGN UP
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-7 text-center mt-4 mt-md-0">
                <img src="<?php echo esc_url( $staging_img . 'safety.png' ); ?>"
                     alt="Workplace Safety"
                     class="safety img-fluid" />
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     FOOTER — MAIN COLUMNS
     Logo + social icons + three link columns.
══════════════════════════════════════ -->
<div class="container-fluid background-theme1 custom-padding1">
    <div class="container">
        <div class="row header-row mb-4">

            <!-- Column 1: Logo + Social Icons -->
            <div class="col-md-3 mb-4 mb-md-0">
                <div class="logo-img mb-3">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <img src="<?php echo esc_url( $staging_img . 'LOGO.png' ); ?>"
                             alt="eTraining Inc."
                             style="max-width: 160px;" />
                    </a>
                </div>
                <ul class="d-flex social-ul list-unstyled gap-2 mb-0">
                    <li class="social-icons">
                        <div class="inner">
                            <a href="https://www.facebook.com/etraintoday" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url( $staging_img . 'facebook.svg' ); ?>" alt="Facebook" />
                            </a>
                        </div>
                    </li>
                    <li class="social-icons">
                        <div class="inner">
                            <a href="https://twitter.com/etraintoday" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url( $staging_img . 'twitter.svg' ); ?>" alt="Twitter" />
                            </a>
                        </div>
                    </li>
                    <li class="social-icons">
                        <div class="inner">
                            <a href="https://www.instagram.com/etraintoday" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url( $staging_img . 'instagram.svg' ); ?>" alt="Instagram" />
                            </a>
                        </div>
                    </li>
                    <li class="social-icons">
                        <div class="inner">
                            <a href="mailto:info@etraintoday.com">
                                <img src="<?php echo esc_url( $staging_img . 'mail-send.svg' ); ?>" alt="Email" />
                            </a>
                        </div>
                    </li>
                    <li class="social-icons">
                        <div class="inner">
                            <a href="https://www.linkedin.com/company/etraintoday" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url( $staging_img . 'linkedin-icon-white.png' ); ?>" alt="LinkedIn" />
                            </a>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Column 2: Courses widget area (falls back to placeholder links) -->
            <div class="col-md-3 footer-section fs1 mb-4 mb-md-0">
                <?php if ( function_exists( 'is_active_sidebar' ) && is_active_sidebar( 'footer-list-1' ) ) : ?>
                    <?php dynamic_sidebar( 'footer-list-1' ); ?>
                <?php else : ?>
                    <h5 class="footer-widget-title text-white mb-3">Popular Courses</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="#">HAZWOPER 40-Hour</a></li>
                        <li><a href="#">HAZWOPER 24-Hour</a></li>
                        <li><a href="#">HAZWOPER 8-Hour Refresher</a></li>
                        <li><a href="#">OSHA 10-Hour General Industry</a></li>
                        <li><a href="#">OSHA 30-Hour General Industry</a></li>
                        <li><a href="#">OSHA 10-Hour Construction</a></li>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Column 3: Resources widget area (falls back to placeholder links) -->
            <div class="col-md-3 footer-section fs2 mb-4 mb-md-0">
                <?php if ( function_exists( 'is_active_sidebar' ) && is_active_sidebar( 'footer-list-2' ) ) : ?>
                    <?php dynamic_sidebar( 'footer-list-2' ); ?>
                <?php else : ?>
                    <h5 class="footer-widget-title text-white mb-3">Resources</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Group Enrollment</a></li>
                        <li><a href="#">Site Safety Training</a></li>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Column 4: Contact/Info widget area (falls back to placeholder) -->
            <div class="col-md-3 footer-section fs3">
                <?php if ( function_exists( 'is_active_sidebar' ) && is_active_sidebar( 'footer-list-3' ) ) : ?>
                    <?php dynamic_sidebar( 'footer-list-3' ); ?>
                <?php else : ?>
                    <h5 class="footer-widget-title text-white mb-3">Contact</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Refund Policy</a></li>
                        <li><a href="mailto:info@etraintoday.com">info@etraintoday.com</a></li>
                    </ul>
                <?php endif; ?>
            </div>

        </div><!-- /.row -->
    </div><!-- /.container -->
</div>

<!-- ══════════════════════════════════════
     COPYRIGHT BAR
══════════════════════════════════════ -->
<div class="container-fluid background-theme2">
    <div class="container">
        <p class="copy-right">
            &copy; <?php echo date( 'Y' ); ?> eTraining Inc. All Rights Reserved.
        </p>
    </div>
</div>

<!-- Bootstrap 5 JS — required for mobile nav toggle and accordion -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php wp_footer(); ?>

</body>
</html>

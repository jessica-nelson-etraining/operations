<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php wp_title( '|', true, 'right' ); ?></title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="https://niallstg.wpengine.com/wp-content/themes/etraintoday/images/eTraining_logo.png">

    <!-- Google Fonts — all four used across the theme -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap 5 — grid and component framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Font Awesome 6 — icons throughout the page -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- Main theme CSS — contains header, footer, navigation, and all global styles -->
    <link rel="stylesheet" href="<?php echo CLB_URL; ?>assets/theme-main.css" />

    <!-- Landing page CSS — contains hero, trust bar, enrollment card, stats strip etc. -->
    <link rel="stylesheet" href="<?php echo CLB_URL; ?>assets/landing-page.css" />

    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<!-- ══════════════════════════════════════
     HEADER / NAVIGATION
     Matches header-v1.php from the staging theme.
     Uses absolute image URL from staging so logo displays on test site.
══════════════════════════════════════ -->
<div class="header-main-wrap">
    <header class="main-header cart-header">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid flex-column flex-md-row">

                <!-- Logo — links to homepage -->
                <a class="navbar-brand text-teal" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <img src="https://niallstg.wpengine.com/wp-content/themes/etraintoday/V1/images/LOGO.png"
                         alt="eTraining Inc."
                         style="max-height: 50px;" />
                </a>

                <!-- Navigation links -->
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo esc_url( home_url( '/courses/' ) ); ?>">Courses</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-signin" href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">
                                Sign In
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Mobile menu toggle -->
                <button class="navbar-toggler ms-auto"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#mainNav"
                        aria-controls="mainNav"
                        aria-expanded="false"
                        aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

            </div>
        </nav>
    </header>
</div>

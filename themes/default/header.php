<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="<?php echo e(get_settings('Site Description')); ?>">
        <title><?php echo e(get_content('Title')); ?> · <?php echo e(get_settings('Site Name')); ?></title>
        <link href="<?php echo e(theme_url_loc()); ?>/style.css" rel="stylesheet">
    </head>
    <body class="<?php insert_body_classes(); ?>">
        <header class="site-header">
            <a class="site-name" href="<?php echo e(site_url()); ?>"><?php echo e(get_settings('Site Name')); ?></a>
            <nav aria-label="Main navigation">
                <?php echo $get_main_nav; ?>
                <?php subMenu(); ?>
            </nav>
        </header>
        <main id="main-content">

<?php
if (! defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('tm-standalone-layout'); ?>>
<?php wp_body_open(); ?>
<?php echo TM_Public::render_standalone_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php wp_footer(); ?>
</body>
</html>

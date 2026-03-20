<?php
if (! defined('ABSPATH')) {
    exit;
}
get_header();
?>
<main class="tm-archive-page tm-shell">
    <header class="tm-page-hero">
        <div>
            <span class="tm-badge"><?php esc_html_e('Marketplace agro', 'terramarket'); ?></span>
            <h1><?php post_type_archive_title(); ?></h1>
            <p><?php esc_html_e('Explora avisos activos con foco en imagen, precio y ubicación.', 'terramarket'); ?></p>
        </div>
    </header>

    <?php if (have_posts()) : ?>
        <div class="tm-listing-grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php
                $post_id   = get_the_ID();
                $price     = (int) get_post_meta($post_id, 'tm_price_clp', true);
                $status    = get_post_meta($post_id, 'tm_listing_status', true) ?: 'active';
                $region    = TM_Helpers::get_term_name_for_post($post_id, 'tm_region');
                $comuna    = TM_Helpers::get_term_name_for_post($post_id, 'tm_comuna');
                $condition = TM_Helpers::get_term_name_for_post($post_id, 'tm_condition');
                $image_url = TM_Helpers::get_listing_primary_image_url($post_id, 'medium_large');
                ?>
                <article class="tm-listing-card">
                    <a class="tm-listing-card__media" href="<?php the_permalink(); ?>">
                        <?php if ($image_url) : ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                        <?php else : ?>
                            <span class="tm-placeholder"><?php esc_html_e('Sin imagen', 'terramarket'); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="tm-listing-card__body">
                        <span class="tm-status tm-status--<?php echo esc_attr($status); ?>"><?php echo esc_html(TM_Helpers::get_listing_status_label($status)); ?></span>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p class="tm-listing-price"><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p>
                        <p class="tm-listing-meta"><?php echo esc_html(trim($condition . ' · ' . $comuna . ', ' . $region, ' ·,')); ?></p>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        <div class="tm-pagination"><?php the_posts_pagination(); ?></div>
    <?php else : ?>
        <div class="tm-empty-state">
            <h2><?php esc_html_e('Todavía no hay avisos activos.', 'terramarket'); ?></h2>
            <p><?php esc_html_e('Cuando se publiquen avisos, aparecerán aquí.', 'terramarket'); ?></p>
        </div>
    <?php endif; ?>
</main>
<?php
get_footer();

<?php
if (! defined('ABSPATH')) {
    exit;
}
get_header();
while (have_posts()) : the_post();
    $post_id      = get_the_ID();
    $price        = (int) get_post_meta($post_id, 'tm_price_clp', true);
    $status       = TM_Helpers::get_effective_listing_status($post_id);
    $contact_name = get_post_meta($post_id, 'tm_contact_name', true);
    $contact_phone = get_post_meta($post_id, 'tm_contact_phone', true);
    $region       = TM_Helpers::get_term_name_for_post($post_id, 'tm_region');
    $comuna       = TM_Helpers::get_term_name_for_post($post_id, 'tm_comuna');
    $condition    = TM_Helpers::get_term_name_for_post($post_id, 'tm_condition');
    $category     = TM_Helpers::get_term_name_for_post($post_id, 'tm_category');
    $subcategory  = TM_Helpers::get_term_name_for_post($post_id, 'tm_subcategory');
    $views        = (int) get_post_meta($post_id, 'tm_views_count', true);
    $gallery      = TM_Helpers::get_listing_gallery($post_id);
    $share_link   = 'mailto:?subject=' . rawurlencode(sprintf(__('Mira este aviso: %s', 'terramarket'), get_the_title())) . '&body=' . rawurlencode(get_permalink($post_id));
    $notice       = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
    $error        = isset($_GET['tm_error']) ? sanitize_text_field(wp_unslash($_GET['tm_error'])) : '';
    ?>
    <main class="tm-single-page tm-shell">
        <header class="tm-page-hero">
            <div>
                <span class="tm-badge"><?php echo esc_html($category ?: __('Aviso', 'terramarket')); ?></span>
                <h1><?php the_title(); ?></h1>
                <p><?php echo esc_html(trim($subcategory . ' · ' . $comuna . ', ' . $region, ' ·,')); ?></p>
            </div>
            <div class="tm-page-hero__actions">
                <a class="tm-button tm-button-light" href="<?php echo esc_url($share_link); ?>"><?php esc_html_e('Compartir por email', 'terramarket'); ?></a>
            </div>
        </header>

        <?php if ($notice) : ?>
            <div class="tm-notice tm-notice-success"><?php echo esc_html(TM_Public::notice_label($notice)); ?></div>
        <?php endif; ?>
        <?php if ($error) : ?>
            <div class="tm-notice tm-notice-error"><?php echo esc_html(TM_Public::notice_label($error)); ?></div>
        <?php endif; ?>

        <div class="tm-single-layout">
            <section class="tm-single-main">
                <div class="tm-gallery">
                    <?php if (! empty($gallery)) : ?>
                        <div class="tm-gallery-main">
                            <img id="tm-main-image" src="<?php echo esc_url($gallery[0]['full']); ?>" alt="<?php echo esc_attr($gallery[0]['alt']); ?>">
                        </div>
                        <?php if (count($gallery) > 1) : ?>
                            <div class="tm-gallery-thumbs">
                                <?php foreach ($gallery as $index => $image) : ?>
                                    <button type="button" class="tm-gallery-thumb <?php echo 0 === $index ? 'is-active' : ''; ?>" data-full-image="<?php echo esc_url($image['full']); ?>">
                                        <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="tm-gallery-empty"><?php esc_html_e('Este aviso no tiene imágenes.', 'terramarket'); ?></div>
                    <?php endif; ?>
                </div>

                <article class="tm-panel tm-single-description">
                    <div class="tm-single-price-row">
                        <div>
                            <span class="tm-status tm-status--<?php echo esc_attr($status); ?>"><?php echo esc_html(TM_Helpers::get_listing_status_label($status)); ?></span>
                            <p class="tm-single-price"><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p>
                        </div>
                        <div class="tm-single-stats">
                            <span><?php echo esc_html($views); ?> <?php esc_html_e('visitas', 'terramarket'); ?></span>
                        </div>
                    </div>
                    <div class="tm-single-meta-grid">
                        <div><strong><?php esc_html_e('Condición', 'terramarket'); ?></strong><span><?php echo esc_html($condition ?: '-'); ?></span></div>
                        <div><strong><?php esc_html_e('Categoría', 'terramarket'); ?></strong><span><?php echo esc_html($category ?: '-'); ?></span></div>
                        <div><strong><?php esc_html_e('Subcategoría', 'terramarket'); ?></strong><span><?php echo esc_html($subcategory ?: '-'); ?></span></div>
                        <div><strong><?php esc_html_e('Ubicación', 'terramarket'); ?></strong><span><?php echo esc_html(trim($comuna . ', ' . $region, ', ')); ?></span></div>
                    </div>
                    <div class="tm-content"><?php the_content(); ?></div>
                </article>
            </section>

            <aside class="tm-single-sidebar">
                <section class="tm-panel tm-seller-box">
                    <h2><?php esc_html_e('Contacto del vendedor', 'terramarket'); ?></h2>
                    <ul class="tm-seller-list">
                        <li><strong><?php esc_html_e('Nombre', 'terramarket'); ?>:</strong> <?php echo esc_html($contact_name ?: '-'); ?></li>
                        <?php if ($contact_phone) : ?>
                            <li><strong><?php esc_html_e('Teléfono', 'terramarket'); ?>:</strong> <?php echo esc_html($contact_phone); ?></li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="tm-panel tm-contact-panel">
                    <h2><?php esc_html_e('Contactar vendedor', 'terramarket'); ?></h2>
                    <form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-contact-form">
                        <?php wp_nonce_field('tm_send_lead', 'tm_send_lead_nonce'); ?>
                        <input type="hidden" name="action" value="tm_send_lead">
                        <input type="hidden" name="tm_listing_id" value="<?php echo esc_attr((string) $post_id); ?>">
                        <input type="text" name="tm_website" value="" class="tm-honeypot" tabindex="-1" autocomplete="off">
                        <div class="tm-field">
                            <label for="tm_buyer_name"><?php esc_html_e('Nombre', 'terramarket'); ?></label>
                            <input type="text" id="tm_buyer_name" name="tm_buyer_name" required>
                        </div>
                        <div class="tm-field">
                            <label for="tm_buyer_email"><?php esc_html_e('Email', 'terramarket'); ?></label>
                            <input type="email" id="tm_buyer_email" name="tm_buyer_email" required>
                        </div>
                        <div class="tm-field">
                            <label for="tm_buyer_phone"><?php esc_html_e('Teléfono', 'terramarket'); ?></label>
                            <input type="text" id="tm_buyer_phone" name="tm_buyer_phone">
                        </div>
                        <div class="tm-field">
                            <label for="tm_message"><?php esc_html_e('Mensaje', 'terramarket'); ?></label>
                            <textarea id="tm_message" name="tm_message" rows="5" required><?php echo esc_textarea(sprintf(__('Hola, me interesa el aviso "%s".', 'terramarket'), get_the_title())); ?></textarea>
                        </div>
                        <button type="submit" class="tm-button tm-button-primary tm-button-block"><?php esc_html_e('Enviar mensaje', 'terramarket'); ?></button>
                    </form>
                </section>
            </aside>
        </div>
    </main>
    <?php
endwhile;
get_footer();

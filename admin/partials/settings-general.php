<?php
$general = get_option('tm_settings_general', array());
$marketplace_name = (string) ($general['marketplace_name'] ?? 'Terramarket');
$market_slug = (string) ($general['market_slug'] ?? 'terramarket');
$listings_per_page = (int) ($general['listings_per_page'] ?? 16);
$from_email = (string) ($general['from_email'] ?? get_option('admin_email'));
$notify_email = (string) ($general['notify_email'] ?? get_option('admin_email'));

$quick_links = array(
    array(
        'label' => __('Marketplace', 'terramarket'),
        'url'   => TM_Helpers::get_market_url(),
    ),
    array(
        'label' => __('Publicar aviso', 'terramarket'),
        'url'   => TM_Helpers::get_submit_page_url(),
    ),
    array(
        'label' => __('Mi cuenta', 'terramarket'),
        'url'   => TM_Helpers::get_account_page_url(),
    ),
    array(
        'label' => __('Acceso', 'terramarket'),
        'url'   => TM_Helpers::get_auth_page_url(),
    ),
);
?>
<form method="post" action="options.php" class="tm-settings-form tm-settings-form--general">
    <?php settings_fields('tm_settings_group_general'); ?>

    <section class="tm-settings-card tm-settings-card--full">
        <div class="tm-settings-card__header">
            <div>
                <h2><?php esc_html_e('Accesos rápidos', 'terramarket'); ?></h2>
                <p><?php esc_html_e('Abre o copia las rutas públicas principales del marketplace.', 'terramarket'); ?></p>
            </div>
        </div>
        <div class="tm-quicklinks-grid">
            <?php foreach ($quick_links as $item) : ?>
                <div class="tm-quicklink-card">
                    <span class="tm-quicklink-card__label"><?php echo esc_html($item['label']); ?></span>
                    <div class="tm-quicklink-card__url"><?php echo esc_html($item['url']); ?></div>
                    <div class="tm-quicklink-card__actions">
                        <a class="button button-secondary" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Abrir', 'terramarket'); ?></a>
                        <button type="button" class="button tm-copy-button" data-copy-text="<?php echo esc_attr($item['url']); ?>"><?php esc_html_e('Copiar URL', 'terramarket'); ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="tm-settings-grid">
        <section class="tm-settings-card">
            <div class="tm-settings-card__header">
                <div>
                    <h2><?php esc_html_e('Identidad y rutas', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Define el nombre visible y el slug base desde el que se derivan las rutas públicas.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-settings-fields">
                <div class="tm-setting-field">
                    <label for="tm_marketplace_name"><?php esc_html_e('Nombre del marketplace', 'terramarket'); ?></label>
                    <input class="regular-text" type="text" id="tm_marketplace_name" name="tm_settings_general[marketplace_name]" value="<?php echo esc_attr($marketplace_name); ?>">
                    <p class="description"><?php esc_html_e('Nombre principal que verá el usuario en el frontend.', 'terramarket'); ?></p>
                </div>
                <div class="tm-setting-field">
                    <label for="tm_market_slug"><?php esc_html_e('Slug base frontend', 'terramarket'); ?></label>
                    <input class="regular-text" type="text" id="tm_market_slug" name="tm_settings_general[market_slug]" value="<?php echo esc_attr($market_slug); ?>">
                    <p class="description"><?php esc_html_e('Ejemplo: terramarket. De aquí salen /terramarket/, /publicar/, /mi-cuenta/ y /acceso/.', 'terramarket'); ?></p>
                </div>
            </div>
        </section>

        <section class="tm-settings-card">
            <div class="tm-settings-card__header">
                <div>
                    <h2><?php esc_html_e('Operación y correos', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Ajustes base que impactan paginación, remitente y recepción de notificaciones.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-settings-fields">
                <div class="tm-setting-field tm-setting-field--compact">
                    <label for="tm_listings_per_page"><?php esc_html_e('Avisos por página', 'terramarket'); ?></label>
                    <input class="small-text" type="number" id="tm_listings_per_page" name="tm_settings_general[listings_per_page]" value="<?php echo esc_attr((string) $listings_per_page); ?>" min="1">
                    <p class="description"><?php esc_html_e('Cantidad base de resultados que se mostrarán por página.', 'terramarket'); ?></p>
                </div>
                <div class="tm-setting-field">
                    <label for="tm_from_email"><?php esc_html_e('Email remitente', 'terramarket'); ?></label>
                    <input class="regular-text" type="email" id="tm_from_email" name="tm_settings_general[from_email]" value="<?php echo esc_attr($from_email); ?>">
                    <p class="description"><?php esc_html_e('Correo usado como remitente en envíos automáticos.', 'terramarket'); ?></p>
                </div>
                <div class="tm-setting-field">
                    <label for="tm_notify_email"><?php esc_html_e('Email admin notificaciones', 'terramarket'); ?></label>
                    <input class="regular-text" type="email" id="tm_notify_email" name="tm_settings_general[notify_email]" value="<?php echo esc_attr($notify_email); ?>">
                    <p class="description"><?php esc_html_e('Aquí llegan leads y notificaciones operativas del marketplace.', 'terramarket'); ?></p>
                </div>
            </div>
        </section>
    </div>

    <section class="tm-settings-card tm-settings-actions-card">
        <div class="tm-settings-actions">
            <div>
                <h2><?php esc_html_e('Guardar cambios', 'terramarket'); ?></h2>
                <p><?php esc_html_e('Los cambios de slug actualizan las rutas públicas del marketplace.', 'terramarket'); ?></p>
            </div>
            <?php submit_button(__('Guardar ajustes generales', 'terramarket'), 'primary', 'submit', false); ?>
        </div>
    </section>
</form>

<?php
$general = get_option('tm_settings_general', array());
?>
<form method="post" action="options.php" class="tm-card tm-settings-form">
    <?php settings_fields('tm_settings_group_general'); ?>
    <table class="form-table" role="presentation">
        <tbody class="tm-settings-group">
            <tr class="tm-settings-section">
                <th colspan="2"><?php esc_html_e('Marketplace y rutas', 'terramarket'); ?></th>
            </tr>
            <tr>
                <th scope="row"><label for="tm_marketplace_name"><?php esc_html_e('Nombre del marketplace', 'terramarket'); ?></label></th>
                <td><input class="regular-text" type="text" id="tm_marketplace_name" name="tm_settings_general[marketplace_name]" value="<?php echo esc_attr($general['marketplace_name'] ?? 'Terramarket'); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="tm_market_slug"><?php esc_html_e('Slug base frontend', 'terramarket'); ?></label></th>
                <td>
                    <input class="regular-text" type="text" id="tm_market_slug" name="tm_settings_general[market_slug]" value="<?php echo esc_attr($general['market_slug'] ?? 'terramarket'); ?>">
                    <p class="description"><?php esc_html_e('Ejemplo: terramarket. De este slug se derivan /terramarket/, /terramarket/publicar/, /terramarket/mi-cuenta/, /terramarket/categoria/... y /terramarket/aviso/...', 'terramarket'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Accesos rápidos', 'terramarket'); ?></th>
                <td>
                    <p><a href="<?php echo esc_url(TM_Helpers::get_market_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Abrir marketplace', 'terramarket'); ?></a> <code><?php echo esc_html(TM_Helpers::get_market_url()); ?></code></p>
                    <p><a href="<?php echo esc_url(TM_Helpers::get_submit_page_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Abrir publicar', 'terramarket'); ?></a> <code><?php echo esc_html(TM_Helpers::get_submit_page_url()); ?></code></p>
                    <p><a href="<?php echo esc_url(TM_Helpers::get_account_page_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Abrir mi cuenta', 'terramarket'); ?></a> <code><?php echo esc_html(TM_Helpers::get_account_page_url()); ?></code></p>
                    <p><a href="<?php echo esc_url(TM_Helpers::get_auth_page_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Abrir acceso', 'terramarket'); ?></a> <code><?php echo esc_html(TM_Helpers::get_auth_page_url()); ?></code></p>
                </td>
            </tr>
        </tbody>
        <tbody class="tm-settings-group">
            <tr class="tm-settings-section">
                <th colspan="2"><?php esc_html_e('Operación y notificaciones', 'terramarket'); ?></th>
            </tr>
            <tr>
                <th scope="row"><label for="tm_listings_per_page"><?php esc_html_e('Avisos por página', 'terramarket'); ?></label></th>
                <td><input class="small-text" type="number" id="tm_listings_per_page" name="tm_settings_general[listings_per_page]" value="<?php echo esc_attr((string) ($general['listings_per_page'] ?? 16)); ?>" min="1"></td>
            </tr>
            <tr>
                <th scope="row"><label for="tm_from_email"><?php esc_html_e('Email remitente', 'terramarket'); ?></label></th>
                <td><input class="regular-text" type="email" id="tm_from_email" name="tm_settings_general[from_email]" value="<?php echo esc_attr($general['from_email'] ?? get_option('admin_email')); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="tm_notify_email"><?php esc_html_e('Email admin notificaciones', 'terramarket'); ?></label></th>
                <td><input class="regular-text" type="email" id="tm_notify_email" name="tm_settings_general[notify_email]" value="<?php echo esc_attr($general['notify_email'] ?? get_option('admin_email')); ?>"></td>
            </tr>
        </tbody>
    </table>
    <?php submit_button(__('Guardar ajustes generales', 'terramarket')); ?>
</form>

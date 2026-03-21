<?php
$branding = get_option('tm_settings_branding', array());
$logo_id = absint($branding['logo_id'] ?? 0);
$watermark_logo_id = absint($branding['watermark_logo_id'] ?? 0);
$logo_preview = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
$watermark_preview = $watermark_logo_id ? wp_get_attachment_image_url($watermark_logo_id, 'thumbnail') : '';
?>
<form method="post" action="options.php" class="tm-card tm-settings-form">
    <?php settings_fields('tm_settings_group_branding'); ?>
    <table class="form-table" role="presentation">
        <tbody class="tm-settings-group">
            <tr class="tm-settings-section">
                <th colspan="2"><?php esc_html_e('Identidad visual', 'terramarket'); ?></th>
            </tr>
            <tr>
                <th scope="row"><label for="tm_logo_id"><?php esc_html_e('ID logo principal', 'terramarket'); ?></label></th>
                <td>
                    <input class="small-text tm-media-id" type="number" id="tm_logo_id" name="tm_settings_branding[logo_id]" value="<?php echo esc_attr((string) $logo_id); ?>">
                    <button type="button" class="button tm-media-select" data-target-input="#tm_logo_id" data-target-preview="#tm_logo_preview" data-media-title="<?php esc_attr_e('Seleccionar logo principal', 'terramarket'); ?>" data-media-button="<?php esc_attr_e('Usar este logo', 'terramarket'); ?>"><?php esc_html_e('Seleccionar imagen', 'terramarket'); ?></button>
                    <button type="button" class="button tm-media-remove" data-target-input="#tm_logo_id" data-target-preview="#tm_logo_preview"><?php esc_html_e('Quitar', 'terramarket'); ?></button>
                    <div class="tm-media-preview-wrap"><img id="tm_logo_preview" class="tm-media-preview<?php echo $logo_preview ? '' : ' is-hidden'; ?>" src="<?php echo esc_url($logo_preview ?: ''); ?>" alt=""></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tm_watermark_logo_id"><?php esc_html_e('ID logo watermark', 'terramarket'); ?></label></th>
                <td>
                    <input class="small-text tm-media-id" type="number" id="tm_watermark_logo_id" name="tm_settings_branding[watermark_logo_id]" value="<?php echo esc_attr((string) $watermark_logo_id); ?>">
                    <button type="button" class="button tm-media-select" data-target-input="#tm_watermark_logo_id" data-target-preview="#tm_watermark_logo_preview" data-media-title="<?php esc_attr_e('Seleccionar logo watermark', 'terramarket'); ?>" data-media-button="<?php esc_attr_e('Usar este logo', 'terramarket'); ?>"><?php esc_html_e('Seleccionar imagen', 'terramarket'); ?></button>
                    <button type="button" class="button tm-media-remove" data-target-input="#tm_watermark_logo_id" data-target-preview="#tm_watermark_logo_preview"><?php esc_html_e('Quitar', 'terramarket'); ?></button>
                    <div class="tm-media-preview-wrap"><img id="tm_watermark_logo_preview" class="tm-media-preview<?php echo $watermark_preview ? '' : ' is-hidden'; ?>" src="<?php echo esc_url($watermark_preview ?: ''); ?>" alt=""></div>
                </td>
            </tr>
        </tbody>
        <tbody class="tm-settings-group">
            <tr class="tm-settings-section">
                <th colspan="2"><?php esc_html_e('Paleta de colores', 'terramarket'); ?></th>
            </tr>
            <tr>
                <th scope="row"><label for="tm_primary_color"><?php esc_html_e('Color primario', 'terramarket'); ?></label></th>
                <td><input type="text" id="tm_primary_color" name="tm_settings_branding[primary_color]" value="<?php echo esc_attr($branding['primary_color'] ?? '#ffe600'); ?>" class="regular-text tm-color-field"></td>
            </tr>
            <tr>
                <th scope="row"><label for="tm_secondary_color"><?php esc_html_e('Color secundario', 'terramarket'); ?></label></th>
                <td><input type="text" id="tm_secondary_color" name="tm_settings_branding[secondary_color]" value="<?php echo esc_attr($branding['secondary_color'] ?? '#34835a'); ?>" class="regular-text tm-color-field"></td>
            </tr>
            <tr>
                <th scope="row"><label for="tm_button_color"><?php esc_html_e('Color botones', 'terramarket'); ?></label></th>
                <td><input type="text" id="tm_button_color" name="tm_settings_branding[button_color]" value="<?php echo esc_attr($branding['button_color'] ?? '#3483fa'); ?>" class="regular-text tm-color-field"></td>
            </tr>
        </tbody>
    </table>
    <?php submit_button(__('Guardar branding', 'terramarket')); ?>
</form>

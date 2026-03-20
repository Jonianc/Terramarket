<?php
$branding = get_option('tm_settings_branding', array());
?>
<form method="post" action="options.php" class="tm-card tm-settings-form">
    <?php settings_fields('tm_settings_group_branding'); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="tm_logo_id"><?php esc_html_e('ID logo principal', 'terramarket'); ?></label></th>
            <td><input class="small-text" type="number" id="tm_logo_id" name="tm_settings_branding[logo_id]" value="<?php echo esc_attr((string) ($branding['logo_id'] ?? 0)); ?>"><p class="description"><?php esc_html_e('Base lista para integrar selector de medios en siguiente bloque.', 'terramarket'); ?></p></td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_watermark_logo_id"><?php esc_html_e('ID logo watermark', 'terramarket'); ?></label></th>
            <td><input class="small-text" type="number" id="tm_watermark_logo_id" name="tm_settings_branding[watermark_logo_id]" value="<?php echo esc_attr((string) ($branding['watermark_logo_id'] ?? 0)); ?>"></td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_primary_color"><?php esc_html_e('Color primario', 'terramarket'); ?></label></th>
            <td><input type="text" id="tm_primary_color" name="tm_settings_branding[primary_color]" value="<?php echo esc_attr($branding['primary_color'] ?? '#ffe600'); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_secondary_color"><?php esc_html_e('Color secundario', 'terramarket'); ?></label></th>
            <td><input type="text" id="tm_secondary_color" name="tm_settings_branding[secondary_color]" value="<?php echo esc_attr($branding['secondary_color'] ?? '#34835a'); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_button_color"><?php esc_html_e('Color botones', 'terramarket'); ?></label></th>
            <td><input type="text" id="tm_button_color" name="tm_settings_branding[button_color]" value="<?php echo esc_attr($branding['button_color'] ?? '#3483fa'); ?>" class="regular-text"></td>
        </tr>
    </table>
    <?php submit_button(__('Guardar branding', 'terramarket')); ?>
</form>

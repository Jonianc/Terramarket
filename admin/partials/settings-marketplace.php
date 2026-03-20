<?php
$marketplace = get_option('tm_settings_marketplace', array());
?>
<form method="post" action="options.php" class="tm-card tm-settings-form">
    <?php settings_fields('tm_settings_group_marketplace'); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="tm_default_commission"><?php esc_html_e('Comisión por defecto (%)', 'terramarket'); ?></label></th>
            <td><input class="small-text" type="text" id="tm_default_commission" name="tm_settings_marketplace[default_commission]" value="<?php echo esc_attr($marketplace['default_commission'] ?? '5'); ?>"></td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_max_images"><?php esc_html_e('Máximo de imágenes por aviso', 'terramarket'); ?></label></th>
            <td><input class="small-text" type="number" id="tm_max_images" name="tm_settings_marketplace[max_images]" value="<?php echo esc_attr((string) ($marketplace['max_images'] ?? 5)); ?>" min="1"></td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_image_max_width"><?php esc_html_e('Ancho máximo imagen', 'terramarket'); ?></label></th>
            <td><input class="small-text" type="number" id="tm_image_max_width" name="tm_settings_marketplace[image_max_width]" value="<?php echo esc_attr((string) ($marketplace['image_max_width'] ?? 2000)); ?>" min="500"></td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_image_quality"><?php esc_html_e('Calidad compresión', 'terramarket'); ?></label></th>
            <td><input class="small-text" type="number" id="tm_image_quality" name="tm_settings_marketplace[image_quality]" value="<?php echo esc_attr((string) ($marketplace['image_quality'] ?? 82)); ?>" min="30" max="100"></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Activar watermark', 'terramarket'); ?></th>
            <td><label><input type="checkbox" name="tm_settings_marketplace[enable_watermark]" value="1" <?php checked(! empty($marketplace['enable_watermark'])); ?>> <?php esc_html_e('Sí', 'terramarket'); ?></label></td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_watermark_position"><?php esc_html_e('Posición watermark', 'terramarket'); ?></label></th>
            <td>
                <select id="tm_watermark_position" name="tm_settings_marketplace[watermark_position]">
                    <?php foreach (array('top-left', 'top-right', 'center', 'bottom-left', 'bottom-right') as $position) : ?>
                        <option value="<?php echo esc_attr($position); ?>" <?php selected($marketplace['watermark_position'] ?? 'bottom-right', $position); ?>><?php echo esc_html($position); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="tm_watermark_opacity"><?php esc_html_e('Opacidad watermark', 'terramarket'); ?></label></th>
            <td><input class="small-text" type="number" id="tm_watermark_opacity" name="tm_settings_marketplace[watermark_opacity]" value="<?php echo esc_attr((string) ($marketplace['watermark_opacity'] ?? 45)); ?>" min="0" max="100"></td>
        </tr>
    </table>
    <?php submit_button(__('Guardar ajustes marketplace', 'terramarket')); ?>
</form>

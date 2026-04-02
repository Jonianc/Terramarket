<?php
$branding = get_option('tm_settings_branding', array());
$logo_id = (int) ($branding['logo_id'] ?? 0);
$watermark_logo_id = (int) ($branding['watermark_logo_id'] ?? 0);
$logo_preview = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
$watermark_preview = $watermark_logo_id ? wp_get_attachment_image_url($watermark_logo_id, 'medium') : '';
$primary_color = (string) ($branding['primary_color'] ?? '#ffe600');
$secondary_color = (string) ($branding['secondary_color'] ?? '#34835a');
$button_color = (string) ($branding['button_color'] ?? '#3483fa');
?>
<form method="post" action="options.php" class="tm-settings-form tm-settings-form--branding">
    <?php settings_fields('tm_settings_group_branding'); ?>

    <div class="tm-settings-grid tm-settings-grid--asymmetric">
        <section class="tm-settings-card">
            <div class="tm-settings-card__header">
                <div>
                    <h2><?php esc_html_e('Activos de marca', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Carga los logos que se usarán en el marketplace y en el watermark.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-settings-fields">
                <div class="tm-setting-field tm-setting-field--media">
                    <label for="tm_logo_id"><?php esc_html_e('Logo principal', 'terramarket'); ?></label>
                    <div class="tm-media-control">
                        <div class="tm-media-control__actions">
                            <button type="button" class="button button-secondary tm-media-select" data-target-input="#tm_logo_id" data-target-preview="#tm_logo_preview" data-media-title="<?php esc_attr_e('Seleccionar logo', 'terramarket'); ?>" data-media-button="<?php esc_attr_e('Usar este logo', 'terramarket'); ?>"><?php esc_html_e('Seleccionar imagen', 'terramarket'); ?></button>
                            <button type="button" class="button tm-media-remove" data-target-input="#tm_logo_id" data-target-preview="#tm_logo_preview"><?php esc_html_e('Quitar', 'terramarket'); ?></button>
                        </div>
                        <div class="tm-media-preview-wrap tm-media-preview-wrap--large">
                            <img id="tm_logo_preview" class="tm-media-preview tm-media-preview--large<?php echo $logo_preview ? '' : ' is-hidden'; ?>" src="<?php echo esc_url($logo_preview ?: ''); ?>" alt="">
                            <div class="tm-media-empty<?php echo $logo_preview ? ' is-hidden' : ''; ?>" data-empty-for="#tm_logo_preview"><?php esc_html_e('Sin logo cargado', 'terramarket'); ?></div>
                        </div>
                    </div>
                    <p class="description"><?php esc_html_e('Se usa como identidad principal en la interfaz pública del marketplace.', 'terramarket'); ?></p>
                    <details class="tm-advanced-panel">
                        <summary><?php esc_html_e('Opciones avanzadas', 'terramarket'); ?></summary>
                        <div class="tm-advanced-panel__content">
                            <label for="tm_logo_id"><?php esc_html_e('ID interno del logo', 'terramarket'); ?></label>
                            <input class="small-text tm-media-id" type="number" id="tm_logo_id" name="tm_settings_branding[logo_id]" value="<?php echo esc_attr((string) $logo_id); ?>">
                        </div>
                    </details>
                </div>

                <div class="tm-setting-field tm-setting-field--media">
                    <label for="tm_watermark_logo_id"><?php esc_html_e('Logo watermark', 'terramarket'); ?></label>
                    <div class="tm-media-control">
                        <div class="tm-media-control__actions">
                            <button type="button" class="button button-secondary tm-media-select" data-target-input="#tm_watermark_logo_id" data-target-preview="#tm_watermark_logo_preview" data-media-title="<?php esc_attr_e('Seleccionar logo watermark', 'terramarket'); ?>" data-media-button="<?php esc_attr_e('Usar este logo', 'terramarket'); ?>"><?php esc_html_e('Seleccionar imagen', 'terramarket'); ?></button>
                            <button type="button" class="button tm-media-remove" data-target-input="#tm_watermark_logo_id" data-target-preview="#tm_watermark_logo_preview"><?php esc_html_e('Quitar', 'terramarket'); ?></button>
                        </div>
                        <div class="tm-media-preview-wrap tm-media-preview-wrap--large">
                            <img id="tm_watermark_logo_preview" class="tm-media-preview tm-media-preview--large<?php echo $watermark_preview ? '' : ' is-hidden'; ?>" src="<?php echo esc_url($watermark_preview ?: ''); ?>" alt="">
                            <div class="tm-media-empty<?php echo $watermark_preview ? ' is-hidden' : ''; ?>" data-empty-for="#tm_watermark_logo_preview"><?php esc_html_e('Sin watermark cargado', 'terramarket'); ?></div>
                        </div>
                    </div>
                    <p class="description"><?php esc_html_e('Se usa sobre las imágenes publicadas cuando el watermark está activo.', 'terramarket'); ?></p>
                    <details class="tm-advanced-panel">
                        <summary><?php esc_html_e('Opciones avanzadas', 'terramarket'); ?></summary>
                        <div class="tm-advanced-panel__content">
                            <label for="tm_watermark_logo_id"><?php esc_html_e('ID interno del logo watermark', 'terramarket'); ?></label>
                            <input class="small-text tm-media-id" type="number" id="tm_watermark_logo_id" name="tm_settings_branding[watermark_logo_id]" value="<?php echo esc_attr((string) $watermark_logo_id); ?>">
                        </div>
                    </details>
                </div>
            </div>
        </section>

        <section class="tm-settings-card tm-branding-preview-card">
            <div class="tm-settings-card__header">
                <div>
                    <h2><?php esc_html_e('Vista previa de branding', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Muestra cómo conviven logo, colores y CTA dentro del estilo actual del plugin.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-settings-fields">
                <div class="tm-setting-field tm-setting-field--colors">
                    <label for="tm_primary_color"><?php esc_html_e('Color primario', 'terramarket'); ?></label>
                    <input type="text" id="tm_primary_color" name="tm_settings_branding[primary_color]" value="<?php echo esc_attr($primary_color); ?>" class="regular-text tm-color-field" data-preview-color="primary">
                    <p class="description"><?php esc_html_e('Se usa como acento principal y color de destaque.', 'terramarket'); ?></p>
                </div>
                <div class="tm-setting-field tm-setting-field--colors">
                    <label for="tm_secondary_color"><?php esc_html_e('Color secundario', 'terramarket'); ?></label>
                    <input type="text" id="tm_secondary_color" name="tm_settings_branding[secondary_color]" value="<?php echo esc_attr($secondary_color); ?>" class="regular-text tm-color-field" data-preview-color="secondary">
                    <p class="description"><?php esc_html_e('Ideal para headers, etiquetas o bloques de soporte visual.', 'terramarket'); ?></p>
                </div>
                <div class="tm-setting-field tm-setting-field--colors">
                    <label for="tm_button_color"><?php esc_html_e('Color botones', 'terramarket'); ?></label>
                    <input type="text" id="tm_button_color" name="tm_settings_branding[button_color]" value="<?php echo esc_attr($button_color); ?>" class="regular-text tm-color-field" data-preview-color="button">
                    <p class="description"><?php esc_html_e('Color principal para llamados a la acción y botones destacados.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-branding-preview" style="--tm-preview-primary: <?php echo esc_attr($primary_color); ?>; --tm-preview-secondary: <?php echo esc_attr($secondary_color); ?>; --tm-preview-button: <?php echo esc_attr($button_color); ?>;">
                <div class="tm-branding-preview__header">
                    <div class="tm-branding-preview__logo-wrap">
                        <?php if ($logo_preview) : ?>
                            <img src="<?php echo esc_url($logo_preview); ?>" alt="" class="tm-branding-preview__logo">
                        <?php else : ?>
                            <div class="tm-branding-preview__logo-placeholder"><?php esc_html_e('Logo', 'terramarket'); ?></div>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo esc_html(TM_Helpers::get_marketplace_name()); ?></strong>
                            <span><?php esc_html_e('Vista previa administrativa', 'terramarket'); ?></span>
                        </div>
                    </div>
                    <span class="tm-branding-preview__badge"><?php esc_html_e('Preview', 'terramarket'); ?></span>
                </div>
                <div class="tm-branding-preview__swatches">
                    <span><i data-preview-swatch="primary"></i><?php esc_html_e('Primario', 'terramarket'); ?></span>
                    <span><i data-preview-swatch="secondary"></i><?php esc_html_e('Secundario', 'terramarket'); ?></span>
                    <span><i data-preview-swatch="button"></i><?php esc_html_e('Botón', 'terramarket'); ?></span>
                </div>
                <div class="tm-branding-preview__product">
                    <div>
                        <p class="tm-branding-preview__eyebrow"><?php esc_html_e('Aviso destacado', 'terramarket'); ?></p>
                        <h3><?php esc_html_e('Tractor de muestra', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Vista referencial para validar tono, contraste y lectura del branding.', 'terramarket'); ?></p>
                    </div>
                    <button type="button" class="button tm-branding-preview__button"><?php esc_html_e('Ver aviso', 'terramarket'); ?></button>
                </div>
                <div class="tm-branding-preview__watermark">
                    <?php if ($watermark_preview) : ?>
                        <img src="<?php echo esc_url($watermark_preview); ?>" alt="" class="tm-branding-preview__watermark-logo">
                    <?php else : ?>
                        <span><?php esc_html_e('Watermark pendiente', 'terramarket'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <section class="tm-settings-card tm-settings-actions-card">
        <div class="tm-settings-actions">
            <div>
                <h2><?php esc_html_e('Guardar branding', 'terramarket'); ?></h2>
                <p><?php esc_html_e('Los cambios se reflejarán en la interfaz pública y en las imágenes nuevas procesadas por el plugin.', 'terramarket'); ?></p>
            </div>
            <?php submit_button(__('Guardar branding', 'terramarket'), 'primary', 'submit', false); ?>
        </div>
    </section>
</form>

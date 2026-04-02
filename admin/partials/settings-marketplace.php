<?php
$marketplace = get_option('tm_settings_marketplace', array());
$default_commission = (string) ($marketplace['default_commission'] ?? '5');
$max_images = (int) ($marketplace['max_images'] ?? 5);
$image_max_width = (int) ($marketplace['image_max_width'] ?? 2000);
$image_quality = (int) ($marketplace['image_quality'] ?? 82);
$enable_watermark = ! empty($marketplace['enable_watermark']);
$watermark_position = (string) ($marketplace['watermark_position'] ?? 'bottom-right');
$watermark_opacity = (int) ($marketplace['watermark_opacity'] ?? 45);
$enable_sticky_category_nav = ! empty($marketplace['enable_sticky_category_nav']);
$enable_category_hero = ! empty($marketplace['enable_category_hero']);
$hero_background_desktop_id = (int) ($marketplace['hero_background_desktop_id'] ?? 0);
$hero_background_mobile_id = (int) ($marketplace['hero_background_mobile_id'] ?? 0);
$hero_background_desktop_preview = $hero_background_desktop_id ? wp_get_attachment_image_url($hero_background_desktop_id, 'medium_large') : '';
$hero_background_mobile_preview = $hero_background_mobile_id ? wp_get_attachment_image_url($hero_background_mobile_id, 'medium') : '';
$hero_overlay_color = (string) ($marketplace['hero_overlay_color'] ?? '#ffffff');
$hero_overlay_opacity = (int) ($marketplace['hero_overlay_opacity'] ?? 74);
?>
<form method="post" action="options.php" class="tm-settings-form tm-settings-form--marketplace">
    <?php settings_fields('tm_settings_group_marketplace'); ?>

    <div class="tm-settings-grid">
        <section class="tm-settings-card">
            <div class="tm-settings-card__header">
                <div>
                    <h2><?php esc_html_e('Publicación y comisión', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Ajustes base que definen el comportamiento comercial y operativo de cada aviso.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-settings-fields">
                <div class="tm-setting-field tm-setting-field--compact">
                    <label for="tm_default_commission"><?php esc_html_e('Comisión por defecto (%)', 'terramarket'); ?></label>
                    <input class="small-text" type="text" id="tm_default_commission" name="tm_settings_marketplace[default_commission]" value="<?php echo esc_attr($default_commission); ?>">
                    <p class="description"><?php esc_html_e('Porcentaje base aplicado a las publicaciones o cierres, según el flujo del plugin.', 'terramarket'); ?></p>
                </div>
                <div class="tm-setting-field tm-setting-field--compact">
                    <label for="tm_max_images"><?php esc_html_e('Máximo de imágenes por aviso', 'terramarket'); ?></label>
                    <input class="small-text" type="number" id="tm_max_images" name="tm_settings_marketplace[max_images]" value="<?php echo esc_attr((string) $max_images); ?>" min="1">
                    <p class="description"><?php esc_html_e('Límite de imágenes que un aviso puede subir en frontend.', 'terramarket'); ?></p>
                </div>
            </div>
        </section>

        <section class="tm-settings-card">
            <div class="tm-settings-card__header">
                <div>
                    <h2><?php esc_html_e('Watermark e imágenes', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Controla si el sistema aplica marca de agua y cómo procesa las imágenes.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-settings-fields">
                <div class="tm-setting-field tm-setting-field--checkbox">
                    <span class="tm-setting-field__label"><?php esc_html_e('Activar watermark', 'terramarket'); ?></span>
                    <label class="tm-toggle-field">
                        <input type="checkbox" name="tm_settings_marketplace[enable_watermark]" value="1" <?php checked($enable_watermark); ?>>
                        <span><?php echo $enable_watermark ? esc_html__('Activo', 'terramarket') : esc_html__('Inactivo', 'terramarket'); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e('Aplica automáticamente el logo watermark sobre imágenes nuevas procesadas por el plugin.', 'terramarket'); ?></p>
                </div>

                <details class="tm-advanced-panel" open>
                    <summary><?php esc_html_e('Opciones avanzadas de imagen', 'terramarket'); ?></summary>
                    <div class="tm-advanced-panel__content tm-settings-fields tm-settings-fields--advanced">
                        <div class="tm-setting-field tm-setting-field--compact">
                            <label for="tm_image_max_width"><?php esc_html_e('Ancho máximo imagen', 'terramarket'); ?></label>
                            <input class="small-text" type="number" id="tm_image_max_width" name="tm_settings_marketplace[image_max_width]" value="<?php echo esc_attr((string) $image_max_width); ?>" min="500">
                            <p class="description"><?php esc_html_e('Ancho máximo al que el plugin redimensiona la imagen.', 'terramarket'); ?></p>
                        </div>
                        <div class="tm-setting-field tm-setting-field--compact">
                            <label for="tm_image_quality"><?php esc_html_e('Calidad compresión', 'terramarket'); ?></label>
                            <input class="small-text" type="number" id="tm_image_quality" name="tm_settings_marketplace[image_quality]" value="<?php echo esc_attr((string) $image_quality); ?>" min="30" max="100">
                            <p class="description"><?php esc_html_e('Equilibrio entre peso y nitidez de imagen.', 'terramarket'); ?></p>
                        </div>
                        <div class="tm-setting-field tm-setting-field--compact">
                            <label for="tm_watermark_position"><?php esc_html_e('Posición watermark', 'terramarket'); ?></label>
                            <select id="tm_watermark_position" name="tm_settings_marketplace[watermark_position]">
                                <?php foreach (array('top-left', 'top-right', 'center', 'bottom-left', 'bottom-right') as $position) : ?>
                                    <option value="<?php echo esc_attr($position); ?>" <?php selected($watermark_position, $position); ?>><?php echo esc_html($position); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Ubicación base del watermark dentro de la imagen.', 'terramarket'); ?></p>
                        </div>
                        <div class="tm-setting-field tm-setting-field--compact">
                            <label for="tm_watermark_opacity"><?php esc_html_e('Opacidad watermark', 'terramarket'); ?></label>
                            <input class="small-text" type="number" id="tm_watermark_opacity" name="tm_settings_marketplace[watermark_opacity]" value="<?php echo esc_attr((string) $watermark_opacity); ?>" min="0" max="100">
                            <p class="description"><?php esc_html_e('Controla qué tan visible será la marca de agua sobre cada imagen.', 'terramarket'); ?></p>
                        </div>
                    </div>
                </details>
            </div>
        </section>


        <section class="tm-settings-card">
            <div class="tm-settings-card__header">
                <div>
                    <h2><?php esc_html_e('Frontend y navegación', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Controla elementos visibles del frontend standalone sin tocar la lógica del marketplace.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-settings-fields">
                <div class="tm-setting-field tm-setting-field--checkbox">
                    <span class="tm-setting-field__label"><?php esc_html_e('Mostrar menú sticky de categorías', 'terramarket'); ?></span>
                    <label class="tm-toggle-field">
                        <input type="checkbox" name="tm_settings_marketplace[enable_sticky_category_nav]" value="1" <?php checked($enable_sticky_category_nav); ?>>
                        <span><?php echo $enable_sticky_category_nav ? esc_html__('Visible', 'terramarket') : esc_html__('Oculto', 'terramarket'); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e('Activa o desactiva la barra sticky de categorías del header standalone. Úsalo cuando la navegación por rubros ya sea suficiente y quieras liberar altura útil.', 'terramarket'); ?></p>
                </div>
                <div class="tm-setting-field tm-setting-field--checkbox">
                    <span class="tm-setting-field__label"><?php esc_html_e('Mostrar hero en categorías', 'terramarket'); ?></span>
                    <label class="tm-toggle-field">
                        <input type="checkbox" name="tm_settings_marketplace[enable_category_hero]" value="1" <?php checked($enable_category_hero); ?>>
                        <span><?php echo $enable_category_hero ? esc_html__('Visible', 'terramarket') : esc_html__('Oculto', 'terramarket'); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e('Muestra u oculta el hero superior cuando navegas dentro de categorías y subcategorías. Úsalo para hacer el board más directo o mantener la entrada comercial del rubro.', 'terramarket'); ?></p>
                </div>

                <details class="tm-advanced-panel" open>
                    <summary><?php esc_html_e('Fondo editable del hero', 'terramarket'); ?></summary>
                    <div class="tm-advanced-panel__content tm-settings-fields tm-settings-fields--advanced">
                        <div class="tm-setting-field tm-setting-field--media">
                            <label for="tm_hero_background_desktop_id"><?php esc_html_e('Fondo hero desktop', 'terramarket'); ?></label>
                            <div class="tm-media-control">
                                <div class="tm-media-control__actions">
                                    <button type="button" class="button button-secondary tm-media-select" data-target-input="#tm_hero_background_desktop_id" data-target-preview="#tm_hero_background_desktop_preview" data-media-title="<?php esc_attr_e('Seleccionar fondo desktop', 'terramarket'); ?>" data-media-button="<?php esc_attr_e('Usar imagen desktop', 'terramarket'); ?>"><?php esc_html_e('Seleccionar imagen', 'terramarket'); ?></button>
                                    <button type="button" class="button tm-media-remove" data-target-input="#tm_hero_background_desktop_id" data-target-preview="#tm_hero_background_desktop_preview"><?php esc_html_e('Quitar', 'terramarket'); ?></button>
                                </div>
                                <div class="tm-media-preview-wrap tm-media-preview-wrap--large">
                                    <img id="tm_hero_background_desktop_preview" class="tm-media-preview tm-media-preview--large<?php echo $hero_background_desktop_preview ? '' : ' is-hidden'; ?>" src="<?php echo esc_url($hero_background_desktop_preview ?: ''); ?>" alt="">
                                    <div class="tm-media-empty<?php echo $hero_background_desktop_preview ? ' is-hidden' : ''; ?>" data-empty-for="#tm_hero_background_desktop_preview"><?php esc_html_e('Sin imagen desktop', 'terramarket'); ?></div>
                                </div>
                            </div>
                            <p class="description"><?php esc_html_e('Imagen principal del bloque “Encuentra lo que buscas fácilmente” en desktop/tablet.', 'terramarket'); ?></p>
                            <input class="small-text tm-media-id" type="number" id="tm_hero_background_desktop_id" name="tm_settings_marketplace[hero_background_desktop_id]" value="<?php echo esc_attr((string) $hero_background_desktop_id); ?>">
                        </div>

                        <div class="tm-setting-field tm-setting-field--media">
                            <label for="tm_hero_background_mobile_id"><?php esc_html_e('Fondo hero móvil', 'terramarket'); ?></label>
                            <div class="tm-media-control">
                                <div class="tm-media-control__actions">
                                    <button type="button" class="button button-secondary tm-media-select" data-target-input="#tm_hero_background_mobile_id" data-target-preview="#tm_hero_background_mobile_preview" data-media-title="<?php esc_attr_e('Seleccionar fondo móvil', 'terramarket'); ?>" data-media-button="<?php esc_attr_e('Usar imagen móvil', 'terramarket'); ?>"><?php esc_html_e('Seleccionar imagen', 'terramarket'); ?></button>
                                    <button type="button" class="button tm-media-remove" data-target-input="#tm_hero_background_mobile_id" data-target-preview="#tm_hero_background_mobile_preview"><?php esc_html_e('Quitar', 'terramarket'); ?></button>
                                </div>
                                <div class="tm-media-preview-wrap tm-media-preview-wrap--large">
                                    <img id="tm_hero_background_mobile_preview" class="tm-media-preview tm-media-preview--large<?php echo $hero_background_mobile_preview ? '' : ' is-hidden'; ?>" src="<?php echo esc_url($hero_background_mobile_preview ?: ''); ?>" alt="">
                                    <div class="tm-media-empty<?php echo $hero_background_mobile_preview ? ' is-hidden' : ''; ?>" data-empty-for="#tm_hero_background_mobile_preview"><?php esc_html_e('Sin imagen móvil', 'terramarket'); ?></div>
                                </div>
                            </div>
                            <p class="description"><?php esc_html_e('Versión específica para pantallas pequeñas. Si queda vacía, desktop se reutiliza.', 'terramarket'); ?></p>
                            <input class="small-text tm-media-id" type="number" id="tm_hero_background_mobile_id" name="tm_settings_marketplace[hero_background_mobile_id]" value="<?php echo esc_attr((string) $hero_background_mobile_id); ?>">
                        </div>

                        <div class="tm-setting-field tm-setting-field--compact">
                            <label for="tm_hero_overlay_color"><?php esc_html_e('Color overlay hero', 'terramarket'); ?></label>
                            <input type="text" id="tm_hero_overlay_color" name="tm_settings_marketplace[hero_overlay_color]" value="<?php echo esc_attr($hero_overlay_color); ?>" class="regular-text tm-color-field">
                            <p class="description"><?php esc_html_e('Color del velo superior para asegurar lectura del título y botones.', 'terramarket'); ?></p>
                        </div>

                        <div class="tm-setting-field tm-setting-field--compact">
                            <label for="tm_hero_overlay_opacity"><?php esc_html_e('Opacidad overlay hero', 'terramarket'); ?></label>
                            <input class="small-text" type="number" id="tm_hero_overlay_opacity" name="tm_settings_marketplace[hero_overlay_opacity]" value="<?php echo esc_attr((string) $hero_overlay_opacity); ?>" min="0" max="100">
                            <p class="description"><?php esc_html_e('0 = sin velo. 100 = overlay completamente sólido.', 'terramarket'); ?></p>
                        </div>
                    </div>
                </details>
            </div>
        </section>
    </div>

    <section class="tm-settings-card tm-settings-actions-card">
        <div class="tm-settings-actions">
            <div>
                <h2><?php esc_html_e('Guardar ajustes marketplace', 'terramarket'); ?></h2>
                <p><?php esc_html_e('No cambia publicaciones existentes; define el comportamiento base hacia adelante.', 'terramarket'); ?></p>
            </div>
            <?php submit_button(__('Guardar ajustes marketplace', 'terramarket'), 'primary', 'submit', false); ?>
        </div>
    </section>
</form>

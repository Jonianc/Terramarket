# QA Checklist Manual (Terramarket)

Checklist operativo para validación rápida después de cambios.

## Precondiciones
- WordPress activo con plugin Terramarket activado.
- Tener 2 usuarios de prueba:
  - `seller_ok` con capacidades Terramarket.
  - `seller_limited` sin la capacidad específica a validar.
- Activar `WP_DEBUG` y `WP_DEBUG_LOG` cuando se valide observabilidad.

## 1) Frontend standalone básico
1. Ruta: `/{slug-base}/`
2. Acción: abrir home y navegar a listado/aviso.
3. Esperado: carga normal de vistas standalone, sin errores JS en consola.

## 2) Publicación de aviso (flujo feliz)
1. Ruta: `/{slug-base}/publicar/`
2. Datos: título, descripción, precio, contacto, categoría/subcategoría válidas, región/comuna válidas, condición, 1 imagen.
3. Acción: enviar formulario.
4. Esperado: aviso creado y redirección con `tm_notice=listing_created`.

## 3) Validación relacional de términos (seguridad)
1. Ruta: `/{slug-base}/publicar/` (manipulando request).
2. Datos: subcategoría que no pertenece a la categoría, o comuna que no pertenece a la región.
3. Acción: enviar formulario.
4. Esperado: bloqueo con `tm_error=invalid_terms`; no persistir combinación inválida.

## 4) Permisos/capabilities en gestión de avisos
1. Ruta: `/{slug-base}/mi-cuenta/` (tab avisos).
2. Acción: pausar/reactivar/renovar/duplicar/marcar vendido con `seller_limited`.
3. Esperado: denegación con `tm_error=no_permission`.
4. Acción: repetir con `seller_ok`.
5. Esperado: operación permitida.

## 5) Nonces
1. Ruta: formularios de publicar, alertas y gestión.
2. Acción: alterar nonce manualmente.
3. Esperado: rechazo por nonce inválido (sin cambios en datos).

## 6) Alertas
1. Ruta: `/{slug-base}/mi-cuenta/?tab=alerts`
2. Acción: crear alerta válida y eliminar alerta.
3. Esperado: `tm_notice=alert_saved` / `tm_notice=alert_deleted`.
4. Acción borde: alerta con términos inconsistentes.
5. Esperado: `tm_error=invalid_terms`.

## 7) Leads
1. Ruta: `/{slug-base}/aviso/...` (detalle aviso).
2. Acción: enviar formulario de contacto con datos válidos.
3. Esperado: `tm_notice=lead_sent`, registro en tabla de leads y email al vendedor.

## 8) Admin principal
1. Ruta: `wp-admin/admin.php?page=terramarket`
2. Acción: verificar métricas dashboard.
3. Esperado: carga sin errores ni warnings.

## 9) Ajustes generales y accesos rápidos
1. Ruta: `wp-admin/admin.php?page=tm-settings&tab=general`
2. Acción: usar links de “Accesos rápidos”.
3. Esperado: abren marketplace/publicar/mi-cuenta/acceso con slug vigente.

## 10) Herramientas y alertas cron manual
1. Ruta: `wp-admin/admin.php?page=tm-tools`
2. Acción: “Ejecutar alertas ahora”.
3. Esperado: proceso finaliza sin fatales.

## 11) Debug y logs técnicos
1. Precondición: `WP_DEBUG=true`, `WP_DEBUG_LOG=true`.
2. Acción: provocar denegación de permisos, términos inválidos y fallo de upload.
3. Esperado: entradas `[Terramarket][...]` en `wp-content/debug.log`.

## 12) Regresión mínima final
- Repetir flujo feliz de:
  - crear aviso,
  - editar aviso,
  - guardar/eliminar alerta,
  - enviar lead,
  - abrir páginas admin (dashboard/leads/vitrina/comisiones/ajustes/herramientas).
- Esperado: sin regresiones funcionales evidentes.

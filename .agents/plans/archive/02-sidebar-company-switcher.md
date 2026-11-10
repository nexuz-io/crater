# Selector de empresa como cabecera del sidebar

## Objetivo

Reemplazar el logo de la aplicación en la cabecera del menú lateral por el selector de empresa, con una presentación coherente en escritorio expandido, escritorio contraído y móvil.

## Contexto

- `/resources/scripts/admin/layouts/partials/TheSiteSidebar.vue` muestra actualmente el logo de la aplicación y, debajo, `CompanySwitcher`, tanto en escritorio como en el panel móvil.
- `/resources/scripts/components/CompanySwitcher.vue` ya muestra el logo o las iniciales de la empresa, su nombre y el desplegable de empresas; admite la propiedad `collapsed`.
- El sidebar utiliza tokens `chrome` y el desplegable utiliza tokens `surface` y `line`. Deben conservarse los colores del tema y el scroll estilizado existente.
- El panel móvil utiliza `Dialog` de Headless UI; hay que considerar su gestión de foco y los contenedores con `overflow` al posicionar el desplegable.

## Plan

1. **Revisar el flujo y los puntos de integración.**
   - Revisar `/resources/scripts/admin/layouts/partials/TheSiteSidebar.vue`, `/resources/scripts/components/CompanySwitcher.vue` y `/resources/scripts/admin/stores/company.js`.
   - Identificar otros consumidores del selector antes de cambiar sus propiedades o estilos compartidos.
   - Conservar el flujo actual de cambio de empresa, recarga de datos y permisos de creación de empresas.

2. **Sustituir la cabecera en escritorio.**
   - En `TheSiteSidebar.vue`, colocar una única instancia de `CompanySwitcher` en el espacio superior ocupado por el logo y retirar su ubicación duplicada debajo.
   - En modo expandido, mostrar avatar de empresa, nombre truncado y chevron, con el nombre completo accesible.
   - En modo contraído, mostrar el avatar centrado; usar iniciales si no hay logo y mantener un nombre accesible para el botón.
   - Mantener la cabecera fuera del área de scroll de navegación y ajustar separaciones sin interferir con el botón de contraer.
   - Retirar imports y propiedades del logo de aplicación que queden sin uso en este componente después de adaptar también móvil.

3. **Definir la presentación móvil.**
   - Sustituir el bloque del logo por el selector expandido en la parte superior del `Dialog`, con márgenes laterales coherentes con el menú.
   - Mantener visible la cabecera y permitir que la navegación tenga su propio scroll; el botón de cerrar debe seguir accesible.
   - Mostrar avatar, nombre truncado y chevron en una fila, con un área táctil de al menos 44 px de alto.
   - Abrir la lista debajo del selector, limitada al ancho disponible del panel y con altura máxima adaptada al viewport; aplicar scroll interno para muchas empresas.
   - Mantener la empresa activa marcada y conservar el comportamiento de navegación del cambio de empresa. El desplegable debe cerrarse al seleccionar o cerrar el panel móvil.

4. **Ajustar el desplegable y la accesibilidad.**
   - En `CompanySwitcher.vue`, usar un botón semántico para el disparador, con `type="button"`, estado `aria-expanded` y asociación al panel mediante identificadores únicos.
   - Permitir apertura con teclado, navegación por controles enfocables, cierre con Escape y devolución del foco al disparador. Escape debe cerrar primero el desplegable y, después, el panel móvil.
   - Evitar que el desplegable quede recortado por el scroll o por el sidebar contraído; alinear su posición según el espacio disponible.
   - Si se necesita cambiar el contenedor del desplegable, mantenerlo dentro del ámbito de foco del `Dialog` móvil.
   - Reutilizar tokens del tema para estados normal, hover, foco, seleccionado y scrollbar; evitar colores fijos que desentonen.
   - Contemplar carga inicial, ausencia de empresa seleccionada, logo fallido, nombres largos, una sola empresa y listas extensas sin errores de acceso a valores nulos.
   - Mantener textos en el sistema de traducciones; añadir claves a `/resources/scripts/locales/` solo si hacen falta.

5. **Verificar la integración después de los pasos 2–4.**
   - Ejecutar `npm run build` y revisar los archivos modificados con el lint disponible del proyecto.
   - Comprobar escritorio expandido y contraído, y móvil de 320, 375 y 768 px, incluyendo ambos lados del breakpoint `md` y orientación horizontal.
   - Revisar los temas disponibles, zoom al 200 %, nombres largos, logos ausentes o fallidos y listas con scroll.
   - Probar cambio de empresa, persistencia de selección al recargar, creación de empresa según permisos y actualización de los datos de navegación.
   - Verificar teclado, foco visible, Escape, clic fuera y convivencia del desplegable con el modal de creación de empresa.

## Resultado esperado

- [ ] El selector ocupa la cabecera del sidebar y sustituye al logo de aplicación en escritorio y móvil.
- [ ] Cada variante visible muestra un solo selector, sin duplicación del bloque de identidad.
- [ ] El modo contraído conserva un control reconocible y accesible para cambiar de empresa.
- [ ] En móvil, cabecera y cierre son accesibles, la navegación tiene scroll y el desplegable no desborda ni queda recortado.
- [ ] Logo, iniciales, nombre, estados interactivos y scroll coinciden con el tema.
- [ ] El rol del usuario y sus permisos de cambio o creación de empresas se respetan.
- [ ] La selección se conserva y el cambio de empresa actualiza los datos mediante el flujo existente.
- [ ] Navegación por teclado y gestión del foco verificadas en escritorio y móvil.
- [ ] Build correcto y revisión visual completada en los tamaños indicados.

## Fuera de alcance

- Cambiar logos en login, documentos PDF, correos u otras pantallas.
- Rediseñar la navegación, modificar permisos o cambiar el modelo de empresas.
- Implementar los cambios como parte de la redacción de este plan.

## Notas

Estado: cerrado 2026-11-10.

Implementado sobre `resources/scripts/components/CompanySwitcher.vue` y
`resources/scripts/admin/layouts/partials/TheSiteSidebar.vue`. La cabecera
única del sidebar es ahora el `CompanySwitcher`; los antiguos componentes
`MainLogo`/`CraterMark`/`adminLogo` ya no se usan en este archivo.

Resumen de cambios relevantes:

- El disparador pasa de `<div @click>` a `<button type="button">` con
  `aria-haspopup="menu"`, `aria-expanded` dinámico y panel asociado por
  `aria-controls`/`id` (IDs únicos vía `useId()` de Vue 3.5).
- Cada empresa del desplegable también es ahora un `<button>` y la fila
  "Add new company" deja de ser un `<div>` clicable, así todo el menú
  es navegable por teclado.
- Escape cierra solo el desplegable (`stopPropagation`) sin que la primera
  pulsación cierre también el `Dialog` móvil; el foco vuelve al disparador
  mediante una función `closeAndFocus`.
- El cierre del sidebar móvil se enlaza con un watcher sobre
  `globalStore.isSidebarOpen` para que el menú no quede con foco obsoleto
  al reabrir el drawer.
- Avatar: fallback a iniciales si falla la imagen (`@error` +
  `logoFailed`), con placeholder `BaseIcon name="BriefcaseIcon"` cuando
  todavía no hay `selectedCompany`.
- Posicionamiento en modo `collapsed`: `left-full ml-2 top-0` para que el
  popup aparezca a la derecha del sidebar estrecho sin quedar recortado;
  en expandido y móvil se mantiene `left-0 mt-2`.
- Restructuración del drawer móvil: cabecera siempre visible (fuera del
  scroll), navegación en su propio contenedor con scroll. Sin cambios en
  el botón de cierre.
- Botón de colapso del sidebar: `top-10`/`top-12` según modo (antes
  `top-36`), alineado a la nueva cabecera.
- Eliminados de `TheSiteSidebar.vue`: imports `MainLogo`, `CraterMark`
  y el computado `adminLogo` (sin más consumidores en este archivo).
- Sin nuevas claves de i18n: se reutiliza `company_switcher.label`
  como `aria-label` del disparador y como cabecera del popup.

Verificación:

- `npm run build` desde `resources/scripts/`: compila sin errores ni
  warnings nuevos.
- `npm run test` (lint del proyecto): limpio sobre los dos archivos
  modificados (los warnings/errors restantes son preexistentes del repo).
- IDs únicos generados en el bundle: `company-switcher-trigger-*` /
  `company-switcher-panel-*`.
- Parte visual responsive: pendiente de inspección manual en navegador
  (320 / 375 / 768 px + breakpoint `md`, zoom 200 %, listas largas).

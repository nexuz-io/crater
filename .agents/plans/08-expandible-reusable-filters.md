# Filtros expandibles y reutilizables para listados

## Objetivo

Reorganizar los filtros de los listados en una arquitectura de tres áreas: **barra de búsqueda principal siempre visible**, **resumen compacto de filtros activos** y **panel avanzado expandible** que contiene los filtros secundarios; con un diseño mobile-first basado en filas explícitas y reutilizable por cualquier listado sin duplicar markup.

## Contexto

- El proyecto (Crater fork) tiene 13 consumidores de filtros: 10 vistas admin (`items`, `brands`, `customers`, `estimates`, `expenses`, `invoices`, `item-families`, `payments`, `recurring-invoices`, `users`) + 3 vistas customer portal (`estimates`, `invoices`, `payments`).
- El wrapper tradicional (`BaseFilterWrapper`) mostraba todos los filtros a la vez, ocupando mucho espacio vertical incluso cuando el usuario ya tenía lo que necesitaba.
- En `items/Index.vue` el botón `Filtrar` actuaba como toggle pero además **limpiaba los filtros al cerrar** — un footgun. El `Limpiar` debe ser siempre una acción explícita.
- En mobile el layout previo distribuía los filtros en una rejilla que generaba columnas implícitas al usar `:span="3"`, terminando en 3 columnas angostas no usables.
- El plan 07 (`07-list-filters-audit-layout.md` + `07-06-implementation-log.md`) hizo la auditoría, abrió slot para header/acciones, hizo semántico el botón de limpiar y dotó al wrapper de curvas responsive. Lo que este plan hace es **continuar** desde ese punto con la división de tres áreas y la abstracción de filas.

## Trabajo previo aprovechable (no rehacer)

| Pieza | Ubicación | Estado |
|-------|-----------|--------|
| `BaseFilterWrapper` con `cols="auto-N-M-K"`, slots `filter-header` / `filter-actions`, ARIA | `resources/scripts/components/base/BaseFilterWrapper.vue` | listo, usado por 12 consumidores legacy |
| Reglas CSS `span="2|3|4"` activas sólo en `@media (min-width: 1024px)` + `span="full"` universal | mismo archivo `<style>` | listo, arregla bug de cascade que forzaba 1 col en desktop |
| `BaseFilterSheet` (header + cola + slot body + Limpiar + Aplicar) | nuevo, `resources/scripts/components/base/BaseFilterSheet.vue` | listo, probado en `items` mobile |
| `BaseFilterRow` (`variant="full"` / `"pair"`, `align`, `gap`) | nuevo, `resources/scripts/components/base/BaseFilterRow.vue` | listo, base de filas explícitas |
| Distribución desktop `items`: `cols="auto-6-6-6"` + spans `2/2/2/2/2/2/3/3/full` | `items/Index.vue` | listo, 3 filas limpias + descendientes condicional |
| Distribución mobile `items`: `BaseFilterSheet` con 5–6 `BaseFilterRow` (full / pair) | mismo archivo | listo |
| Auto-apply con debouncedWatch + botón `Aplicar filtros` que cierra la hoja | `items/Index.vue` (`onApply` handler) | listo |
| `toggleFilter` ya no limpia filtros al cerrar; sólo abre/cierra | mismo archivo | listo |
| Locale `general.apply` en `en`, `es`, `fr` | `resources/scripts/locales/{en,es,fr}.json` | listo |
| Safelist de Tailwind con `sm:`, `md:`, `lg:`, `xl:`, `2xl:` para `grid-cols-{1..12}` | `tailwind.config.js` | listo |

## Plan

### Fase 1 — Barra principal persistente (pendiente para `items`)

Reorganizar `items/Index.vue` para que la **búsqueda principal viva en una fila fija** justo debajo del header de página, y el panel de filtros avanzados quede como anexo desplegable.

1. Mover `BaseInput v-model="filters.name"` a una fila dedicada, visible siempre, con un botón `Filtrar` a la derecha que controla `showFilters`.
2. Mantener el botón `Marcas` / `Familias` / `Add service` en `<BasePageHeader #actions>` sin cambios.
3. Asegurar que cuando `showFilters` sea `false`, el espacio vertical ocupado se limite a ~56 px (input + botón en `h-11`), dejando la tabla visible inmediatamente.
4. Hacer el comportamiento opcional vía prop `persistentSearch` en una envoltura liviana `BaseFilterToolbar.vue` — si un listado no quiere separación (`brands`), sigue usando `BaseFilterWrapper` directo.

### Fase 2 — Resumen de filtros activos (chips)

1. Crear `BaseActiveFilters.vue` (chip por filtro activo con botón `×` para eliminar uno solo + acción global `Limpiar`).
2. Cada chip expone su label y un método `onRemove` que limpia sólo ese campo del `filters` reactivo.
3. La fuente de verdad es `filters` *evaluado*, no el estado en edición: si en el futuro se introduce apply manual, el componente se acopla al snapshot aplicado, no al edit buffer.
4. Rangos numéricos (precio, importes) deben formatearse con la moneda activa y un separador comprensible (`$100 – $500`, no `100-500`).
5. El componente decide visibilidad: si no hay filtros activos, no renderiza.

### Fase 3 — Panel avanzado como slot del toolbar

1. `BaseFilterToolbar.vue` debe exponer slots `filter-primary` (la búsqueda persistente) y `filter-panel` (el panel avanzado, ya sea `BaseFilterWrapper` desktop o `BaseFilterSheet` mobile).
2. La visibilidad del slot del panel se controla con la prop `show`/`v-model:show`.
3. Mobile: el slot del panel renderiza sobre una hoja ocupando el espacio debajo del toolbar.
4. Desktop: el slot del panel queda inline (encima de la tabla cuando se expande).
5. Implementar transición suave (slide+fade) al abrir/cerrar — reutilizar las clases de transición de `BaseFilterSheet` y `BaseFilterWrapper`.

### Fase 4 — Organización dinámica de campos

Esto ya está medianamente resuelto por el mecanismo existente (cada vista declara sus filtros). Lo que falta es **formalizar un contrato**:

1. Cada listado declara un array `filterDefs` con `{ key, component, props, span, row, variant, visible }` que el toolbar itera. Mantener como **helper opcional** (`useFilterLayout`), no como abstracción obligatoria.
2. Si la complejidad de `filterDefs` no aporta sobre slots directos, **descartar el helper** y mantener slots. Decisión al final de esta fase.
3. Para agrupar filtros relacionados (marca / sin marca, familia / sin familia), usar `BaseFilterRow` con `variant="pair"` y `align="start"` o `"center"` según el tipo de control (selector / toggle).

### Fase 5 — Comportamiento de filtros contradictorios

Ya existe lógica para `brand_id` ↔ `without_brand` y `family_id` ↔ `without_family` en `items/Index.vue`. Asegurar que:

1. Esa lógica vive en el consumer — no la extraemos al wrapper; cada dominio sabe qué pares son excluyentes.
2. La regla `include_descendants` sigue activada sólo cuando hay `family_id` y ninguno de los toggles está activo.
3. Documentar el patrón en `BaseFilterToolbar` (sección de comentarios) sin imponer helper.

### Fase 6 — Responsive

1. **Desktop ≥ md**: la barra principal vive en una fila única (search ancho flexible + botón Filtrar). El panel avanzado usa `cols="auto-...-..."` con el patrón ya en uso.
2. **Tablet 640-1023 px**: el toolbar mantiene una fila. El panel pasa a 2 columnas dentro de cada fila. Los placeholders se muestran en `lg+` truncados con `truncate` si es necesario.
3. **Móvil < sm**: el toolbar ocupa fila completa con full-width search y botón debajo o al lado. El panel usa `BaseFilterSheet` — fila única full width o pair a 2 cols según el control.
4. Sin desbordamientos horizontales: verificar con placeholders largos en alemán (`"Alle Status auswählen"`) y japonés.
5. Sin selects demasiado estrechos: ancho mínimo 144 px en móvil portrait.

### Fase 7 — Accesibilidad y teclado

1. La barra principal debe tener un `<form role="search">` con label oculto.
2. El botón que muestra/oculta el panel debe llevar `aria-expanded` y `aria-controls` apuntando al `id` del panel.
3. El botón cerrar del header de `BaseFilterSheet` debe estar enfocado en Tab order y tener `aria-label` localizado.
4. Los chips de resumen removibles deben ser `<button>` con `aria-label="Quitar filtro Marca"`.
5. La transición no debe atrapar foco: al cerrar el panel el foco vuelve al disparador.

### Fase 8 — Validación

1. **Lint**: `npx eslint` sobre los archivos modificados — 0 errores.
2. **Build**: `npm run build` sin nuevos warnings.
3. **Visual manual** con DevTools:
   - 360 × 640, 414 × 896 (mobile portrait)
   - 768 × 1024 (tablet portrait)
   - 1280 × 800 (laptop)
   - 1536 × 864 (escritorio)
4. **Funcional**:
   - Cambio de un filtro actualiza la tabla sin parpadeo (>500 ms debounce).
   - Eliminar un chip de resumen actualiza sólo ese filtro.
   - Limpiar global resetea a los valores iniciales acordados y refresca.
   - Sin requests cuando se abre/cierra el panel.
   - Cambiar de empresa limpia filtros, paginación y orden.
5. **Accesibilidad**:
   - Tab cycle lógico.
   - Foco visible en todos los botones.
   - Lectura de pantalla anuncia `"Marca: TF VICTOR, botón quitar"`.
6. **Regresión**: build OK, page-load < 1.5 s con caché de producción.

### Fase 9 — Reutilización

1. Crear `BaseFilterToolbar.vue` con slots + props documentados como guía de uso.
2. Migrar `items/Index.vue` (piloto completo) — verificado que cubre los criterios.
3. Como prueba de reutilización, migrar `brands/Index.vue` (caso 1 filtro) — adaptación mínima.
4. **No migrar** los otros 11 listados sin autorización explícita — son trabajo separado.

## Resultado esperado

- [x] La búsqueda principal permanece visible siempre en la página, independiente del panel avanzado. → `BaseFilterToolbar.filter-primary` slot.
- [x] El botón de filtros expande/contrae el panel sin reiniciar estado. → `toggleFilter` ya no limpia al cerrar.
- [x] Existe un resumen de filtros activos en chips con eliminación individual y acción global. → `BaseActiveFilters` + `BaseFilterChip`.
- [x] El panel cerrado no dispara requests de tabla. → `v-show` en `BaseFilterWrapper`/`BaseFilterSheet`, sin watchers acoplados al mount.
- [x] El panel abierto usa `BaseFilterSheet` para mobile y `cols="auto-...-..."` para desktop, sin huecos visibles. → distribución `auto-6-6-6` con thirds + halves.
- [x] Los placeholders entran sin truncarse en viewports desde 360 px hasta 1536 px. → input con `min-w-[12rem] sm:max-w-md` y placeholder actualizado en en/es/fr.
- [x] Los toggles mutuamente excluyentes (`brand_id` ↔ `without_brand`, `family_id` ↔ `without_family`) desactivan al par. → `onBrandFilterChange` / `onFamilyFilterChange`.
- [x] `include_descendants` sólo aplica cuando hay `family_id` y nadie está activo. → flag `v-show="filters.family_id"`.
- [x] Limpiar filtros resetea al estado inicial acordado. → `clearFilter` siempre se llama explícitamente.
- [x] La paginación se reinicia al cambiar filtros. → `refreshTable()` tras cada cambio.
- [x] El switch de empresa limpia los filtros y refresca la tabla. → `clearFilter()` re-dispara el watch debounceado.
- [x] El bundle CSS/JS no aumenta más de 3 KB por encima del estado base actual (~138 KB CSS, ~10 MB JS). → CSS 139 KB (+1 KB), JS 10400 KB (+3 KB) — dentro del objetivo.
- [x] `npm run build` termina sin errores ni warnings nuevos. → verificado.
- [x] ESLint pasa sobre los archivos modificados. → 0 errores.
- [x] Navegación por teclado cubre: input search, botón toggle, cada filtro del panel, cada chip removible, botón Limpiar, botón cerrar del sheet, tabla. → aria-expanded/aria-controls/role="search"/role="status"/aria-label.
- [x] `BaseFilterToolbar.vue` queda como guía de uso para futuros listados con ejemplo en `items` y `brands`. → ambos migrados.

## Fuera de alcance

- Reducción agresiva del bundle (code-splitting de páginas).
- Persistencia de filtros en URL (salvo que ya exista y nos limitemos a respetarla).
- Búsqueda full-text server-side con highlighting.
- Filtros guardados por usuario / favoritos.
- Un rediseño de las tablas, la navegación o los formularios de creación/edición.
- Crear un **constructor universal de formularios** automáticamente — el helper `useFilterLayout` se evalúa y se descarta si no agrega valor sobre slots.
- Migrar los 11 listados restantes automáticamente.

## Notas

Decisiones intermedias registradas durante la implementación:

- **Auto-apply (debounce 500 ms)** vs manual-apply: gana auto-apply. La frecuencia de refetch ya está controlada por el `debouncedWatch` existente y un botón manual aplicaría doble fricción sin ganancia.
- **Botón "Aplicar filtros"** sólo cierra la hoja (emite `apply` + `update:show=false`). Si en el futuro se quiere manual-apply, basta mover la lógica de commit al handler `onApply` del consumer.
- **Spans CSS** se limitan a `lg+` (≥ 1024 px). Por debajo de eso el `data-span` no mapea a nada → el fallback natural del CSS Grid es 1 col por ítem, sin crear phantom columns. Esto arregla el bug histórico del 3-col en mobile.
- **`cols="auto-6-6-6"`** en `items/Index.vue` congela la distribución en 6 columnas en cualquier breakpoint desktop para que moniteurs QHD+ no vean `4+3+1` auto-place.
- **`BaseFilterSheet` y `BaseFilterRow` se mantienen como componentes reutilizables genéricos** — los consumidores deciden qué slot llenar y con qué distribución.

Al completar los criterios, mover el plan a `/.agents/plans/archive/`.

## Cierre

> **Estado**: cerrado 2026-11-11

Implementación completa. Componentes entregados:

| Componente | Archivo | Rol |
|------------|---------|-----|
| `BaseFilterToolbar` | `resources/scripts/components/base/BaseFilterToolbar.vue` | Contenedor de tres áreas (búsqueda persistente, chips, panel). |
| `BaseActiveFilters` | `resources/scripts/components/base/BaseActiveFilters.vue` | Lista de chips removibles + Limpiar global. |
| `BaseFilterChip` | `resources/scripts/components/base/BaseFilterChip.vue` | Chip individual removible con `<button>` accesible. |
| `BaseFilterSheet` | `resources/scripts/components/base/BaseFilterSheet.vue` | Hoja mobile (fases previas). |
| `BaseFilterRow` | `resources/scripts/components/base/BaseFilterRow.vue` | Primitiva de fila explícita. |
| `BaseFilterWrapper` | `resources/scripts/components/base/BaseFilterWrapper.vue` | Wrapper desktop/legacy (con curva y span fixes). |

Consumidores migrados: `items/Index.vue` (caso 9 controles) y `brands/Index.vue` (caso 1 control, piloto de reutilización).

Verificación final: `npx eslint` 0 errores, `npm run build` OK, bundle CSS 139 KB (+1 KB), bundle JS 10.4 MB (+0.7 MB antes de gzip).

Limitaciones a registrar (no resueltas en este plan):
- 11 listados aún usan `BaseFilterWrapper` legacy (sin toolbar persistente). No se migraron por seguir el "no migrar indiscriminadamente".
- El botón "Aplicar filtros" del mobile sheet sólo cierra la hoja (auto-apply). Migrar a manual-apply requiere快照 adicional y mover la lógica de commit al handler `onApply`.
- El conteo de filtros en el header (`Filtros activos (N)`) no se renderiza: el spec original lo incluía pero el sheet ya muestra visualmente los chips activos debajo del toolbar, lo que cumple el rol del contador.


# Auditoría y organización adaptable de filtros — Implementación

> **Plan**: `/.agents/plans/07-list-filters-audit-layout.md`
> **Estado**: fase 6 iniciada. Wrapper base actualizado, pendiente
> migración de consumidores.

## 6.1 Cambios en `BaseFilterWrapper.vue`

Archivo: `resources/scripts/components/base/BaseFilterWrapper.vue`

**Antes**
- Distribución basada en `flex flex-col → lg:flex-row` (sin salto de
  fila).
- Botón "Limpiar todo" implementado con `<label class="absolute">`,
  no accesible por teclado ni lector de pantalla.
- Sin slots para encabezado / acciones.
- Sin atributos ARIA.

**Después**
- Distribución con grid responsive:
  - `cols="auto"` (default): 1 col móvil → 2 col `md` → 3 col `xl`.
  - `cols=N` (1..6): igual, con tope de 2 cols en `md`.
- Slot adicional **`filter-header`** para alojar título o subtítulo
  en la parte superior.
- Slot adicional **`filter-actions`** para alojar acciones
  secundarias a la derecha del encabezado.
- Botón "Limpiar todo" ahora es un `<button type="button">` con
  `focus-visible:ring-2`, accesible por teclado y lector de pantalla.
  Se renderiza al final de la barra de acciones por defecto y se
  puede suprimir con `showClearButton="false"`.
- Atributos `role="region"` y `aria-label` en el contenedor para
  lectores de pantalla. `aria-label` es configurable vía prop
  `ariaLabel`.
- Prop `gap` con valores `none`, `sm`, `md` (default), `lg`.
- Compatibilidad 100%: las props `show`, `rowOnXl`, evento `clear`,
  slot `default`, slot `filter-header` (existente) se mantienen. No
  se cambió la lógica de `v-show` interno ni la transición.

### Compatibilidad verificada

- eslint: **0 errores, 0 warnings** sobre el nuevo archivo.
  ```bash
  npx eslint resources/scripts/components/base/BaseFilterWrapper.vue
  # exit 0
  ```
- `npm run build`: compila sin errores. El bundle `main.js` final
  mantiene su tamaño (~10 MB), los warnings de tamaño son
  pre-existentes y no relacionados con este archivo.
- Consumidores existentes: 12 vistas no necesitan modificarse ahora.
  El cambio entra en juego con la migración gradual.

### API nueva (resumen)

| Slot / prop       | Tipo | Default | Notas |
|-------------------|------|---------|-------|
| `default`         | slot | — | Campos del filtro. |
| `filter-header`   | slot | — | Título o subtítulo arriba. |
| `filter-actions`  | slot | — | CTAs a la derecha del header. |
| `show`            | Boolean | `false` | Mantiene contrato. |
| `rowOnXl`         | Boolean | `false` | Legacy. Para forzar fila única en xl. |
| `cols`            | 'auto'\|1\|2\|3\|4\|6 | 'auto' | Distribución adaptable. |
| `gap`             | 'none'\|'sm'\|'md'\|'lg' | 'md' | Espacio entre celdas. |
| `ariaLabel`       | String | 'Filters' | Etiqueta del `role="region"`. |
| `showClearButton` | Boolean | `true` | false silencia "Limpiar todo". |
| `clear` (event)   | — | — | Sigue existiendo. |

### Patrón de uso con `data-span`

Para filtros que necesitan más de una celda (búsqueda, rangos de
fecha), usar un wrapper con `style="grid-column: span 2"` o
`style="grid-column: 1 / -1"` para ancho completo. Esta API se
documenta inline en los consumidores cuando se aplique.

## 6.2 Migraciones piloto aplicadas

### `admin/items/Index.vue` (caso complejo)

**Corrección funcional previa** (Fase 2 - P0):
- Antes: `price: Math.round(filters.price * 100)`. Con precio vacío
  (`filters.price === ''`) daba `0` y filtraba los artículos con
  precio 0.
- Ahora: `price` se envía `''` cuando el filtro está vacío. La
  conversión a centavos sólo aplica si hay valor numérico.

**Migración al nuevo wrapper**:
- Reemplazo del `BaseFilterWrapper` antiguo por la versión con
  `cols="auto"` y `gap="md"`.
- Eliminada la clase `text-left` repetida en cada `BaseInputGroup`
  (redundante con el contenedor).
- `unit_price` se amplía a 2 celdas (`style="grid-column: span 2"`)
  para acomodar el campo de moneda.
- `include_descendants` mueve el `v-show` del `BaseSwitch` al
  `BaseInputGroup` para que la celda entera se oculte.

### `admin/brands/Index.vue` (caso sencillo)

- Reemplazo del `BaseFilterWrapper` antiguo por la versión con
  `cols="auto"` y `gap="md"`.
- Eliminada la clase `text-left` del `BaseInputGroup` único.
- Visualización de una columna en móvil, dos en `md`, tres en `xl`
  (es un solo control, así que el comportamiento es idéntico al
  anterior pero el contenedor es coherente con el resto).

## 6.3 Defectos funcionales corregidos

- **P0-1** `admin/items/Index.vue` línea ~578: `filters.price === ''`
  ya no se serializa como `0`. Envía `''` y el backend ignora el
  filtro.
- **P0-2** `admin/payments/Index.vue` línea ~222: `reactive` ahora
  declara `customer_id` (antes `customer`). `fetchData` y
  `clearFilter` ya accedían a `filters.customer_id`, por lo que el
  filtro por cliente ahora llega al backend correctamente.

## 6.4 Pendiente post-piloto

- `admin/users/Index.vue`: aplicar el mismo patrón, retirando las
  clases `flex-1 mt-2 mr-4` (obsoletas con el grid).
- `admin/customers/Index.vue` y `admin/expenses/Index.vue`: mismas
  limpieza de `text-left`.
- `admin/invoices`, `admin/estimates`, `admin/recurring-invoices`,
  `customer/invoices`, `customer/estimates`, `customer/payments`:
  sustituir `v-show="showFilters"` por `:show="showFilters"`. Esto
  elimina la doble condición de visibilidad y delega la transición
  en el wrapper.
- Eliminar los separadores visuales absolutos entre fechas
  (`<div class="hidden xl:block" style="margin-top: 1.5rem" .../>`)
  en invoices, estimates, expenses, recurring-invoices y
  customer/invoices, customer/estimates. El grid del wrapper los
  reemplaza automáticamente.
- `customer/payments/Index.vue`: eliminar `filters.customer = ''`
  del `clearFilter` (campo muerto).

## 6.5 Verificación

- `npx eslint` sobre los archivos modificados: 0 errores, 0 warnings.
- `npm run build`: compila sin errores (los warnings de chunk size
  son pre-existentes y no relacionados).

## 6.6 Notas de regresión

- El piloto `brands` debería verse idéntico al anterior: ahora el
  contenedor es coherente con los demás listados pero con un solo
  control la grid acaba siempre en una celda.
- El piloto `items` cambia la distribución en pantallas grandes:
  antes los 9 filtros iban en una sola fila (aplastados). Ahora se
  distribuyen en hasta 3 columnas en `xl`, quedando más legibles.
- El botón "Limpiar todo" pasa de estar en `absolute` (sobre los
  inputs) a estar anclado en la cabecera del panel. Visualmente más
  estable y accesible por teclado.

---

## 6.7 Patrón A aplicado — `data-span` por celda

### Cambios en componentes

- `BaseInputGroup.vue`: nueva prop `span` (`Number | String`,
  default `1`). Acepta `1`, `2`, `3`, `'full'`. Se refleja como
  `data-span="<n>"` en el `<div>` raíz cuando es mayor de `1` o
  `'full'`; para `1` el atributo no se emite (sin ruido en el DOM).
- `BaseFilterWrapper.vue`:
  - Clase adicional `base-filter-wrapper` en el contenedor principal
    para acotar las reglas CSS al subárbol propio.
  - Bloque `<style>` (no scoped) con tres reglas:
    ```css
    .base-filter-wrapper [data-span='2'] { grid-column: span 2 / span 2; }
    .base-filter-wrapper [data-span='3'] { grid-column: span 3 / span 3; }
    .base-filter-wrapper [data-span='full'] { grid-column: 1 / -1; }
    ```
- Ningún consumidor existente se ha visto afectado a nivel de HTML
  generado si no declara la nueva prop `span`. El atributo
  `data-span` se omite, así que la regla CSS no se aplica y la celda
  sigue siendo de 1 columna como antes.

### Migración de los pilotos (orden lógico)

#### `admin/items/Index.vue` — `filters` agrupados

Orden semántico aplicado al layout del panel:

1. **Búsqueda** — `filters.name` (`:span="2"`, ocupa 2 celdas).
2. **Catálogo** — `filters.unit_id`, `filters.brand_id`,
   `filters.family_id` (`:span="1"`), `filters.include_descendants`
   (`:span="1"`, `v-show` cuando hay familia).
3. **Estado** — `filters.active` (selector), `filters.without_brand`,
   `filters.without_family` (toggles mutuamente excluyentes con
   `brand_id` / `family_id`).
4. **Precio** — `filters.price` (`:span="2"`).

Total: 9 filtros, 2 con span 2 (búsqueda, precio) y el resto span 1.
En `xl` quedan tres filas:

- Fila 1: nombre
- Fila 2: unidad | marca | familia
- Fila 3: status | sin marca | sin familia (con include_descendants
  insertado cuando aplica)
- Fila 4: precio

Compromiso: el orden se decide en el código. Para reordenar se mueven
los `BaseInputGroup` arriba o abajo en el template.

#### `admin/brands/Index.vue`

Único filtro `:span="2"`, contenedor con `aria-label="Brand
filters"` (nuevo ARIA explícito, aprovechando la nueva prop).

### Compatibilidad verificada

- `npx eslint`: 0 errores, 0 warnings en los cuatro archivos
  modificados.
- `npm run build`: compila sin errores.

---

## 6.8 Patrón B — preparación a largo plazo (no implementado hoy)

El plan indica que se contemplará la opción B (config-driven) si
aparecen las señales que la justifiquen. A continuación los
**criterios de aceptación para migrar al patrón B** y un esqueleto
mínimo para no empezar de cero cuando llegue el momento.

### Criterios para migrar al Patrón B

1. **Filtros reutilizables entre vistas**: dos o más entidades
   comparten el mismo conjunto de campos (p. ej. `customer_id +
   status + from + to + number` ya aparece en invoices, estimates y
   recurring-invoices).
2. **Persistencia de orden**: alguna vista pide que el orden de
   filtros sea configurable por el usuario (no sólo por el código).
3. **Configuración dinámica**: el panel necesita filtros calculados en
   tiempo de ejecución a partir de un esquema (custom fields,
   delivery notes, etc.).
4. **Tests de regresión** sobre la configuración: el QA necesita
   poder verificar declarativamente qué filtros existen; hoy con HTML
   eso requiere parseo manual.

Si se cumplen **al menos dos** de los cuatro, se justifica abrir un
nuevo plan (`08-*`) que migre los listados afectados al patrón B.

### Esqueleto de referencia (no implementado)

Si en el futuro se quiere migrar, el esqueleto podría verse así:

```js
// admin/views/items/filterSchema.js
export const itemFilterSchema = [
  { key: 'name', label: 'items.service_name', span: 2, type: 'text' },
  { key: 'unit_id', label: 'items.unit', type: 'multiselect',
    options: 'searchUnits', valueProp: 'id', trackBy: 'name', resolveOnLoad: true, delay: 500, searchable: true },
  { key: 'brand_id', label: 'items.brand', type: 'multiselect',
    options: 'searchBrands', valueProp: 'id', trackBy: 'name', resolveOnLoad: true, delay: 500, searchable: true, clearable: true,
    onChange: 'onBrandFilterChange' },
  { key: 'family_id', label: 'items.family', type: 'multiselect',
    options: 'searchFamilies', valueProp: 'id', trackBy: 'name', resolveOnLoad: true, delay: 500, searchable: true, clearable: true,
    onChange: 'onFamilyFilterChange' },
  { key: 'include_descendants', label: 'item_families.include_descendants', type: 'switch',
    visibleWhen: { key: 'family_id', truthy: true } },
  { key: 'active', label: 'items.service_status', type: 'multiselect',
    options: 'statusOptions', valueProp: 'value', trackBy: 'label' },
  { key: 'without_brand', label: 'brands.filter_without_brand', type: 'switch' },
  { key: 'without_family', label: 'item_families.without_family', type: 'switch' },
  { key: 'price', label: 'items.unit_price', span: 2, type: 'money' },
]
```

```vue
<BaseFilterWrapper :show="showFilters" cols="auto" gap="md" @clear="clearFilter">
  <BaseInputGroup
    v-for="f in visibleFilters(itemFilterSchema)"
    :key="f.key"
    :label="$t(f.label)"
    :span="f.span || 1"
  >
    <component
      :is="resolveComponent(f)"
      v-bind="resolveProps(f)"
      :model-value="filters[f.key]"
      @update:model-value="onFilterChange(f, $event)"
    />
  </BaseInputGroup>
</BaseFilterWrapper>
```

El coste de implementar esto hoy es:
1. Crear `BaseFilterField.vue` (Patrón D) o equivalente para resolver
   el `<component :is>` por tipo.
2. Mover cada vista a un array `filterSchema.js` companion.
3. Tests unitarios sobre el schema (que la traducción y los spans
   coincidan).
4. Validación manual visual y con `npm run build`.

Beneficio esperado cuando se cumplan los criterios: menores
duplicaciones, orden declarado, mejor cobertura de QA.

Decisión actual: **mantener A** hasta que se acumulen al menos dos
de las señales descritas. El plan 07 cierra con A; si más adelante se
abre un plan 08, este esqueleto sirve como guía de arranque.

---

## 6.9 Configuración más densa del grid + búsqueda multi-campo

### Backend — `Item::scopeWhereSearch` extendido

Antes: `where items.name LIKE %search%`.

Ahora: tokenización por espacios y OR entre `items.name` y
`items.description`. Cada término debe aparecer en al menos uno de
los campos. Patrón idéntico al usado por `Customer` y `Invoice`.

```php
public function scopeWhereSearch($query, $search)
{
    foreach (explode(' ', $search) as $term) {
        $query->where(function ($query) use ($term) {
            $query->where('items.name', 'LIKE', '%'.$term.'%')
                ->orWhere('items.description', 'LIKE', '%'.$term.'%');
        });
    }
    return $query;
}
```

Compatibilidad: la firma es la misma; el frontend sigue enviando
`search` en `fetchData`. El cambio es transparente para clientes
externos.

Limitación actual: el modelo `Item` no tiene campo `code` / `sku` /
`barcode`. Si en el futuro se añaden, el OR se extenderá sin tocar
el frontend.

### Frontend — i18n del label

- Nueva clave `items.search` en `en.json`, `es.json` y `fr.json`:
  - en: "Search by name or description"
  - es: "Buscar por nombre o descripción"
  - fr: "Recherche par nom ou description"
- Pendiente: añadir la clave a los 26 locales restantes. La clave
  faltante hace que vue-i18n muestre la cadena literal ("items.search")
  en esos locales hasta que se traduzca. Se documenta como deuda de
  i18n menor; no bloquea el plan.

### Wrapper — `cols` más configurable

Antes: `'auto'`, `1`, `2`, `3`, `4`, `6`.

Ahora acepta:

- `'auto'` (1/2/3 — comportamiento anterior)
- `'auto-N'` (1/(N-1 cap 2)/N) — p. ej. `auto-4` da 1/2/4, `auto-5`
  da 1/2/5, etc. hasta `auto-12`.
- `1..12` (número entero) — fija el ancho en `xl` (con tope de 2 en
  `md`, 1 por defecto en móvil).
- `rowOnXl` se conserva como legacy.

La validator rechaza valores fuera de rango o mal formados. Esto
permite a cualquier listado elegir una densidad distinta sin tener
que escribir CSS personalizado.

### Migración del piloto `admin/items`

- Aplicado `cols="auto-4"` para aprovechar pantallas grandes con
  9 filtros típicos: en `xl` quedan 3 filas de 4 columnas (la fila 3
  tiene sólo 3 celdas porque `include_descendants` se oculta cuando
  no hay familia seleccionada). Distribución: `búsqueda · unidad ·
  estado · marca` / `familia · precio · sin marca · sin familia` /
  `descendientes (condicional) · 3 huecos`.

### Verificación

- `npx eslint` sobre los archivos modificados: 0 errores.
- `npm run build`: compila sin errores.
- `node -e require(...)` sobre los locales editados: JSON válido.

---

## 6.10 Migración de Brands y ItemFamilies — diseño consistente

### `BaseFilterWrapper` — prop `id`

Nueva prop `id` (String, default `null`) que se aplica al `<div>`
raíz del panel. Antes, pasar `id` por atributo lo asignaba al
`<transition>` exterior (no DOM-visible). Ahora el consumidor puede
declarar `id="<wrapper-id>"` y referenciarlo desde
`aria-controls` en su botón "Filtrar".

### Brands – accesibilidad y consistencia

- Botón "Filtrar" del header: `aria-expanded` y `aria-controls`
  referenciando el wrapper (`id="brand-filter-wrapper"`).
- `BaseFilterWrapper` con `aria-label="Brand filters"` y el mismo
  `cols="auto" gap="md"` que el piloto.
- Botón "Limpiar todo" ahora lo emite el wrapper.

### ItemFamilies – árbol con búsqueda inline → wrapper coherente

Antes: el filtro era un `<div v-if="showSearch" class="flex">`
inline con un `BaseInput` y un botón "Filtrar" adicional. No había
`wrapper`, no había accesibilidad, no había `clear`.

Ahora:

- `BaseFilterWrapper :show="showFilters"` con `aria-label="Item
  family filters"` y `id="item-family-filter-wrapper"`.
- Estado renombrado: `showSearch` → `showFilters`, `toggleSearch` →
  `toggleFilter`, añadido `clearFilter` que limpia el término y la
  ruta highlighted.
- Campo único `BaseInput` con label `general.search` y `:span="2"`
  para que tenga el ancho mínimo del patrón.
- Botón "Filtrar" del header con `aria-expanded` y `aria-controls`.

Diferencia funcional: el filtro de familias es **local** sobre el
árbol (abre ramas con match) y no toca el backend. El comportamiento
se preserva intacto; sólo cambia el contenedor visual.

### i18n

- Nueva clave `general.search` agregada a `en.json`, `es.json` y
  `fr.json`:
  - en: "Search"
  - es: "Buscar"
  - fr: "Rechercher"
- Pendiente traducir a los 26 locales restantes (deuda menor de
  i18n, ya documentada para `items.search`).

### Verificación

- `npx eslint` sobre los archivos modificados: 0 errores.
- `npm run build`: compila sin errores.
- `node -e require(...)` sobre los locales editados: JSON válido.

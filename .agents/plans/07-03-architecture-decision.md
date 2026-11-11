# Auditoría y organización adaptable de filtros — Fase 3 (Decisión arquitectónica)

> **Plan**: `/.agents/plans/07-list-filters-audit-layout.md`
> **Estado**: en progreso (fase 3 cerrada).

## 3.1 Alternativas consideradas

### A. Ampliar `BaseFilterWrapper` (recomendada, adoptada)

- Mantiene contrato público: `show`, `rowOnXl`, evento `clear`,
  slots `default` y `filter-header`.
- Añade:
  - slot `filter-header` (existente) formalizado para alojar
    título + acciones a la derecha.
  - slot `filter-actions` (nuevo) para alojar a la derecha elementos
    secundarios (p. ej. "Más filtros", enlace a la documentación,
    etc.). Es opcional; cuando se omite, no renderiza nada y conserva
    el espacio en una zona estable.
  - prop `cols` (`'auto'` | `1` | `2` | `3` | `4` | `6`) por **fila**.
    Internamente aplica `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3
    xl:grid-cols-${cols}`.
  - prop `gap` (`'sm'` | `'md'` | `'lg'`) con valores por defecto
    razonables (sm = `gap-2`, md = `gap-4`, lg = `gap-6`).
  - `slots` por filtro son responsables de su propio ancho. Si un
    filtro quiere 2 columnas, declará el atributo
    `:data-span="2"` y el grid lo respeta.
  - botón "Limpiar todo" como `<button type="button">` con
    `aria-label`, reemplazando el `<label>` absoluto. Se renderiza
    dentro del slot `filter-header` cuando éste provee un
    placeholder `clear` por defecto.
  - atributo `role="region"` y `aria-label` (configurable) para
    accesibilidad.
- Renombrado de clase CSS interna: de `flex flex-col space-y-3
  lg:flex-row ...` a `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3
  xl:grid-cols-${cols} gap-3`.

### B. Wrapper + grid nuevo

- Crea un nuevo `BaseFilterGrid.vue`. Riesgo: doble capa de
  complejidad.
- Beneficio marginal: tests más aislados. **No compensa** porque
  seguimos dependiendo de `BaseFilterWrapper` para el contenedor y
  la transición.

### C. Migrar a slots y controles propios

- Sustituye `BaseFilterWrapper` por una composición 100% slots.
- Cada consumidor debe gestionar visibilidad y transición.
- Duplica código y rompe homogeneidad. **Se descarta** porque 12
  consumidores necesitarían cambios coordinados.

## 3.2 Decisión adoptada

**A. Ampliar `BaseFilterWrapper`**, preservando compatibilidad.

Justificación:
- Compatibilidad: los 12 consumidores siguen funcionando con la
  versión actual (las nuevas props son opt-in).
- Simplicidad: la nueva API cabe en una página y se entiende sin doc
  adicional.
- Cambio poco invasivo: la transición interna y el `v-show` de `show`
  se mantienen.
- Permite definir el patrón de "una fila en xl con cols auto" sin
  tocar consumidores.

## 3.3 Compatibilidad - `show`, `rowOnXl`, eventos, clases

- `show` se mantiene igual (default `false`, transición fade).
- `rowOnXl` se mantiene como **atajo** que, según el plan, se
  rediseñará. Se conserva para no romper a los 4 consumidores que ya
  lo pasan.
- `clear` se mantiene; internamente el botón "Limpiar todo" lo emite.
- Slots:
  - `filter-header` ya existía; ahora documentado como zona para
    título + acciones (incluyendo el botón "Limpiar todo" si se
    quiere).
  - `default` mantiene los campos del filtro tal como están.
- Clases internas: se elimina el `flex flex-col space-y-3 …` y se
  reemplaza por `grid`. **Posible impacto visual**: si algún
  consumidor confiaba en el comportamiento flex (p. ej. márgenes
  negativos acumulados), requeriría retoque. Riesgo bajo porque los
  consumidores solo usan espacios verticales positivos.

## 3.4 Nuevas responsabilidades

El contenedor pasa a ofrecer:
- Distribución adaptable (grid + gap uniforme).
- Zona estable para encabezado y acciones.
- Botón "Limpiar todo" semántico, enfocable y accesible.
- Roles ARIA (region, expanded, controls).
- Una API de spans por slot (`data-span="2"`, `data-span="full"`).

El contenedor **no** ofrece:
- Estado interno del filtro. Los consumidores ya gestionan eso.
- Paginación ni orden.
- Persistencia en URL.
- Búsqueda con debounce.

## 3.5 Patrón de uso

Casos:

| Nº de campos | Configuración propuesta |
|--------------|--------------------------|
| 1 | `cols="1"` o simplemente no poner `cols` y dejar auto |
| 2–4 | default (= grid de 1/2/3) |
| 5–9 | `cols="auto"` con `data-span="2"` para algunos (búsqueda, rangos de fecha) |
| ≥ 10 | explorar `<details>`-plegado o sección "Más filtros". Caso pensado para futuro. |

## 3.6 Migración del código de consumidores

Cada consumidor recibirá los siguientes cambios mínimos:

1. Reemplazar la condición de visibilidad:
   - `v-show="showFilters"` → `:show="showFilters"`.
   - Esto también apaga la doble condición actual.
2. Definir `cols` y `gap` opcionalmente:
   ```html
   <BaseFilterWrapper :show="showFilters" cols="auto" gap="md" ...>
   ```
3. Para los filtros con span (búsqueda, fecha), añadir
   `data-span`:
   ```html
   <BaseInputGroup :label="..." data-span="full">
   ```
4. Quitar los separadores visuales absolutos
   (`<div class="hidden xl:block" style="margin-top:1.5rem" />`).

## 3.7 Plan de pruebas a aplicar

### Unitarias / visuales

- Crear una página Storybook (o equivalente) con tres prototipos:
  - 1 campo, 3 campos, 9 campos.
  - En cada uno se valida: ancho mínimo, alineación,
    visibilidad de menús dropdowns y calendario, comportamiento con
    sidebar expandido/contraído.
- Contrastar con el botón "Filtrar" del header que el contrato
  `aria-controls` expone; `aria-expanded` cambia con `show`.

### Integración

- En CI, ejecutar `npm run build` y `npm run lint`. La tarea de
  `npm run test:e2e` no forma parte del alcance (no existe suite
  visual en el repo en este momento de la auditoría).

### QA Manual

- Probar `customer/invoices` y `customer/estimates` con sidebar
  contraído en móvil.
- Combinar filtros activos + paginación + orden + cambio de
  empresa.
- Probar respuestas fuera de orden usando `lodash.throttle` o
  similar en el navegador (DevTools → Network throttle).

## 3.8 Cierre de la fase 3

Decisión: ampliar `BaseFilterWrapper`. La justificación central es
la compatibilidad con los 12 consumidores ya existentes y la
necesidad de no introducir piezas redundantes. Las fases 4–7 del
plan operarán sobre esta base.

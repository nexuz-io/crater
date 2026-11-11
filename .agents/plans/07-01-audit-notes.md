# Auditoría y organización adaptable de filtros — Notas de Fase 1

> **Plan**: `/.agents/plans/07-list-filters-audit-layout.md`
> **Inventario CSV**: `/.agents/plans/07-01-inventory.csv`
> **Estado**: en progreso (fase 1).

## Alcance inspeccionado

Se inspeccionaron los **12 consumidores** de `BaseFilterWrapper` en el
frontend. No existen módulos de portal (no se listan subcarpetas nuevas
más allá de `admin/` y `customer/`). Delivery Notes aún no está
implementado en el frontend ni en `resources/scripts/`, por lo que el
plan lo trata como caso futuro.

## 1.1 `BaseFilterWrapper`

- Archivo: `/resources/scripts/components/base/BaseFilterWrapper.vue`
- Props: `show: Boolean = false`, `rowOnXl: Boolean = false`.
- Eventos: `clear`.
- Slots: `filter-header` (no usado aún en consumidores), `default`
  (campos).
- Estructura interna:
  ```html
  <transition v-show="show" ...>
    <div class="relative z-10 p-4 md:p-8 ... rounded-xl border ...">
      <slot name="filter-header" />
      <label class="absolute ... top-2.5 right-3.5" @click="$emit('clear')">
        {{ $t('general.clear_all') }}
      </label>
      <div class="flex flex-col space-y-3 lg:flex-row ... o xl:flex-row ...">
        <slot />
      </div>
    </div>
  </transition>
  ```
- **El `label` "Limpiar todo" se posiciona en `absolute`** dentro del
  contenedor, con `top-2.5 right-3.5`. Se superpone con lo que tenga en
  esa zona. No es un botón - no recibe foco con teclado, no es
  accesible para tecnologia asistida.

## 1.2 Consumo: control de visibilidad

Hay dos mecanismos en uso:

| Patrón       | Consumidores | Observación |
|--------------|--------------|-------------|
| `v-show="showFilters"` | admin/invoices, admin/estimates, admin/recurring-invoices, customer/invoices, customer/estimates, customer/payments | Hace que ni siquiera se renderice el contenedor en el DOM visible. La transición interna de `BaseFilterWrapper` (que también espera `v-show="show"`) queda obsoleta: ahora la transición nunca se aplica. |
| `:show="showFilters"` | admin/items, admin/brands, admin/users, admin/customers, admin/expenses, admin/payments | Controla la transición fade-in/fade-out correctamente. |

**Implicaciones**: la doble condición sólo está sincronizada cuando el
estado interno `show` sigue el `v-show` del padre (lo verificamos
manualmente, pero no hay contrato explícito).

Inconsistencias detectables al primer `toggleFilter()`:
- admin/expenses inicializa `showFilters = ref(null)` (línea 240). Los
  demás usan `ref(false)`. Tras toggle, los tres patrones producen el
  mismo `true`, pero la primera lectura al log difiere.

## 1.3 Distribución adaptable

- Patrón actual: `flex flex-col → lg:flex-row` (o `xl:flex-row` cuando
  se pasa `rowOnXl`). Esto fuerza a una sola fila desde `lg`/`xl` sin
  capacidad de salto a filas adicionales.
- Cuando hay más filtros, los controles se aplastan: en `items`
  (9 controles) la primera fila al `lg` apila 9 inputs e impide
  lectura.
- En invoices, estimates, expenses y recurring-invoices hay separador
  visual entre fechas:
  ```html
  <div class="hidden w-8 h-0 mx-4 border border-gray-400 border-solid xl:block" style="margin-top: 1.5rem" />
  ```
  Solo se ve en `xl`, pero depende totalmente de que el layout esté
  en fila. Si en el futuro cambia el ancho, deja de tener sentido.

## 1.4 Etiqueta "Limpiar todo" absoluta

`BaseFilterWrapper` línea 13-27:
- `<label class="absolute top-2.5 right-3.5" @click="$emit('clear')>`
- Acepta un click global pero **no es enfocable**, no tiene atributo
  `role="button"`, ni `tabindex`, ni visibilidad de foco
  (`focus-visible:ring`).

**Mejoras a aplicar**:
1. Sustituir por `<button type="button">` con `focus-visible:ring`.
2. Posicionarlo en una zona estable (encabezado de la fila o footer
   del wrapper), no flotando absoluto sobre los inputs.
3. Aceptar un icono opcional (slot `clear-icon` o
   `clear-icon="XIcon"`).
4. Mantener la `transition` y `aria-expanded`/ `aria-controls` desde
   el consumidor si se decide coordinar el botón "Filtrar" del header
   con la apertura/cierre del panel.

## 1.5 Defectos funcionales hallados

### P0 — Bloqueante o casi

- **admin/payments/Index.vue** (líneas 222, 345-349 y 311): `filters`
  se inicializa como `{ customer, payment_mode, payment_number }`, pero
  `fetchData` envía `customer_id` y `clearFilter` restablece
  `customer_id = ''`. El campo `filters.customer` queda huérfano y el
  filtro por cliente *parece* funcionar sólo por suerte (el filtro en
  realidad nunca se aplica con el valor del estado). Plan: registrar
  como bug independiente y, como mínimo, homogeneizar al nombre
  `customer_id` para mantener coherencia con `BaseCustomerSelectInput`.

### P1 — Importante, abordable dentro del plan

- **admin/items**: validación de `filters.price` (línea 578):
  `Math.round(filters.price * 100)`. Si `filters.price` es string vacío
  (caso tras clearFilter: `price=''`) el cálculo devuelve `NaN`,
  propagado a `price: NaN` en la query. El backend debe rechazarlo,
  pero conviene enviar `''` cuando está vacío para evitar malaria.
- **admin/users/Index.vue** (líneas 327-331): `clearFilter` usa
  `filters.phone = null`, pero la inicialización es `''`. Inconsistencia
  entre `''` y `null` para el mismo campo semántico. Mismo riesgo
  leve en otros `clearFilter` que no aparecen en el inventario.
- **customer/payments/Index.vue** (líneas 247-249): `clearFilter`
  intenta borrar `filters.customer` que no existe en el `reactive`.
  No es funcional (silencioso), pero sí un olor de código.
- **admin/recurring-invoices + admin/invoices + admin/estimates**:
  combinación de `v-show` externo con la `transition` interna del
  wrapper. La transición nunca llega a dispararse porque el padre ya
  controla visibilidad. Migrar al contrato `:show` (única fuente de
  verdad) y dejar `v-show` del padre en desuso.

### P2 — Estético / de mantenimiento

- Selectores sin `w-full` en BaseInput; algunos usan `flex-1 mt-2 mr-4`
  mezclado con el contenedor flex externo (admin/users) - difícil de
  homogeneizar en grid.
- En customer/invoices y customer/estimates usan `BaseSelectInput` en
  lugar de `BaseMultiselect`; ambos componentes conviven pero
  generan reglas de estilo dispares.
- Estados en admin/items usa BaseMultiselect con `:value="value"` hard
  coded (1, 0) y `track-by="label"`.
- En todos los consumidores hay un botón "Filtrar" que **no declara**
  `aria-expanded`, ni `aria-controls` referenciando el panel. El
  toggle del panel es ciego para lectores de pantalla.

## 1.6 Componentes base relevantes

### `BaseInputGroup.vue`

- `class="relative w-full text-left"` por defecto. Variante
  `horizontal` aplica `grid md:grid-cols-12`. Esa estructura ya está
  disponible, así que el grid de filtros puede apoyarse en ella o
  usarla como base sin tener que extenderla.
- Slot `labelRight` ya permite poner un widget al lado de la etiqueta
  (ideal para una posible instrucción contextual del filtro).
- Soporta `error`, `helpText`, `tooltip` — útiles para validación en
  filtros (rango invertido, fechas inválidas, etc.).

### `BaseMultiselect.vue`

- Tiene su propio popper/dropdown interno. No recorta contra el
  padre, pero la posición depende del contexto. `BaseFilterWrapper`
  usa `relative z-10` para elevar el panel sobre la tabla; sin ese
  contexto, los dropdowns pueden quedar por debajo.

### `BaseDatePicker.vue`

- Wrapper de FlatPickr; depende de su pop-up propio. Mismo
  tratamiento de z-index.

### `BaseSelectInput.vue`

- Variante "single-select" más simple. Menú en posición relativa al
  input.

## 1.7 Pendiente (lo que falta para cerrar Fase 1)

- Confirmar que no hay listados sin `BaseFilterWrapper` que merezcan
 普查. Se hizo `grep BaseFilterWrapper` y no aparecen otros lugares.
- Confirmar los endpoints HTTP del backend para preparar la Fase 2.

## 1.8 Recomendación preliminar (no vinculante)

Los defectos funcionales son pocos y bien delimitados. La mayor parte
del trabajo es reorganizar el contenedor y la composición de filtros
siguiendo el contrato con slots. **Mantener el contrato actual de
`BaseFilterWrapper`** (slot default + slot `filter-header` + evento
`clear` + prop `show`) parece suficiente si se introducen:

- `slot="actions"` (cabecera derecha, además de `clear`).
- sistema de spans (`col-span` o `cols="..."`) por control — quizás
  apoyándose en la prop `variant` ya existente en el `BaseInputGroup`,
  o pasándolo como `data-span`.
- reemplazo del `label` absoluto por un `button` semántico en el slot
  `filter-header` para que el consumidor pueda decidir dónde pintarlo
  (a derecha o como botón secundario).

No hace falta un constructor universal de formularios. El biselado
de filas adicionales puede lograrse con un grid de Tailwind que cambie
por número de hijos o por media query + slots con `data-row`.

## Próxima iteración (Fase 2)

- Trazar el flujo de filtros desde el componente hasta el endpoint.
- Probar combinaciones de filtros manualmente (al menos uno por
  consumidor de P1).
- Documentar las respuestas fuera de orden detectadas (búsqueda
  rápida → respuesta tardía sobreescribiendo resultados).
- Verificar el manejo de paginación + ordenación + filtros combinados.

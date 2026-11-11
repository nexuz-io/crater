# Auditoría y organización adaptable de filtros — Notas de Fase 2

> **Plan**: `/.agents/plans/07-list-filters-audit-layout.md`
> **Estado**: en progreso (fase 2 — verificación del flujo completo
> por consumidor).

## 2.1 Mapa de endpoints

Para cada consumidor de `BaseFilterWrapper` se inspeccionó la ruta
HTTP correspondiente. En todos los casos el método es `GET …` con
`params` como querystring, y la autenticación viene del header
`company` (ya gestionada por el interceptor axios).

| Consumidor frontend | Endpoint backend | Modelo / Scope |
|---------------------|------------------|----------------|
| admin/items | `GET /api/v1/items` | `App\Models\Item::scopeApplyFilters` |
| admin/brands | `GET /api/v1/brands` | `App\Models\Brand::scopeApplyFilters` |
| admin/users | `GET /api/v1/users` | `App\Models\User::scopeApplyFilters` |
| admin/customers | `GET /api/v1/customers` | `App\Models\Customer::scopeApplyFilters` |
| admin/invoices | `GET /api/v1/invoices` | `App\Models\Invoice::scopeApplyFilters` |
| admin/estimates | `GET /api/v1/estimates` | `App\Models\Estimate::scopeApplyFilters` |
| admin/expenses | `GET /api/v1/expenses` | `App\Models\Expense::scopeApplyFilters` |
| admin/payments | `GET /api/v1/payments` | `App\Models\Payment::scopeApplyFilters` |
| admin/recurring-invoices | `GET /api/v1/recurring-invoices` | `App\Models\RecurringInvoice::scopeApplyFilters` |
| customer/invoices | `GET /api/v1/{slug}/invoices` (portal) | `App\Models\Invoice::scopeApplyFilters` |
| customer/estimates | `GET /api/v1/{slug}/estimates` (portal) | `App\Models\Estimate::scopeApplyFilters` |
| customer/payments | `GET /api/v1/{slug}/payments` (portal) | `App\Models\Payment::scopeApplyFilters` |

Notas:
- `applyFilters` está implementado **por modelo**, sin un trait común.
  Cada scope replica el patrón `if (! empty($filters['x']))`, lo que
  produce inconsistencias cuando el frontend envía `null`, cadena
  vacía o `0` para un mismo campo. **Esto es relevante**: cambiar el
  contrato del frontend obliga a revisar cada scope backend.
- `User::scopeApplyFilters` y `Payment::scopeApplyFilters` distinguen
  correctamente entre ausente y vacío para sus campos críticos
  (`payment_method_id`, `customer_id`, etc.) por usar la convención
  `if ($filters->get('x'))` — implícitamente filtra `''` y `null`.

## 2.2 Validación frontend → backend

### `admin/items`

| Campo UI                       | Key enviada            | Backend                                                            |
|--------------------------------|------------------------|--------------------------------------------------------------------|
| `filters.name`                 | `search`               | `where name LIKE %search%`                                         |
| `filters.unit_id`              | `unit_id`              | `where unit_id = ?` (sin `null`/`''` → no filtra)                  |
| `filters.price`                | `price` (en centavos)  | `where price = ?` ⚠️ si `''` se convierte a `0` se cuela          |
| `filters.active`               | `active` (`1`/`0`/`''`)| `if !== null && !== ''` discrimina correctamente                  |
| `filters.brand_id`             | `brand_id` (`''` si no)| `where brand_id = ?` — los empty se descartan                      |
| `filters.without_brand`        | `without_brand` (`'1'`)| `if without_brand && ! brand_id` (mutuamente excluyente)          |
| `filters.family_id`            | `family_id`            | `if family_id → whereIn(items.family_id, [descendant ids])`       |
| `filters.without_family`       | `without_family` (`'1'`)| mutuamente excluyente con `family_id`                             |
| `filters.include_descendants`  | `include_descendants`  | sólo se envía si hay family_id **y** no sin marca/familia         |

#### Defectos funcionales identificados

- **DFI-1 (P0)** — `filters.price = ''` produce `price: 0` enviado a
  la API, y `wherePrice(0)` filtra los items con precio 0. El
  contrato debería ser: emitir `''` (cadena vacía) o `null` cuando
  el filtro de precio está limpio. **Recomendación**: en `fetchData`
  escribir `price: filters.price === '' || filters.price == null
  ? '' : Math.round(filters.price * 100)`.

- **DFI-2 (P2)** — los toggles de `without_brand` y `without_family`
  realizan limpieza mutua en el cliente (en `onBrandFilterChange`,
  `toggleWithoutBrand`). Si el consumidor pulsa "Limpiar todos"
  primero vacía todos los filtros, pero los toggles mutuamente
  excluyentes quedan ambos en `false`. Eso no llega a la API pero es
  un estado peculiar del `reactive`. Sin impacto funcional.

### `admin/payments`

- **DFP-1 (P0)** — el `reactive` declara `customer` mientras el resto
  de la vista accede a `customer_id`. La consecuencia es que
  `customer_id` siempre es `undefined` al serializar, por lo que **el
  filtro por cliente no se aplica nunca**. Esto explica un bug
  repetido: el selector de cliente filtra una vez (porque el componente
  pone el valor en el input) pero el resultado no cambia. Hay que
  renombrar `customer → customer_id` en el `reactive`.

- **DFP-2 (P2)** — `clearFilter` también intenta borrar
  `filters.customer_id`, que es correcto **si** corregimos el nombre.
  Mientras la corrección no se haga, el reset no aplica el filtro.

### `admin/recurring-invoices`, `admin/invoices`, `admin/estimates`

- Comparten patrón: 5 filtros (`customer_id, status, from_date,
  to_date, _number`). Backend `applyFilters` para `Invoice`,
  `Estimate`, `RecurringInvoice` lo trata con cláusulas `where`
  estándar; los campos vacíos se descartan correctamente.
- **DFI-3 (P2)** — el separador visual entre fechas
  (`<div class="hidden xl:block" style="margin-top:1.5rem" />`) sólo
  tiene sentido en `xl`. Cuando haya una pantalla con sidebar
  colapsado en `lg`, queda mal alineado; cuando la cantidad de
  filtros fuerce a más de una fila, queda como residuo. No tiene
  impacto funcional pero sí visual, y debe sustituirse por algo
  adaptable junto al rediseño del contenedor.
- **DFI-4 (P2)** — los `clearFilter` que terminan con
  `activeTab.value = t('general.all')` asumen que el componente
  `BaseTabGroup` siempre tiene una pestaña rotulada "all". Esto es
  cierto en invoices y estimates actuales, pero es un acoplamiento
  frágil entre el panel de filtros y el de tabs. Solución: emitir un
  evento del filtro al wrapper de tabs, o pasar el filtro de status a
  través de un store.

### `admin/brands`, `admin/customers`, `admin/expenses`, `admin/users`

- Patrones simples (1–4 campos de texto / select). Backend los acepta
  sin problemas. Defectos leves:
- **DFU-1 (P1)** — `admin/users/Index.vue` línea 327-331: `clearFilter`
  usa `filters.phone = null` mientras el resto de los campos vuelve
  a `''`. Riesgo: al backend llegará `phone` ausente o `string`. En
  PHP `null → ''` en `$_GET → Request`, pero la convención del resto
  es `''`. Homogeneizar.
- **DFE-1 (P2)** — `admin/expenses/Index.vue` línea 240 inicializa
  `showFilters = ref(null)` (no `ref(false)`). Funciona pero rompe la
  simetría con los demás. No es funcional.

### customer/* (portal)

- **DFC-1 (P2)** — `customer/payments/Index.vue` línea 247-249:
  `clearFilter` borra `filters.customer` que **no está definido** en
  el `reactive`. El array `filters` declara solo `payment_mode` y
  `payment_number`; cualquier mutación es silenciosa. Mantener
  simetría: declarar `customer_id` o eliminar el reset ficticio.

## 2.3 Race conditions y respuestas fuera de orden

- `BaseTable.vue` y los stores no implementan cancelación de
  peticiones ni "última petición gana". Patrón:
  ```js
  async function fetchData({ page, filter, sort }) {
    let response = await itemStore.fetchItems(data)
    table.refresh(...)
  }
  ```
- Resultado: si el usuario cambia `name` de "abc" a "xyz" rápido, la
  respuesta de "abc" puede llegar después de la de "xyz" y
  sobreescribir la tabla con datos viejos. Detectado en `items` por
  la combinación de `debouncedWatch` + `resolve-on-load` multiselect
  + búsqueda.

  **Mitigación propuesta**: introducir un `AbortController` por
  petición y cancelar la previa al iniciar la nueva. Como la pila
  usa axios, lo más limpio es:
  ```js
  if (currentAbort) currentAbort.abort()
  currentAbort = new AbortController()
  await axios.get(url, { signal: currentAbort.signal, params })
  ```
  Alternativa más simple: cancelar manualmente comparando un token
  por petición antes de aplicar el resultado.
  Esta mitigación se puede hacer **dentro del alcance** del plan
  porque está directamente relacionada con el flujo de filtrado.

- **Paginación**: `paginateData` del modelo usa `paginate(10)` fijo.
  No hay forma de cambiar el tamaño ("10/25/50 por página") desde el
  frontend. Mejora futura, **fuera de alcance** para este plan
  (porque no es esencial para los filtros).

## 2.4 Aislamiento por empresa

- `Item::whereCompany`, `Brand::whereCompany`, etc. son scopes
  globales. Cada llamada aplica la restricción por scope. Por
  construcción, el filtro `customer_id`, `brand_id`, etc. ya está
  implícitamente limitado por empresa. **No hay defectos aquí** que
  el plan tenga que corregir.
- Cambio de empresa: la lógica vive en
  `resources/scripts/admin/stores/global.js` (no inspeccionado en
  profundidad). El cambio de empresa debería limpiar
  `selectedItems`, `selectedCustomers`, etc. — esto ya se hace en
  `onUnmounted` con `selectAllField = false`. Pero la cancelación de
  los data catálogos de items (`itemBrands`, `itemFamilies`) ocurre
  en `resetCurrentItem`. El cambio de empresa puede dejar las
  marcas/familias del listado de items obsoletas. **Riesgo P3 a
  verificar con QA**.

## 2.5 Resumen de la fase 2

| Severidad | Conteo | Pendiente |
|-----------|--------|-----------|
| P0 | 2 | corregir en código del frontend antes del rediseño |
| P1 | 2 | corregir en código del frontend |
| P2 | 6 | correcciones menores o rediseño |
| P3 | 1 | revisar con QA (catálogos stale al cambiar empresa) |

Los dos P0 resuelven los defectos de fondo (`price` vacío y
`customer_id` perdido en payments). El P1 de `phone` y los P2 son
oportunidades para limpiar mientras se rediseñan los filtros.

## Próxima fase

Fase 3 — decidir arquitectura. El plan pide comparar ampliar
`BaseFilterWrapper`, componer con grid, o crear pieza nueva. Vamos a
esa decisión con la información recogida.

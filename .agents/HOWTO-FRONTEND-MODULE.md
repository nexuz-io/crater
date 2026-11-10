# Cómo crear y registrar el frontend de un nuevo módulo

> Documento de referencia (no committeado — vive en `.agents/`).
> Última revisión: 2026-11-09.
> Stack: Vue 3 + Pinia + vue-i18n + Vue Router 4 + Tailwind 2 + Vite 2.
> Ejemplo canónico: módulo **`items`** (`/admin/items`). Patrón
> alternativo (panel maestro-detalle): `calculator/View.vue`.

Relacionado con `HOWTO-CREATE-MODULE.md` (backend) y plan 01
(consistencia de traducciones).

---

## 1. Vista general del flujo

Cada clic del usuario recorre estos puntos:

```
URL → Vue Router → meta.ability → vista Vue → store Pinia → axios → /api/v1/*
                                                      ↓
                                            useNotificationStore
                                            (toast success/error)
```

Si una pieza está mal conectada, la app **silenciosamente** se rompe:
- ruta sin `meta.ability` → cualquier usuario entra aunque no deba.
- store que no usa `useNotificationStore` → el usuario no sabe si la
  acción funcionó.
- `$t('xxx')` sin clave en algún locale → **warning** en consola del
  navegador, no error.

Antes de empezar, entiende este grafo mental — los pasos siguientes lo
rellenan.

## 2. Habilidades (abilities)

### 2.1. Backend ya registrado

Las abilities se definieron en el backend (`config/abilities.php`,
descrito en `HOWTO-CREATE-MODULE.md` §2). El frontend necesita saber
qué keys string comparar.

### 2.2. Frontend: `stub/abilities.js`

Editar `resources/scripts/admin/stub/abilities.js`. El archivo exporta
un objeto plano con constantes que apuntan al string de ability
backend. Patrón:

```js
// resources/scripts/admin/stub/abilities.js
// Items
CREATE_ITEM: 'create-item',
DELETE_ITEM: 'delete-item',
EDIT_ITEM: 'edit-item',
VIEW_ITEM: 'view-item',
```

Para tu módulo:

```js
// Mi Modulo
CREATE_MI_MODULO: 'create-mi-modulo',
DELETE_MI_MODULO: 'delete-mi-modulo',
EDIT_MI_MODULO: 'edit-mi-modulo',
VIEW_MI_MODULO: 'view-mi-modulo',
```

Mismas strings que en `config/abilities.php` — son la "clave única"
del ability entre capas.

### 2.3. Uso dentro de una vista

```js
import abilities from '@/scripts/admin/stub/abilities'

if (userStore.hasAbilities(abilities.CREATE_ITEM)) { ... }
```

`userStore.hasAbilities(...)` ya hace el chequeo contra el array
`userStore.currentUser.abilities` (poblado al login).

## 3. Routing (Vue Router)

### 3.1. Dónde tocar

`resources/scripts/admin/admin-router.js`. Las rutas de admin van
**dentro** del grupo `path: '/admin'` (línea 167 de ese archivo),
que carga `LayoutBasic` y exige `requiresAuth: true`.

### 3.2. Patrón CRUD estándar

```js
// 1. import lazy-load (NO importes arriba con import estático)
const MiModuloIndex = () =>
  import('@/scripts/admin/views/mi-modulo/Index.vue')
const MiModuloCreate = () =>
  import('@/scripts/admin/views/mi-modulo/Create.vue')

// 2. dentro del children del bloque /admin
{
  path: 'mi-modulo',
  meta: { ability: abilities.VIEW_MI_MODULO },
  component: MiModuloIndex,
},
{
  path: 'mi-modulo/create',
  name: 'mi-modulo.create',
  meta: { ability: abilities.CREATE_MI_MODULO },
  component: MiModuloCreate,
},
{
  path: 'mi-modulo/:id/edit',
  name: 'mi-modulo.edit',
  meta: { ability: abilities.EDIT_MI_MODULO },
  component: MiModuloCreate,   // ← misma vista, detecta :id y carga
},
```

> **Importante**: el `path: 'mi-modulo'` (lista) **no** lleva `name:`
> (sigue la convención del proyecto). Las acciones sí llevan nombre
> porque el botón "guardar" o el breadcrumb los nombra para `to="..."`.

### 3.3. meta especiales

| Meta              | Efecto                                                      |
|-------------------|-------------------------------------------------------------|
| `ability: '...'`  | El route guard `beforeEach` redirige si el user no la tiene |
| `isOwner: true`   | Sólo el owner de la company puede acceder                   |
| `requiresAuth`    | true en /admin, false en /login, etc.                       |

### 3.4. Variante "View con panel lateral"

Si el módulo tiene una vista tipo panel maestro-detalle
(como Calculator), una misma página cubre lista + detalle en
URLs distintas pero usando el mismo componente:

```js
{
  path: 'calculators',
  name: 'calculators.index',
  meta: { ability: abilities.VIEW_CALCULATOR },
  component: CalculatorView,
},
{
  path: 'calculators/:id/view',
  name: 'calculators.view',
  meta: { ability: abilities.VIEW_CALCULATOR },
  component: CalculatorView,
},
```

Dentro del `.vue` se inspecciona `route.params.id` y `route.name`
para decidir si mostrar lista vacía, lista con selección, o detalle.

## 4. Store Pinia

### 4.1. Ubicación

`resources/scripts/admin/stores/<modulo>.js`. Nombre en **kebab-case
singular** (`item.js`, `customer.js`, `calculator.js` — para módulo
"Mi Modulo" el archivo es `mi-modulo.js`).

### 4.2. Estructura base (copiar `item.js`)

```js
// resources/scripts/admin/stores/mi-modulo.js
import axios from 'axios'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useMiModuloStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n   // i18n vía window (NO via useI18n)

  return defineStoreFunc({
    id: 'mi-modulo',
    state: () => ({
      miModulos: [],
      totalMiModulos: 0,
      selectAllField: false,
      selectedMiModulos: [],
      currentMiModulo: {
        name: '',
        description: '',
        // ...campos del form, con valores por defecto seguros
      },
    }),
    getters: {
      isEdit: (state) => !!state.currentMiModulo.id,
    },
    actions: {
      resetCurrentMiModulo() { /* vacía currentMiModulo */ },

      fetchMiModulos(params) {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/mi-modulos', { params })
            .then((response) => {
              this.miModulos = response.data.data
              this.totalMiModulos = response.data.meta.mi_modulo_total_count
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchMiModulo(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/mi-modulos/${id}`)
            .then((response) => {
              if (response.data) {
                Object.assign(this.currentMiModulo, response.data.data)
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addMiModulo(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/mi-modulos', data)
            .then((response) => {
              this.miModulos.push(response.data.data)
              this._notifySuccess(global.t('mi_modulo.created_message'))
              resolve(response)
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      updateMiModulo(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/mi-modulos/${data.id}`, data)
            .then((response) => {
              const i = this.miModulos.findIndex(
                (x) => x.id === response.data.data.id
              )
              if (i !== -1) this.miModulos[i] = response.data.data
              this._notifySuccess(global.t('mi_modulo.updated_message'))
              resolve(response)
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      deleteMiModulo(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/mi-modulos/delete`, id)
            .then(() => {
              this.miModulos = this.miModulos.filter((x) => x.id !== id)
              this._notifySuccess(global.tc('mi_modulo.deleted_message', 1))
              resolve()
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      deleteMultipleMiModulos() {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/mi-modulos/delete`, { ids: this.selectedMiModulos })
            .then(() => {
              this.selectedMiModulos = []
              this.selectAllField = false
              this._notifySuccess(global.tc('mi_modulo.deleted_message', 2))
              resolve()
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      _notifySuccess(message) {
        useNotificationStore().showNotification({ type: 'success', message })
      },
    },
  })()
}
```

### 4.3. Antipatrones frecuentes en el store

- ❌ Usar `useI18n().t(...)` dentro del store → pinia se llama en
  setup, el composable no está montado en ese momento. **Usar
  `window.i18n.global.t('...')`**. Referencia:
  `resources/scripts/admin/stores/item.js:8`.
- ❌ Llamar `useNotificationStore()` en el `state()` → debe ser
  **dentro** de cada action, ya que el store puede no existir
  todavía.
- ❌ Hardcodear mensajes en francés o inglés. **Siempre**
  `global.t('mi_modulo.foo')`.
- ❌ Olvidar `handleError(err)` en `.catch()` → la app muestra
  spinner infinito en lugar de un toast de error.

### 4.4. Plurales i18n

`global.t('mi_modulo.deleted_message', 1)` con `tc(..., n)` para
plurales (singular vs plural según idioma). Los locales usan la
convención ICU-ish `"foo": "uno | muchos"`.

## 5. Vistas Vue

### 5.1. Ubicación

`resources/scripts/admin/views/<modulo>/`. Convención:

- `Index.vue` — lista + acciones masivas (botón nuevo, filtros).
- `Create.vue` — formulario. **Sirve también como Edit**,
  detecta `:id` desde la ruta para cargar y cambiar copy.
- `View.vue` — opcional, sólo para vista de panel maestro-detalle
  (Calculator) o de sólo-lectura.

### 5.2. Esqueleto de `Index.vue`

```vue
<template>
  <BasePage>
    <BasePageHeader :title="$t('mi_modulo.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('mi_modulo.title')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(abilities.CREATE_MI_MODULO)"
          @click="$router.push('/admin/mi-modulo/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('mi_modulo.add_new') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" class="mt-5" @clear="clearFilter">
      <!-- BaseInputGroup con input + selectores -->
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder v-show="showEmptyScreen"
      :title="$t('mi_modulo.no_items')"
      :description="$t('mi_modulo.list_of_items')"
    >
      <SatelliteIcon class="mt-5 mb-4" />
      <BaseButton @click="$router.push('/admin/mi-modulo/create')">
        {{ $t('mi_modulo.add_new') }}
      </BaseButton>
    </BaseEmptyPlaceholder>

    <!-- Tabla / lista cuando hay datos -->
    <BaseTable v-show="!showEmptyScreen" ... />
  </BasePage>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useMiModuloStore } from '@/scripts/admin/stores/mi-modulo'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'

const miModuloStore = useMiModuloStore()
const userStore = useUserStore()
const showFilters = ref(false)
const showEmptyScreen = computed(() =>
  !miModuloStore.totalMiModulos && !miModuloStore.miModulos.length
)
const filters = ref({ name: '', active: null })

function clearFilter() { filters.value = { name: '', active: null } }
function toggleFilter() { showFilters.value = !showFilters.value }

async function loadItems() {
  await miModuloStore.fetchMiModulos({
    name: filters.value.name || undefined,
    active: filters.value.active ?? undefined,
  })
}

onMounted(loadItems)
</script>
```

### 5.3. Esqueleto de `Create.vue` (que también es Edit)

```vue
<template>
  <BasePage>
    <BasePageHeader :title="pageTitle">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('mi_modulo.title')" to="/admin/mi-modulo" />
        <BaseBreadcrumbItem :title="pageTitle" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton variant="primary-outline" :loading="isSaving" @click="onSave">
          {{ isEdit ? $t('mi_modulo.update') : $t('mi_modulo.save') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseContentPlaceholders v-if="isLoading" :rows="6" />

    <div v-else class="grid grid-cols-12 gap-6 mt-6">
      <BaseInputGroup :label="$t('mi_modulo.name')" class="col-span-12 md:col-span-6">
        <BaseInput v-model="miModuloStore.currentMiModulo.name"
                   type="text" name="name" />
      </BaseInputGroup>

      <BaseInputGroup class="col-span-12 md:col-span-6"
                      :label="$t('mi_modulo.active')">
        <BaseSwitch v-model="miModuloStore.currentMiModulo.is_active"
                    :label-right="$t('mi_modulo.active_help')" />
      </BaseInputGroup>

      <BaseInputGrid class="col-span-12">
        <BaseTextarea :label="$t('mi_modulo.description')"
                      v-model="miModuloStore.currentMiModulo.description" />
      </BaseInputGrid>
    </div>
  </BasePage>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMiModuloStore } from '@/scripts/admin/stores/mi-modulo'

const route = useRoute()
const router = useRouter()
const miModuloStore = useMiModuloStore()
const isSaving = ref(false)
const isLoading = computed(() =>
  !!route.params.id && !miModuloStore.currentMiModulo.id
)
const isEdit = computed(() => !!route.params.id)
const pageTitle = computed(() =>
  isEdit.value ? $t('mi_modulo.edit') : $t('mi_modulo.create')
)

async function onSave() {
  isSaving.value = true
  try {
    if (isEdit.value) {
      await miModuloStore.updateMiModulo(miModuloStore.currentMiModulo)
    } else {
      await miModuloStore.addMiModulo(miModuloStore.currentMiModulo)
    }
    router.push('/admin/mi-modulo')
  } finally {
    isSaving.value = false
  }
}

onMounted(async () => {
  miModuloStore.resetCurrentMiModulo()
  if (route.params.id) {
    await miModuloStore.fetchMiModulo(route.params.id)
  }
})
</script>
```

**Punto crítico**: `Create.vue` cubre **create + edit** usando la
misma ruta `:id/edit`. El componente detecta `route.params.id` y
cambia copy + comportamiento (`$t('mi_modulo.save')` →
`$t('mi_modulo.update')`).

## 6. Componentes Base disponibles (lista curada)

Toda la familia vive en `resources/scripts/components/base/`. **No
crees un botón / input / modal propio** si existe equivalente; los
estilos están centralizados.

| Componente | Uso típico |
|---|---|
| `BasePage` | Envoltorio principal. Cualquier vista debe empezar con esto. |
| `BasePageHeader` | Header con título, breadcrumb y slot `actions` para los botones de la derecha. |
| `BaseBreadcrumb` + `BaseBreadcrumbItem` | Migas de pan. `to` puede ser ruta name o string. |
| `BaseButton` | Botón principal. Variantes: `primary`, `primary-outline`, `gray`, `danger`. Slot `left` / `right` para iconos. |
| `BaseIcon` | `<BaseIcon name="PlusIcon" />`. Lista: `resources/scripts/components/hero-icons.js`. |
| `BaseCard` | Tarjeta estándar. Útil para agrupar info. |
| `BaseInput` / `BaseTextarea` / `BaseInputGroup` | Inputs con label integrado. **`BaseInputGroup` es el wrapper obligatorio**. |
| `BaseInputGrid` | Grid para layout horizontal de inputs múltiples. |
| `BaseMoney` | Input dinero con formato `"2 345,67 €"` (via `v-money3`). |
| `BaseMultiselect` | <multiselect> envuelto. Props: `options`, `value-prop`, `track-by`, `label`, `searchable`, etc. |
| `BaseSwitch` | Switch on/off con label a la derecha. |
| `BaseDatePicker` / `BaseTimePicker` | Controles de fecha y hora. |
| `BaseFilterWrapper` | Caja agrupadora de filtros con botón "clear". |
| `BaseEmptyPlaceholder` | Estado vacío con icono + descripción + CTA. |
| `BaseDialog` | Modal genérico (`is-open`, `title`, slot default y footer). |
| `BaseDropdown` + `BaseDropdownItem` | Menú desplegable. (calculador/delete). |
| `BaseTable` | Tabla estándar con columnas y slot `actions` por fila. |
| `BaseNotification` | Para llamar manualmente (los stores ya lo manejan). |
| `BaseSettingCard` | Una tarjeta usada en Settings. |
| `BaseWizard` + `WizardStep` + `WizardNavigation` | Para flujos multi-paso (instalación, onboarding). |
| `BaseContentPlaceholders` / Boxes | Skeleton loaders. |
| `BaseErrorAlert` / `BaseInfoAlert` | Banner de error / info. |
| `BaseGlobalLoader` | Loader encima de toda la pantalla. |

## 7. Iconos

Se cargan **explícitamente** en `resources/scripts/components/hero-icons.js`
porque Rollup/Vite hace tree-shaking y sin imports explícitos los
iconos referenciados por nombre **se pierden en prod** (es una trampa
clásica del proyecto). Ver el aviso en `DEVELOPPEMENT-LOCAL.md`.

Para usar uno nuevo:

1. Agregar el import en `hero-icons.js`.
2. Usar `<BaseIcon name="MiIcon" />` en la vista.

El nombre es **PascalCase** (`CalculatorIcon`, `PlusIcon`, etc.) — la
lista completa está en código, sólo revisando `hero-icons.js`.

## 8. Traducciones (i18n)

### 8.1. Archivos

**31 locales** en `resources/scripts/locales/*.json`:

```
ar.json  cs.json  de.json  el.json  en.json  es.json  fa.json
fi.json  fr.json  hi.json  hr.json  id.json  it.json  ja.json
ko.json  lt.json  lv.json  nl.json  pl.json  pt-br.json  pt.json
ro.json  ru.json  sk.json  sl.json  sr.json  sv.json  th.json
tr.json  vi.json  zh.json
```

El handshake con `i18n` está en `resources/scripts/locales/locales.js`
y se carga globalmente como `window.i18n`. La función es
`window.i18n.global.t('...')` (no se usa `useI18n` en stores).

### 8.2. Convención de claves

Agrupá siempre bajo el nombre del módulo:

```json
{
  "mi_modulo": {
    "title": "Mi Módulo",
    "add_new": "Nuevo",
    "edit": "Editar",
    "create": "Crear",
    "save": "Guardar",
    "update": "Actualizar",
    "name": "Nombre",
    "description": "Descripción",
    "active": "Activo",
    "active_help": "Cuando está activo aparece en la lista",
    "no_items": "¡Aún no hay elementos!",
    "list_of_items": "Esta sección contendrá la lista.",
    "created_message": "Elemento creado con éxito",
    "updated_message": "Elemento actualizado",
    "deleted_message": "Elemento eliminado | Elementos eliminados"
  }
}
```

### 8.3. Plurales con `tc(...)`

```vue
{{ $tc('mi_modulo.deleted_message', count) }}   <!-- plural automático -->
```

El locale debe tener la barra `|` separating singular | plural (o la
forma ICU si querés casos como 0/1/many).

### 8.4. Plan 01 — Consistencia

Antes de mergear, **revisá** que la palabra que elegiste (ej.
"Servicios") sea la misma en:

- `mi_modulo.title` (menú lateral y header).
- `mi_modulo.breadcrumb` (si hay uno específico).
- Etiquetas de botones de crear/editar.

Si ves inconsistencias como las que arregló el plan 01 (mezcla
"Servicios"/"Artículos" en español), abrí un plan nuevo antes de
mergear el feature.

## 9. Menú lateral

### 9.1. ¿De dónde sale el sidebar?

El componente `LayoutBasic` renderiza el sidebar leyendo
`config/crater.php → main_menu` + `settings_menu`. Las entradas se
exponen al front vía una de las APIs bootstrap (ver
`app/Http/Controllers/V1/Admin/General/BootstrapController.php`).

### 9.2. Cuándo aparece / desaparece

- La entrada existe en `main_menu` o `settings_menu`. **Siempre** se
  incluye al renderizar.
- **Se oculta** si el usuario no tiene el `ability` declarado en la
  entrada (chequeo client-side en el componente sidebar).
- Se oculta además si `owner_only => true` y el usuario no es owner.

> Por lo tanto: una vez que agregás la entrada al `config/crater.php`
> **y** el ability correspondiente, el sidebar se actualiza solo.

## 10. Verificación end-to-end

Antes de mergear:

```bash
yarn build        # 1. errores de compilación Vue / i18n
yarn lint
```

Y a mano:

1. Login → sidebar muestra la nueva entrada.
2. Click → navega a `/admin/mi-modulo`. Aparece empty placeholder.
3. Botón "Nuevo" → formulario. Llenar campos. Save.
4. Toast verde. Vuelve a la lista. Aparece el nuevo.
5. Editar el mismo elemento → cambia título a "Editar". Save → toast
   verde, lista actualizada.
6. Refrescar página → sigue ahí (prueba que el backend funciona).
7. Bulk-select 2 elementos → botón "Eliminar" → toast verde, etc.
8. En una pestaña aparte con un usuario sin permiso `view-mi-modulo`:
   la entrada del sidebar **no aparece**.

## 11. Antipatrones y gotchas

| Antipatrón | Cómo lo evito |
|---|---|
| Importar un icono sin ponerlo en `hero-icons.js`. | Siempre agregar el import o usar `SatelliteIcon` etc. ya registrados. |
| Usar `useI18n()` en un store. | Usar `window.i18n.global.t(...)`. |
| Llamar a API con axios sin token. | El interceptor está en `resources/scripts/http`. No tocar. |
| Llamada `$t('mi_modulo.sub.sub.sub')` con claves anidadas a 4+ niveles profundo. | Mantener jerarquía ≤ 3. |
| Crear un componente Button propio. | Usar BaseButton. |
| Olvidar `:required="true"` en inputs obligatorios. | Validar siempre del lado form además del FormRequest. |
| Sidebar no muestra tu entrada. | Check (1) config/crater.php tiene la entrada, (2) ability existe, (3) user actual tiene el ability, (4) no es `owner_only` aplicado a no-owner. |
| Build falla con `Cannot find module '@/...'`. | El alias `@` → `resources/scripts/`. Si tenés duda, revisá `vite.config.js → resolve.alias`. |
| `yarn build` ok pero iconos faltan en prod. | Tree-shaking: revisá `hero-icons.js`. (Documentado en `DEVELOPPEMENT-LOCAL.md` §"Pièges connus du build"). |

## 12. Resumen del checklist para crear el frontend de un módulo

- [ ] Abilities añadidas en `resources/scripts/admin/stub/abilities.js`
- [ ] Rutas añadidas en `resources/scripts/admin/admin-router.js`
      con `meta.ability`
- [ ] Store Pinia creado en `resources/scripts/admin/stores/<modulo>.js`
      con `fetch<Plural>`, `fetch<Singular>`, `add`, `update`,
      `delete<Singular>`, `deleteMultiple<Singular>`
- [ ] `Index.vue` (lista + filtros + empty placeholder + bulk actions)
- [ ] `Create.vue` (alta + edición, detecta `:id`)
- [ ] `View.vue` opcional si aplica el patrón maestro-detalle
- [ ] Locales `mi_modulo.*` añadidos en los **31** JSON
- [ ] Entrada en `config/crater.php → main_menu` o `settings_menu`
- [ ] `yarn build` ok sin warnings de i18n
- [ ] Smoke test manual (login → listado → crear → editar → borrar
   → bulk delete → cambio de idioma).

---

> **Recordá**: vue-i18n resuelta **tienpo de build**. Si ves
> `Not found 'mi_modulo.title'` en consola del browser tras añadir
> claves, hace falta `yarn build` (o reiniciar `yarn dev`).

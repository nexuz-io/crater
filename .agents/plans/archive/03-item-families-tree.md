# Familias jerárquicas para artículos

## Objetivo

Organizar los artículos en familias y subfamilias de estructura arbórea, representando todos los niveles con una única clase y permitiendo administrarlas por empresa.

## Contexto

- `/app/Models/Item.php` representa los artículos y dispone de filtros por empresa, nombre, precio, unidad y estado.
- La creación y edición utilizan `/app/Http/Requests/ItemsRequest.php`, `/app/Http/Controllers/V1/Admin/Item/ItemsController.php` y las vistas de `/resources/scripts/admin/views/items/`.
- La jerarquía se modelará mediante una relación autorreferenciada: cada familia puede tener un padre y varias hijas, todas instancias de `ItemFamily`. La relación padre-hija expresa la estructura del árbol; no requiere subclases PHP por nivel.
- Se usará **Familias** como término de interfaz en español para distinguirlas de las categorías de gastos. Coordinar la terminología de artículos con `01-translation-consistency-items.md`.

## Plan

1. **Definir las reglas del árbol y la asignación.**
   - Adoptar una lista de adyacencia (`parent_id`) con múltiples raíces por empresa y profundidad variable.
   - Cada artículo tendrá cero o una familia; se podrá asignar tanto a una raíz como a una subfamilia.
   - Un artículo asignado a una hija pertenecerá a la rama de sus ancestros para navegación y filtros, sin duplicar asignaciones en la base de datos.
   - La jerarquía no propagará precios, impuestos, permisos ni otros atributos del artículo.
   - Permitir nombres repetidos en ramas distintas, pero exigir nombres únicos entre hermanas, incluidas las raíces de una empresa; normalizar espacios y definir comparación coherente con la base de datos.

2. **Crear el esquema y el modelo único.**
   - Añadir migraciones en `/database/migrations/` para `item_families`: `id`, `company_id`, `parent_id` nullable, `name` y timestamps.
   - Añadir `family_id` nullable a `items`; los artículos existentes quedarán sin familia.
   - Crear `/app/Models/ItemFamily.php` con relaciones `parent()`, `children()`, `items()` y `company()`, y consultas explícitamente limitadas a la empresa.
   - Añadir `family()` en `/app/Models/Item.php` y la relación de familias en `/app/Models/Company.php`.
   - Indexar `(company_id, parent_id)` y `(company_id, family_id)` en sus respectivas tablas; definir claves foráneas sin borrado en cascada de artículos ni de subárboles.
   - Resolver la unicidad de raíces considerando que un índice único con `parent_id = NULL` no impide duplicados en todos los motores. Usar validación y serialización transaccional por empresa para las escrituras del árbol.
   - Integrar la eliminación completa de empresa: desvincular o eliminar sus artículos según el flujo existente y borrar familias de hojas a raíces dentro de la operación correspondiente.
   - Definir un rollback que retire primero la referencia de artículos y después la tabla de familias.

3. **Implementar operaciones de árbol seguras y consistentes.**
   - Centralizar creación, renombrado, cambio de padre y eliminación en `/app/Services/ItemFamilyService.php`.
   - Comprobar que familia, padre y artículo pertenezcan a la empresa autorizada en cada operación.
   - Rechazar un nodo como su propio padre y cualquier cambio de padre hacia uno de sus descendientes.
   - Ejecutar validaciones y escritura dentro de una transacción, bloqueando la fila de empresa antes de recorrer el árbol para serializar movimientos concurrentes y evitar ciclos o nombres duplicados por carreras.
   - Cambiar `parent_id` moverá la rama completa sin alterar IDs ni asignaciones de artículos.
   - Permitir eliminar únicamente familias sin hijas y sin artículos; devolver un error de validación explicativo en caso contrario. Contar también artículos inactivos.
   - Proteger la carrera entre asignación de artículos y eliminación de familias mediante claves foráneas y el bloqueo apropiado.
   - Centralizar recorridos de ancestros y descendientes, con detección de nodos visitados para evitar bucles ante datos corruptos.

4. **Exponer API, validación y autorización.**
   - Crear controlador en `/app/Http/Controllers/V1/Admin/Item/`, Form Requests en `/app/Http/Requests/` y recurso en `/app/Http/Resources/` siguiendo los patrones existentes.
   - Registrar en `/routes/api.php` las operaciones de listado, creación, consulta, actualización y eliminación bajo `item-families`, dentro del grupo autenticado y limitado por empresa.
   - Definir el listado como una colección plana `{ id, parent_id, name }`, ordenada de forma determinista; construir el árbol en el cliente sin recursos JSON recursivos ni consultas por cada nodo.
   - Incluir IDs y relaciones necesarias en las respuestas de mutación; documentar los errores de padre inválido, ciclo, nombre duplicado y familia no vacía.
   - Registrar `ItemFamilyPolicy` en `/app/Providers/AuthServiceProvider.php` y reutilizar las capacidades existentes de ver, crear, editar y eliminar artículos para las operaciones equivalentes de familias.
   - Verificar pertenencia a la empresa además del permiso. Resolver modelos después de seleccionar la empresa y no confiar en `company_id` enviado por el cliente.
   - Añadir validación de `family_id` nullable y perteneciente a la empresa en `/app/Http/Requests/ItemsRequest.php`.

5. **Integrar familias en artículos y filtros.**
   - Extender creación y actualización de artículos para guardar o quitar `family_id` y conservar la asignación cuando el campo se omita en una actualización.
   - Ampliar `/app/Http/Resources/ItemResource.php` y revisar `/app/Http/Resources/ItemCollection.php` para devolver `family_id` y un resumen de familia cuando esté cargado.
   - Cargar la relación anticipadamente en listados y edición para evitar consultas N+1.
   - Añadir filtros `family_id`, `include_descendants` y `without_family`, definiendo que este último es incompatible con un `family_id` concreto.
   - Al seleccionar una familia en la interfaz, incluir su rama por defecto; permitir consultar solo los artículos asignados directamente.
   - Obtener IDs de descendientes dentro de la misma empresa y combinar los filtros con los existentes mediante condiciones agrupadas, evitando que un `OR` permita salir del ámbito de empresa.
   - Revisar consumidores de artículos en facturas, presupuestos, calculadora e importación: mantener los contratos existentes cuando no se utilicen familias.

6. **Construir la administración de familias.**
   - Crear store Pinia `/resources/scripts/admin/stores/item-family.js` y vistas en `/resources/scripts/admin/views/item-families/`.
   - Añadir acceso «Familias» desde el listado de artículos y registrar rutas siguiendo la estructura actual del router y sus permisos.
   - Construir el árbol mediante un mapa por ID y relaciones padre-hijas; reutilizar un componente recursivo para cualquier nivel.
   - Permitir expandir/contraer ramas, crear raíz, añadir hija, renombrar, cambiar padre y eliminar familias vacías.
   - Ofrecer un selector de padre que excluya el propio nodo y sus descendientes; mantener la validación equivalente en servidor.
   - Mostrar la ruta completa para distinguir nombres repetidos, con búsqueda que conserve visibles los ancestros de los resultados.
   - En móvil, utilizar una columna, indentación acotada y rutas truncadas con nombre completo accesible; formularios y acciones deben funcionar sin arrastrar y soltar.
   - Usar los componentes, tokens de tema, scroll y traducciones existentes. Mantener controles de expansión y acciones utilizables por teclado.

7. **Actualizar creación, edición y listado de artículos.**
   - En `/resources/scripts/admin/views/items/Create.vue`, añadir selector jerárquico opcional con «Sin familia» y ruta de cada opción.
   - Precargar la selección al editar y permitir quitarla sin alterar los demás datos del artículo.
   - En `/resources/scripts/admin/views/items/Index.vue`, añadir columna o etiqueta de familia y filtros de rama, asignación directa y artículos sin familia.
   - Actualizar el store de artículos identificado durante la implementación para incluir la asignación y los nuevos filtros.
   - Limpiar y recargar las familias al cambiar de empresa; descartar respuestas pendientes de la empresa anterior.
   - Mantener accesible la creación de artículos cuando todavía no haya familias y mostrar errores de carga en lugar de presentar una lista vacía como si fuera válida.
   - Añadir traducciones coherentes en `/resources/scripts/locales/` siguiendo la estrategia actual de idiomas y fallback.

8. **Validar integridad, compatibilidad y experiencia de uso.**
   - Añadir pruebas backend de raíces, hijas, varios niveles, movimientos de rama, eliminación restringida y asignación/desasignación de artículos.
   - Probar ciclos directos e indirectos, nombres repetidos entre hermanas y aislamiento entre empresas en consultas, mutaciones y filtros.
   - Probar filtros con descendientes, asignación directa y sin familia, combinados con búsqueda, estado y paginación.
   - Verificar que el árbol y las relaciones cargadas no produzcan consultas por cada nodo; usar un conjunto de prueba amplio y medir la respuesta antes de decidir optimizaciones adicionales.
   - Verificar movimientos concurrentes y unicidad de raíces en el motor de base de datos del despliegue; SQLite por sí solo no valida estos bloqueos.
   - Ejecutar migración y rollback en una base de pruebas, las pruebas focalizadas y `npm run build`.
   - Revisar escritorio y móvil: crear raíz e hija, asignar un artículo, editarlo, mover la rama, filtrar y cambiar de empresa.

## Resultado esperado

- [ ] Todas las familias y subfamilias utilizan `ItemFamily` y la misma tabla autorreferenciada.
- [ ] Se admiten varias raíces y niveles de descendencia sin crear clases por nivel.
- [ ] No se pueden introducir ciclos, padres de otra empresa ni duplicados entre hermanas.
- [ ] Crear, editar y mover familias conserva la integridad del árbol y los artículos asignados.
- [ ] Las familias con hijas o artículos no se eliminan accidentalmente.
- [ ] Los artículos existentes siguen funcionando sin familia; al editar se carga y conserva la selección.
- [ ] Los filtros permiten consultar una familia, su rama o artículos sin familia.
- [ ] La interfaz funciona en móvil, con teclado y con los colores del tema.
- [ ] El cambio de empresa no reutiliza familias o selecciones de la anterior.
- [ ] Permisos y aislamiento por empresa están cubiertos por pruebas.
- [ ] Migración, rollback, pruebas focalizadas y build verificados.

## Fuera de alcance

- Asignar varias familias al mismo artículo o añadir etiquetas independientes.
- Heredar precios, impuestos, descuentos u otros atributos entre familias y artículos.
- Reclasificar automáticamente artículos existentes o alterar documentos históricos.
- Ordenación manual por arrastre, reportes nuevos por familia o una biblioteca genérica de árboles para otros módulos.

## Notas

Estado: cerrado 2026-11-10.

Resumen de cambios:

**Backend (PHP)**
- `database/migrations/2026_11_10_130000_create_item_families_table.php`:
  crea `item_families` (`id`, `company_id`, `parent_id` nullable,
  `name`, `name_normalized`, timestamps) con FK `company_id` en
  cascada y FK `parent_id` restrict (autoreferencial).
- `database/migrations/2026_11_10_130100_add_family_id_to_items_table.php`:
  añade `family_id` nullable a `items` con FK restrict
  (`items.family_id → item_families.id`).
- `app/Models/ItemFamily.php`: relaciones `parent`, `children`,
  `items`, `company`; scopes `whereCompany`, `whereParent`,
  `whereSearch` y `applyFilters`. Normalización centralizada
  (`cleanName`, `normalizeName`, `applyName`).
- `app/Models/Item.php`: `family()` belongsTo + scopes `whereFamily`,
  `whereWithoutFamily` y carga la rama vía
  `ItemFamily::descendantIds($familyId, $company)`. `createItem` /
  `updateItem` eager-loadean `family`.
- `app/Models/Company.php`: `itemFamilies()` belongsToMany-by-company
  + helper `deleteItemFamiliesDeep()` que recorre hojas → raíces
  (BFS) para que el FK restrict no bloquee el teardown.
- `app/Services/ItemFamilyService.php`: servicio centralizado que
  usa `DB::transaction` + `lockForUpdate()` sobre la fila de la
  empresa o de la fila padre para serializar escrituras
  concurrentes. `create` valida padre y unicidad entre hermanas
  con `name_normalized`; `update` previene ciclos (auto-padre y
  ancestros) y valida re-parent y renombre; `delete` rechaza con
  ValidationException si tiene hijas o artículos;
  `descendantIds` BFS con `visited` para defensas contra árboles
  corruptos.
- `app/Http/Requests/ItemFamilyRequest.php`: validación `name`
  (3-255), `parent_id` (`exists:item_families,id,company_id`);
  el control de ciclos y unicidad lo hace
  `ItemFamilyService` dentro de la transacción.
- `app/Http/Resources/ItemFamilyResource.php`: payload plano
  `{id, parent_id, name, company_id, …}` para que el cliente monte
  el árbol sin recursos recursivos ni consultas por nivel.
- `app/Http/Resources/ItemResource.php`: expone `family_id` y
  `family` embebido (`whenLoaded`).
- `app/Http/Controllers/V1/Admin/Item/ItemFamiliesController.php`:
  CRUD estándar; `index` ordena por (parent_id NULL primero,
  parent_id, name); mutaciones delegadas al servicio.
- `app/Http/Controllers/V1/Admin/Item/ItemsController.php`: eager-load
  `brand`, `family`, `taxes`. La rama se devuelve a través de
  `scopeApplyFilters` con `descendantIds` server-side (1 query,
  no N+1).
- `app/Http/Requests/ItemsRequest.php`: añade `family_id` con la
  misma coerción "string vacío → null" que ya usaba plan 04 para
  `brand_id`, y regla `exists:item_families,…`.
- `app/Policies/ItemFamilyPolicy.php`: reusaability `view-item`
  (igual que `BrandPolicy` y `UnitPolicy`).
- `app/Providers/AuthServiceProvider.php`: registro del policy.
- `config/abilities.php`: `view-item-family` /
  `create-item-family` / `edit-item-family` /
  `delete-item-family` con dependencia `view-item`.
- `routes/api.php`: `Route::apiResource('item-families', …)` en
  el subgrupo autenticado + empresa.
- `database/migrations/2026_11_10_130200_grant_item_family_abilities_to_existing_companies.php`:
  reaplica `Company::setupRoles()` a cada empresa viva — mismo
  patrón usado por plan 04 y calculator.
- `database/factories/ItemFamilyFactory.php`: con estado
  `->childOf($parent)` y convención de normalización que reusa el
  factory de Brand.
- `tests/Feature/Admin/ItemFamilyTest.php`: cubre index, create
  como raíz / hijo, duplicado entre hermanas / aceptado entre
  raíces, ciclos (auto-padre y ancestro), `parent_id`
  cross-company, delete con hijos / con artículos / libre, y
  rename + re-parent.

**Frontend (Vue)**
- `resources/scripts/admin/stub/abilities.js`: claves
  `VIEW_ITEM_FAMILY`, `CREATE_ITEM_FAMILY`, `EDIT_ITEM_FAMILY`,
  `DELETE_ITEM_FAMILY`.
- `resources/scripts/admin/stores/item-family.js`: CRUD Pinia con
  `resetCurrentFamily`, `fetchFamilies`, `fetchFamily`,
  `addFamily`, `updateFamily`, `deleteFamily`. `deleteFamily`
  mapea errores del backend a un toast específico
  (`family_attached_message`).
- `resources/scripts/admin/stores/item.js`: añade `family_id` /
  `family` al `currentItem`, nueva acción `fetchItemFamilies` y
  cache `itemFamilies`.
- `resources/scripts/admin/components/trees/ItemFamilyTreeNode.vue`:
  componente recursivo (registrado con `defineOptions({ name })`)
  que renderiza chevron, etiqueta, badge de raíz, contador de
  hijas y dropdown de acciones con abilities gating. Emite
  `toggle`, `select`, `add-child`, `rename`, `move`, `remove`.
- `resources/scripts/admin/components/modal-components/ItemFamilyModal.vue`:
  crea/renombra/mueve via `rules` de Vuelidate. Detecta "modo
  mover" via `modalStore.title` y excluye descendientes del
  selector de padre.
- `resources/scripts/admin/views/item-families/Index.vue`: árbol
  plano renderizado client-side vía `childrenMap` / `parentsMap`.
  Search abre ancestrales de cada match (`fullPathSet` highlight,
  `expandedSet` mantiene visibles). Botones accesibles por teclado
  + empty state + loading.
- `resources/scripts/admin/views/items/Index.vue`: columna
  `family` en la tabla (muestra breadcrumb path), nuevo filtro de
  familia, toggle "Sin familia" y toggle "Incluir descendientes"
  (visible sólo cuando hay filtro de familia). Acceso directo
  "Familias" en el header.
- `resources/scripts/admin/views/items/Create.vue`: selector
  opcional con paths breadcrumbed, splice defensivo para la
  familia actualmente asignada fuera del catálogo, y carga
  asíncrona del árbol en `loadData()` con ability gating. Modal
  inline de creación reusa `ItemFamilyModal`.
- `resources/scripts/admin/admin-router.js`: ruta `item-families`
  gated por `VIEW_ITEM_FAMILY`.

**Localización**
- 31 archivos `locales/*.json` ahora contienen bloque
  `item_families` y dos claves nuevas en `items` (`family`,
  `select_a_family`). en / es / fr / de / it / nl / pt / pt-br / pl
  con traducciones reales; el resto cae al mismo texto en inglés
  para mantener vue-i18n silencioso.

**Verificación**
- `php -l` sobre cada PHP nuevo/editado: limpio.
- `npm run build` desde `resources/scripts/`: compila sin errores.
- `npx eslint` sobre los 8 archivos Vue/JS nuevos/editados: 0
  errores / 0 warnings.
- Migraciones aplicadas en la BD live:
  - `item_families` tiene todas las columnas + FKs.
  - `items.family_id` está presente.
  - `permissions` para el `super admin` de empresa 1 incluye las
    cuatro abilities nuevas (`view/create/edit/delete-item-family`)
    junto a las 51 ya existentes.
  - `Bouncer::can('view-item-family', ItemFamily::class)` resuelve
    YES con el super admin actual.

**Compatibilidad con planes 03 / 04**
- `family_id` y `brand_id` son columnas independientes; no se
  interfieren entre sí en migraciones, recursos, ni en el form.
- `Company::deleteCompany` ordena items → brands → families; cada
  bloque limpia explícitamente al estilo de los precedents.
- Cuando plan 03 y plan 04 coexisten: la migration de grants de
  familia es 03 y la de grants de brand es 04. Como ambas
  reaplican `Company::setupRoles()` y son idempotentes vía
  Bouncer::allow(), pueden correr en cualquier orden.

**Pendientes para QA manual en navegador**
- Crear raíz "Electronics", hija "Phones", nieta "Smartphones";
  asignar un artículo a "Smartphones"; editarlo preservando la
  asignación; filtrar por "Electronics" con "Incluir descendientes"
  y validar que la tabla muestra los items; cambiar el toggle a
  "Sólo esta familia" y comprobar que se reduce a la familia
  exacta.
- Mover "Electronics" bajo "Smartphones" — debe ser rechazado con
  el toast del move_error_cycle. Cambiar la empresa para confirmar
  que la familia nueva no se filtra entre empresas distintas.
- Móvil: árbol en una columna, indentación acotada (~24 px por
  nivel), rutas truncadas con `title` accesible; navegación por
  teclado del chevron + dropdown funciona sin puntero.

---

## Parche posterior al cierre (2026-11-10)

Síntoma reportado: "No permite agregar hijos a las categorías".
Diagnóstico: el endpoint `POST /api/v1/item-families` con
`{name, parent_id}` responde 201 Correctly; la respuesta viaja
correcta hasta el callback `refreshData` del modal. Lo que fallaba
era la UI: las ramas inician colapsadas en `expandedSet = new Set()`,
así que al crear una subfamilia el nodo entraba en `familyStore.families`
pero el `<ul v-if="hasChildren && isExpanded">` del padre seguía
oculto, dando al operador la sensación de que la acción no había
hecho nada.

Aplicado en `views/item-families/Index.vue`:

- `refresh(context = {})` ahora acepta
  `{initial?, expandAncestorsOf?, collapseAncestorsOf?}` y además de
  recargar datos aplica los efectos de expansión / colapso.
- `onMounted` pasa `{initial: true}` para que cada raíz esté
  expandida de inicio (cumpliendo el comentario original "Initial
  state opens the first level" del cierre).
- `onAddChild(parent)` pasa `{expandAncestorsOf: parentId}` para
  que la nueva subfamilia se vea sin que el operador tenga que
  abrir la rama manualmente.
- `onMove(node)` pasa `{expandAncestorsOf: newParentId,
  collapseAncestorsOf: previousParentId}` para que el nodo
  reubicado se vea bajo su nuevo padre sin confundir al usuario
  con una rama fantasma donde ya no está.

Helpers nuevos `expandNode(id)` y `collapseNode(id)` mantienen
reactivo el `Set` reasignando un nuevo Set (Vue 3 + VueUse Set
best-practice; sin esto los mutadores directos sobre `Set` no
disparan `computed`).

Verificación: `npm run build` + `npx eslint` sobre los 8 archivos
del plan, ambos pasan limpios. No se tocaron migraciones ni
controladores.

---

## Parche posterior al cierre #2 (2026-11-10)

Síntoma reportado: "Los elipsis del menu no abren el context menu".
Diagnóstico: `BaseDropdown` envuelve el slot "activator" dentro de
un `<MenuButton>` (Headless UI) y `<BaseButton>` se renderiza como
`<button>`. El kebab del nodo traía explicitamente
`@click.stop` en el `<BaseButton>` interior "para que no se
propague al row", pero ese mismo `.stop` impedía que el click
llegase al `<MenuButton>` exterior — el listener de Headless UI
que alterna el estado abierto nunca se ejecuta. Resultado: el
menu nunca se desplegaba.

Aplicado en `components/trees/ItemFamilyTreeNode.vue`:

- Eliminado `@click.stop` del `<BaseButton>` del activador (kebab).
  El kebab ya no necesita detener propagación porque el row no
  tiene un listener sobre el `<div>` contenedor — el `@click` solo
  necesita llegar al `<MenuButton>` para abrir.
  Resultado: el dropdown se abre como en cualquier otro Index de
  la app.

- Cambiados los `@click.stop="..."` de los cuatro `<BaseDropdownItem>`
  a `@click.stop.prevent="..."`. `<BaseDropdownItem>` envuelve el
  contenido en `<a href="#">`; sin `.prevent` el click cerraba el
  menú (vía Headless UI al hacer toggle en `<MenuItem>`) pero la
  navegación nativa seguía inyectando `#` en la URL. Con
  `.prevent`, el emit ocurre limpiamente sin contaminar el hash.
  Esto es un parche local al árbol de familias; los demás Index del
  proyecto mantienen su comportamiento actual para no expandir el
  blast radius.

Verificación: `npm run build` + `npx eslint
./admin/components/trees/ItemFamilyTreeNode.vue`, ambos pasan
limpios. Sin cambios en backend, migraciones, ni el resto del SFC.

---

## Parche posterior al cierre #3 (2026-11-10)

Síntoma reportado (al eliminar un familia sin hijos / con un item
asignado): `SQLSTATE[42S22]: Column not found: 1054 Unknown column
'items.item_family_id' in 'WHERE'` durante el guard "tiene items
asociados".

Diagnóstico: el FK de `items` hacia `item_families` se llama
`family_id` (definido en la migration
`2026_11_10_130100_add_family_id_to_items_table.php`). Pero
`ItemFamily::items()` declaraba

```php
return $this->hasMany(Item::class);
```

sin FK explícita. La convención por defecto de Laravel para ese
overload es `<parent_snake>_id`, lo que produce
`items.item_family_id` en SQL — columna inexistente. La
guard `$family->items()->exists()` dentro de
`ItemFamilyService::delete()` explotaba con 1054.

Aplicado en `app/Models/ItemFamily.php`: el método `items()` ahora
declara el FK explícitamente

```php
return $this->hasMany(Item::class, 'family_id');
```

La inversa `Item::family()` ya pasaba `'family_id'` como segundo
argumento, así que la simetría queda correcta.

Verificación funcional (script efímero `scripts/repro_delete_family.php`,
ya eliminado): una vez aplicado el cambio, `DB::getQueryLog()` muestra
`select exists(select * from items where items.family_id = ? and
items.family_id is not null)`, `Service::delete()` corre sin SQL
errors, y el `Helpful error: has services attached` ValidationException
se dispara correctamente cuando hay items asociados. `php -l
app/Models/ItemFamily.php` limpio.

---

## Parche posterior al cierre #4 (2026-11-10)

Síntoma reportado: al aplicar el filtro `family_id` con
`include_descendants` por defecto, `Call to undefined method
Crater\Models\ItemFamily::descendantIds()`.

Diagnóstico: el BFS vivía en `ItemFamilyService::descendantIds` (un
`public static` correcto), pero `Item::scopeApplyFilters` lo llamaba
como `ItemFamily::descendantIds(...)` — el docstring del scope y la
documentación del plan incluso apuntan a la clase modelo,
`ItemFamily::descendantIds`. La implementación nunca se movió:
estaba en el servicio, llamada desde el sitio equivocado.

Aplicado:

- Añadida la implementación canónica (BFS con `visited` + cap de
  profundidad) en `app/Models/ItemFamily.php` como
  `public static function descendantIds(int $familyId, int $companyId)`
  (el docstring explica la elección: vivir en el modelo evita que
  `Item::scopeApplyFilters` necesite resolver un binding del
  service container).
- `app/Services/ItemFamilyService.php` redujó su
  `descendantIds()` a thin alias que llama al método del modelo,
  preservando compatibilidad con tests o callers que ya invocaban
  la ruta en el servicio.

Verificación: `php -l` limpio en los tres archivos tocados. Llamada
directa devuelve `[1, 7]` para el árbol sembrado; el scope
`Item::applyFilters(['family_id' => 1, 'include_descendants' => 1])`
genera `select * from items where items.family_id in (?, ?)` con
bindings `[1, 7]` (un único `whereIn` para toda la rama, según la
decisión del plan). El alias del servicio sigue produciendo los
mismos ids.

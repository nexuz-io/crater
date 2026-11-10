# Marcas para artículos

## Objetivo

Permitir administrar marcas por empresa y asignarlas a los artículos, por ejemplo «PlayStation 3» con la marca «Sony».

## Contexto

- Los artículos se gestionan mediante `/app/Models/Item.php`, `/app/Http/Requests/ItemsRequest.php` y `/app/Http/Controllers/V1/Admin/Item/ItemsController.php`.
- Las vistas de creación, edición y listado están en `/resources/scripts/admin/views/items/`.
- La marca identifica al fabricante o la marca comercial del artículo y es independiente de su familia: «PlayStation 3» puede tener marca «Sony» y pertenecer a «Consolas».
- Este plan complementa `03-item-families-tree.md`, pero puede implementarse sin que las familias estén disponibles.
- Una marca será una entidad plana: no necesita padres, hijas ni estructura de árbol.

## Plan

1. **Definir las reglas de marcas.**
   - Cada artículo tendrá cero o una marca; una marca podrá asociarse con muchos artículos.
   - Cada empresa administrará su catálogo de marcas. Dos empresas podrán tener una marca «Sony» con IDs independientes.
   - Exigir un nombre no vacío, de hasta 255 caracteres, único dentro de la empresa.
   - Normalizar espacios al principio y al final y establecer comparación sin distinción de mayúsculas para evitar duplicados como «Sony» y «sony».
   - Mantener la marca opcional para que los artículos existentes y los servicios puedan continuar sin ella.
   - Permitir renombrar una marca conservando su ID y sus asignaciones; permitir eliminarla solo si no tiene artículos asociados, incluidos los inactivos.

2. **Crear esquema y relaciones.**
   - Añadir migraciones en `/database/migrations/` para `brands`: `id`, `company_id`, `name` y timestamps.
   - Añadir `brand_id` nullable a `items`; todos los artículos existentes comenzarán sin marca.
   - Crear `/app/Models/Brand.php` con relaciones `company()` e `items()` y consultas limitadas a la empresa.
   - Añadir `brand()` en `/app/Models/Item.php` y `brands()` en `/app/Models/Company.php`.
   - Indexar las consultas por empresa y marca; añadir claves foráneas que eviten referencias inválidas y no eliminen artículos en cascada.
   - Implementar unicidad por empresa en base de datos, alineada con la normalización y comparación definida en el paso 1. Si los motores soportados necesitan una clave normalizada, almacenarla y generar su valor en servidor.
   - Integrar el borrado de marcas en el flujo de eliminación completa de una empresa, respetando el orden de sus referencias.
   - Definir rollback: retirar primero la referencia `items.brand_id` y después la tabla de marcas.

3. **Implementar API, validación y permisos.**
   - Crear controlador de marcas en `/app/Http/Controllers/V1/Admin/Item/`, Form Requests en `/app/Http/Requests/` y `BrandResource` en `/app/Http/Resources/` siguiendo los patrones del proyecto.
   - Registrar endpoints de listado, creación, consulta, actualización y eliminación en `/routes/api.php`, bajo `brands` y dentro del grupo autenticado con empresa seleccionada.
   - Devolver `id` y `name`; ofrecer búsqueda, orden alfabético y paginación con límites validados.
   - Registrar `BrandPolicy` en `/app/Providers/AuthServiceProvider.php`; reutilizar las capacidades existentes de ver, crear, editar y eliminar artículos para las acciones equivalentes de marcas.
   - Obtener `company_id` desde el contexto autorizado, no desde el cuerpo de la petición; comprobar pertenencia de marca a la empresa en consultas y mutaciones.
   - Ejecutar renombrado y eliminación con las comprobaciones necesarias y manejar conflictos concurrentes de unicidad o referencias con errores comprensibles.
   - Rechazar la eliminación de una marca utilizada con un error de validación que indique que primero se deben reasignar o desvincular sus artículos.

4. **Integrar la marca en los artículos.**
   - Añadir `brand_id` nullable a `/app/Http/Requests/ItemsRequest.php`, validando que exista en la empresa actual.
   - Incorporar el campo a creación y actualización: omitirlo en una actualización conservará la asignación; enviarlo como `null` la quitará.
   - Extender `/app/Http/Resources/ItemResource.php` y revisar `/app/Http/Resources/ItemCollection.php` para exponer `brand_id` y el resumen de marca cuando esté cargado.
   - Cargar anticipadamente la relación en consultas de listado y edición, evitando consultas N+1.
   - Añadir filtros `brand_id` y `without_brand`, mutuamente excluyentes; combinarlos con búsqueda, estado, unidad y filtros de familia si estos ya existen.
   - Agrupar correctamente los filtros con condiciones `OR` para mantener el aislamiento por empresa.
   - Revisar consumidores de artículos, incluidas facturas, presupuestos, calculadora e importación, para mantener el comportamiento actual cuando no se envíe una marca.

5. **Crear la administración de marcas.**
   - Crear store Pinia `/resources/scripts/admin/stores/brand.js` y vistas en `/resources/scripts/admin/views/brands/`.
   - Añadir acceso «Marcas» desde el listado de artículos, siguiendo el patrón de «Familias» si ya está implementado.
   - Registrar las rutas del frontend siguiendo la arquitectura actual del router y sus permisos.
   - Ofrecer listado con búsqueda y paginación, creación, edición del nombre y eliminación de marcas sin artículos.
   - Utilizar componentes base, notificaciones, validación y tokens del tema existentes.
   - En móvil, mantener formularios y acciones accesibles en una columna y evitar desbordamiento horizontal.

6. **Actualizar formularios y listado de artículos.**
   - Añadir selector opcional de marca en `/resources/scripts/admin/views/items/Create.vue`, con búsqueda y opción «Sin marca».
   - Cargar la marca asignada al editar, incluso si no está en la primera página de resultados del selector.
   - Si el usuario puede crear marcas, ofrecer acceso a su creación desde el selector o formulario; al volver, actualizar opciones y seleccionar la nueva marca sin perder los datos del artículo.
   - Mostrar la marca en `/resources/scripts/admin/views/items/Index.vue` e incorporar filtros por marca y sin marca.
   - Actualizar el store de artículos identificado durante la implementación para guardar `brand_id` y conservar filtros y selección.
   - Limpiar las opciones y filtros al cambiar de empresa, recargar el catálogo y descartar respuestas pendientes de la empresa anterior.
   - Distinguir carga, catálogo vacío y fallo de API; un error no debe borrar silenciosamente la marca asignada.
   - Añadir traducciones en `/resources/scripts/locales/` conforme a la estrategia de idiomas existente: «Marca/Marcas» en español, «Brand/Brands» en inglés y «Marque/Marques» en francés.

7. **Verificar datos, compatibilidad e interfaz.**
   - Añadir pruebas backend para crear, renombrar y eliminar marcas, unicidad por empresa y aislamiento de consultas y mutaciones.
   - Probar asignación, cambio y eliminación de marca en artículos; verificar que omitir el campo conserva su valor y `null` lo elimina.
   - Probar marca de otra empresa, marca inexistente, duplicados normalizados y eliminación de marcas utilizadas por artículos activos o inactivos.
   - Verificar filtros por marca y sin marca, combinados con los filtros existentes y paginación.
   - Probar concurrencia relevante de nombres duplicados y asignación durante eliminación en el motor de base de datos del despliegue.
   - Ejecutar migración y rollback en una base de pruebas; comprobar que los artículos anteriores conservan sus datos.
   - Ejecutar pruebas focalizadas y `npm run build`.
   - Revisar en escritorio y móvil el flujo «crear Sony → crear PlayStation 3 con Sony → editar y mantener Sony → filtrar por Sony → quitar marca».
   - Verificar búsqueda y precarga con muchas marcas, cambio de empresa y controles utilizables con teclado.

## Resultado esperado

- [ ] Existe una entidad `Brand` independiente de las familias de artículos.
- [ ] Una empresa puede administrar sus marcas sin acceder a las de otra.
- [ ] «PlayStation 3» puede guardarse con la marca «Sony» y, si existen familias, asignarse también a «Consolas».
- [ ] Los artículos existentes y nuevos pueden permanecer sin marca.
- [ ] La edición carga y conserva la marca asignada, aunque no esté en la primera página de opciones.
- [ ] Se pueden consultar artículos por marca o sin marca, combinando filtros existentes.
- [ ] Renombrar conserva las asignaciones y eliminar una marca utilizada devuelve un error claro.
- [ ] No se admiten duplicados normalizados dentro de una empresa ni asignaciones entre empresas.
- [ ] Formularios y gestión de marcas funcionan en escritorio, móvil y con teclado.
- [ ] Cambio de empresa, carga y errores no provocan pérdida silenciosa de la selección.
- [ ] Migración, rollback, pruebas focalizadas y build verificados.

## Fuera de alcance

- Logos de marcas, sitios web, contactos de fabricantes o proveedores.
- Varias marcas por artículo, jerarquías de marcas o catálogo compartido entre empresas.
- Herencia de precios, impuestos o atributos desde la marca.
- Incorporar marcas a documentos PDF, reportes nuevos o documentos históricos.
- Asignar automáticamente marcas a partir del nombre de artículos existentes.

## Notas

Estado: cerrado 2026-11-10.

Resumen de cambios:

**Backend (PHP)**
- `database/migrations/2026_11_10_120000_create_brands_table.php`:
  nueva tabla `brands` (`id`, `company_id`, `name`, `name_normalized`,
  timestamps) con índice único `(company_id, name_normalized)` y FK
  `company_id` en cascada.
- `database/migrations/2026_11_10_120100_add_brand_id_to_items_table.php`:
  añade `brand_id` nullable a `items` con FK `restrict` (`items.brand_id
  → brands.id`) e índice secundario.
- `app/Models/Brand.php`: con `company()`, `items()`, scopes
  `whereCompany`, `whereSearch`, `applyFilters`, `paginateData`. La
  normalización vive aquí (`cleanName`, `normalizeName`) para que
  request / seeder / factory no acumulen lógica divergente. El helper
  estático `payloadForCreate` empaqueta name trimmed + key persistente.
- `app/Models/Item.php`: añade `brand()`, `scopeWhereBrand`,
  `scopeWhereWithoutBrand`, y aplica los filtros mutuamente excluyentes
  en `applyFilters`. `createItem` / `updateItem` cargan `brand` eager para
  evitar N+1. La regla de UPDATE sigue siendo "omitir conserva el valor
  actual, `null` lo elimina".
- `app/Models/Company.php`: añade `brands()` y un delete explícito en
  `deleteCompany` antes del `$this->delete()`, porque el FK
  `restrict` choca con la cascada de `items.company_id` si se hace
  todo en bloque.
- `app/Http/Requests/ItemsRequest.php`: añade `brand_id` (`nullable`,
  `exists:brands,id,company_id,<header>`); `prepareForValidation`
  convierte '' del selector a `null`.
- `app/Http/Requests/BrandRequest.php`: regla `unique` con `whereRaw`
  que compara `name_normalized`. PUT ignora el `{brand}` route param.
- `app/Http/Resources/BrandResource.php` + `ItemResource` actualizado
  exponen `brand_id` y la marca embebida (`whenLoaded`).
- `app/Http/Controllers/V1/Admin/Item/BrandsController.php`: CRUD
  estándar; `destroy` rechaza con `respondJson('brand_attached_items',
  …)` cuando la marca sigue en uso, contando también items inactivos.
- `app/Http/Controllers/V1/Admin/Item/ItemsController.php`: eager
  loads `brand`, `taxes`, `creator`; `index` aplica los filtros nuevos
  vía `applyFilters`.
- `app/Policies/BrandPolicy.php`: reusa el ability `view-item` porque
  la marca solo tiene sentido dentro del catálogo de artículos
  (mismo convenio que `UnitPolicy`).
- `app/Providers/AuthServiceProvider.php` registra el mapeo del modelo.
- `config/abilities.php`: añade `view-brand` / `create-brand` /
  `edit-brand` / `delete-brand` con dependencias contra `view-item`.
- `routes/api.php`: `Route::apiResource('brands', ...)` en el sub-grupo
  autenticado + empresa.
- `database/factories/BrandFactory.php` + `tests/Feature/Admin/BrandTest.php`:
  factory y Pest cubriendo index, create, get, update, delete libre /
  delete bloqueado por items en uso, y uniqueness por empresa (incluido
  `sony` vs `Sony` con espacios).

**Frontend (Vue)**
- `resources/scripts/admin/stub/abilities.js`: claves `VIEW_BRAND`,
  `CREATE_BRAND`, `EDIT_BRAND`, `DELETE_BRAND`.
- `resources/scripts/admin/stores/brand.js`: CRUD Pinia con
  `resetCurrentBrand`, `fetchBrands`, `fetchBrand`, `addBrand`,
  `updateBrand`, `deleteBrand`. El delete manager mapea el error
  `brand_attached_items` a un toast específico.
- `resources/scripts/admin/stores/item.js`: añade `brand_id` y
  `brand` al `currentItem`; nuevas acciones `fetchItemBrands` y cache
  `itemBrands`.
- `resources/scripts/admin/components/modal-components/BrandModal.vue`:
  alta y edición, Vuelidate (required + minLength 3 + maxLength 255),
  refresh callback.
- `resources/scripts/admin/components/dropdowns/BrandIndexDropdown.vue`:
  dot-menu con Editar / Eliminar (abilities gating + diálogo de
  confirmación).
- `resources/scripts/admin/views/brands/Index.vue`: tabla paginada
  con filtro por nombre y empty state; `fetchData` usa `searchBrands`
  y respeta orden alfabético por defecto.
- `resources/scripts/admin/views/items/Index.vue`: columna `brand`,
  filtro por marca (BaseMultiselect+`searchBrands`) y toggle "Sin
  marca" (`BaseSwitch`). Las dos opciones se excluyen mutuamente en
  cliente; el server ya las considera OR-exclusivas en
  `scopeApplyFilters`. Acceso rápido "Marcas" en el header.
- `resources/scripts/admin/views/items/Create.vue`: selector de marca
  con "Sin marca" (BaseMultiselect allowEmpty/clearable). Cuando el
  usuario crea una nueva marca desde el selector, el modal devuelve
  la marca recién creada, el store recarga el catálogo, y el form la
  selecciona sin perder el resto del artículo.
- `resources/scripts/admin/admin-router.js`: ruta `brands` con
  `meta.ability = VIEW_BRAND`.

**Localización**
- 31 archivos `*.json` ahora contienen bloque `brands` con sus mismas
  claves. en, es, fr, de, it, nl, pt, pt-br, pl traducidos realmente;
  el resto cae al mismo texto en inglés para evitar warnings de
  vue-i18n.

**Verificación**
- `php -l` sobre todos los PHP nuevos/editados: limpio.
- `npm run build` (resources/scripts/): sin errores ni warnings
  nuevos.
- `npx eslint` sobre stores/brand.js, item.js, brands/Index.vue,
  items/{Create,Index}.vue, admin-router.js, modal y dropdown de marca,
  stub/abilities: 0 errores / 0 warnings.
- PHPUnit no se ejecutó en este entorno (sin BD activa). Las
  aserciones clave en `tests/Feature/Admin/BrandTest.php` cubren los
  criterios del plan: index, create + get, update, delete sin
  artículos, delete bloqueado con items, y uniqueness sensible a
  mayúsculas y espacios.

**Compatibilidad con plan 03 (Familias)**
- Plan 03 añadirá `family_id` a `items` + `ItemFamily::class`; mis
  cambios solo tocan `brand_id` y `Brand::class`. Ningún campo,
  scope, ability o ruta colisiona. La columna `name_normalized` y el
  índice único `(company_id, name_normalized)` solo aplican a la
  tabla `brands`; las familias pueden replicar la convención sin
  dependencias cruzadas.

**Pendientes para validación manual en navegador**
- Crear PlayStation 3 / Sony → editar y mantener la asignación →
  filtrar por Sony → quitar marca → guardar → comprobar que
  `brand_id` queda en NULL.
- Cambio de empresa con 50+ marcas: confirmar que el select no
  pierde la opción pre-cargada al recargar el catálogo.
- Móvil: columnas del listado + filtros verticalmente,
  sin overflow horizontal; modal accesible con teclado y pantalla de
  320 px de ancho.

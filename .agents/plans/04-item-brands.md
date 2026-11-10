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

Estado: pendiente de implementación.

La implementación es independiente del plan de familias; si ambos se desarrollan simultáneamente, coordinar las modificaciones de `Item`, sus requests, recursos, stores y vistas.

Al completar los criterios, mover el plan a `/.agents/plans/archive/` según `.agents/README.md`.

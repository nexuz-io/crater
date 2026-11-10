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

Estado: pendiente de implementación.

Las decisiones iniciales son familia opcional y única por artículo, asignación en cualquier nivel y eliminación restringida de familias no vacías.

Al completar los criterios, mover el plan a `/.agents/plans/archive/` según `.agents/README.md`.

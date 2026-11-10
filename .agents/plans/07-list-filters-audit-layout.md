# Auditoría y organización adaptable de filtros de listados

## Objetivo

Verificar el funcionamiento de los filtros de listados y mejorar su organización adaptable, evaluando y reutilizando componentes existentes para que tanto los formularios pequeños como los extensos sean claros y utilizables.

## Contexto

- Existe `/resources/scripts/components/base/BaseFilterWrapper.vue`, usado por artículos, marcas, usuarios, clientes, facturas, presupuestos, pagos, gastos y facturas recurrentes.
- El componente organiza sus hijos en columna y cambia a una fila sin salto de línea desde `lg` o `xl`, mediante `rowOnXl`. Esto puede comprimir controles cuando se acumulan filtros.
- «Limpiar todos» se representa actualmente con un `label` posicionado de forma absoluta; revisar semántica, acceso por teclado y posibles solapamientos.
- Hay variantes en sus consumidores: por ejemplo, facturas recurrentes utiliza `v-show` en lugar de pasar la propiedad `show`. La auditoría deberá verificar su efecto real.
- Las familias y marcas aumentan los filtros de artículos; Delivery Notes añadirá otro listado. La solución debe acomodar estas necesidades sin exigir un componente específico por entidad.

## Plan

1. **Inventariar listados y componentes de filtro.**
   - Localizar todos los consumidores de `BaseFilterWrapper`, incluyendo vistas administrativas, portal y módulos si existen, y los listados que construyen su propio panel.
   - Revisar los componentes base de campos, grids, selectores, fechas, tablas y botones antes de proponer abstracciones nuevas.
   - Registrar por listado: filtros, tipos, valores iniciales, número de campos, condiciones de visibilidad, componente usado y comportamiento en escritorio y móvil.
   - Identificar filtros dependientes, como familia y subfamilias, opciones por empresa, selecciones remotas y rangos de fechas.
   - Crear una matriz de cobertura con rutas reales, problemas reproducibles y prioridad; distinguir defectos funcionales de mejoras visuales.

2. **Verificar el flujo completo de filtrado.**
   - Seguir cada filtro desde el control hasta el store, parámetros HTTP, validación backend y consulta Eloquent.
   - Comprobar que cada campo envía la clave y el tipo esperados; distinguir ausencia, cadena vacía, `null`, `0` y `false`.
   - Probar filtros individualmente y combinados, especialmente estados, rangos, importes, clientes, familias y marcas.
   - Revisar fechas con formato `YYYY-MM-DD`, límites inclusivos y validación de rangos invertidos; evitar conversiones ambiguas o desplazamientos por zona horaria.
   - Verificar que cambiar filtros reinicia la página, conserva la ordenación prevista y actualiza resultados y totales con los mismos criterios.
   - Comprobar que limpiar filtros restaura los valores acordados y realmente actualiza la consulta, sin parámetros antiguos ni selecciones invisibles.
   - Revisar debounce, peticiones duplicadas y respuestas fuera de orden; impedir que una respuesta antigua sustituya resultados de filtros más recientes.
   - Comprobar aislamiento por empresa, agrupación de condiciones `OR` y limpieza de filtros/opciones al cambiar de empresa.
   - Documentar si el listado aplica cambios inmediatamente o mediante «Aplicar», y si persiste filtros en URL/store. Mantener coherencia sin introducir persistencia nueva indiscriminadamente.

3. **Evaluar reutilización y decidir la arquitectura.**
   - Comparar ampliar `BaseFilterWrapper`, componerlo con un grid existente o crear una pieza nueva solo si hay una responsabilidad no cubierta.
   - Priorizar un contenedor de presentación reutilizable con slots para campos, cabecera y acciones; mantener las reglas y consultas de cada entidad en sus vistas/stores.
   - Evaluar un composable para lógica realmente repetida, como limpieza o sincronización de filtros, solo después de identificar contratos equivalentes y casos distintos.
   - No asumir que un constructor universal de formularios aporta valor: comparar complejidad, tipado, validación, traducciones y mantenimiento frente a slots/componentes existentes.
   - Registrar la decisión y su justificación, incluyendo compatibilidad de `show`, `rowOnXl`, eventos y clases de los consumidores actuales.

4. **Definir la distribución adaptable de campos.**
   - Usar una distribución con salto de fila, preferentemente grid de columnas adaptables, con ancho mínimo útil por control y `gap` uniforme.
   - Mantener una sola fila cuando los controles quepan cómodamente; usar varias filas en cuanto el ancho disponible o la cantidad de campos lo requieran.
   - Basar la distribución en el espacio real del contenedor, incluyendo sidebar expandido/contraído, y no solo en el ancho del viewport.
   - En móvil, usar una columna como base; habilitar columnas adicionales únicamente cuando las etiquetas y controles mantengan legibilidad.
   - Permitir campos de ancho extendido, como búsqueda y rangos de fechas, mediante una API pequeña y explícita de spans o slots.
   - Alinear los controles por su zona de entrada, contemplando etiquetas multilínea y mensajes de validación sin solapamiento.
   - Reservar una fila o zona estable para título y acciones, evitando que «Limpiar todos» invada campos o etiquetas.
   - Revisar menús de selectores y calendarios: no recortarlos con `overflow` del panel ni ocultarlos debajo de la tabla.

5. **Mejorar la organización para muchos filtros.**
   - Ordenar filtros por frecuencia de uso y agrupar campos relacionados: búsqueda, clasificación, estado, cliente y fechas según cada listado.
   - Evaluar filtros secundarios dentro de una sección «Más filtros» cuando reduzca carga visual; no ocultar campos solo por superar un número arbitrario.
   - Si existen filtros activos en una sección plegada, mostrar indicador y permitir localizarlos o limpiarlos; cerrar el panel no debe cancelar silenciosamente el filtrado.
   - Evaluar contador o resumen de filtros activos, con eliminación individual cuando aporte claridad; contar rangos y selecciones múltiples según una convención común.
   - Distinguir «Sin resultados» de errores de carga y ofrecer limpieza o ajuste de filtros desde el estado vacío.
   - Documentar el patrón elegido para listados con pocos, medianos y muchos campos antes de extenderlo al resto.

6. **Implementar y migrar mediante casos piloto.**
   - Adaptar `/resources/scripts/components/base/BaseFilterWrapper.vue` o la composición elegida después del paso 3.
   - Reemplazar acciones no semánticas por botones `type="button"`, con foco visible y nombres accesibles.
   - Verificar apertura/cierre, `aria-expanded` en el disparador, asociación con el panel y ausencia de foco atrapado en secciones ocultas.
   - Aplicar primero a `/resources/scripts/admin/views/items/Index.vue` como caso de muchos filtros y a `/resources/scripts/admin/views/brands/Index.vue` como caso sencillo.
   - Revisar en esos pilotos campos de ancho fijo, `w-full`, márgenes y layouts internos que puedan impedir el nuevo comportamiento.
   - Tras validar pilotos, migrar los consumidores inventariados y corregir inconsistencias funcionales detectadas, incluida la forma de controlar `show`.
   - Reutilizar tokens del tema y traducciones de `/resources/scripts/locales/`; añadir textos solo donde los nuevos controles lo requieran.
   - Documentar el uso del patrón para nuevos listados, incluido Delivery Notes, con ejemplos breves de campos simples, rangos y grupos secundarios.

7. **Validar comportamiento y presentación.**
   - Añadir pruebas focalizadas para defectos funcionales encontrados: combinación de filtros, valores `0`/`false`, limpieza, página reiniciada, rangos y aislamiento por empresa.
   - Probar respuestas fuera de orden en búsquedas y cambios rápidos de selección, sin añadir pruebas que solo repliquen estilos internos.
   - Revisar 320, 375, 768, 1024 y 1440 px, ambos estados del sidebar y zoom al 200 %.
   - Verificar uno, pocos y muchos filtros; nombres largos, etiquetas traducidas, rangos, selectores remotos y mensajes de error.
   - Comprobar navegación por teclado, foco visible, apertura de desplegables, limpieza y recuperación desde «Sin resultados».
   - Ejecutar las pruebas relevantes, lint sobre los archivos modificados con la configuración disponible y `npm run build`.
   - Completar la matriz de cobertura con resultados, incidencias resueltas y pendientes explícitos.

## Resultado esperado

- [ ] Existe un inventario de listados, filtros y componentes reutilizables con verificación funcional.
- [ ] Está documentada la decisión de ampliar componentes existentes o crear nuevas piezas, con responsabilidades claras.
- [ ] Pocos filtros se muestran de forma compacta y muchos filtros se distribuyen en varias filas sin comprimirse ni desbordarse.
- [ ] La distribución funciona con sidebar expandido/contraído, móvil, zoom y traducciones largas.
- [ ] Cabecera, acciones, etiquetas y mensajes de validación no se solapan.
- [ ] Selectores y calendarios permanecen visibles y utilizables.
- [ ] Apertura, limpieza y filtros secundarios tienen controles accesibles y comportamiento coherente.
- [ ] Los filtros activos siguen siendo identificables aunque el panel o una sección estén plegados.
- [ ] Las combinaciones, fechas, valores vacíos, paginación y cambio de empresa funcionan correctamente.
- [ ] Las respuestas antiguas no sustituyen resultados de búsquedas recientes.
- [ ] Los listados revisados usan el patrón acordado y existe una guía para futuros consumidores.
- [ ] Pruebas focalizadas, revisión visual y build completados.

## Fuera de alcance

- Rediseñar tablas completas, navegación o formularios de creación/edición ajenos a filtros.
- Añadir filtros de negocio nuevos que no sean necesarios para corregir fallos encontrados.
- Crear un generador universal de formularios sin justificarlo con el análisis.
- Incorporar búsquedas guardadas o preferencias persistentes nuevas como parte de esta revisión.

## Notas

Estado: pendiente de análisis e implementación.

Hipótesis inicial: ampliar el contenedor existente con una distribución adaptable y slots de acciones resolverá buena parte del problema. Se confirmará después de revisar los consumidores y los componentes base disponibles.

Al completar los criterios, mover el plan a `/.agents/plans/archive/` según `.agents/README.md`.

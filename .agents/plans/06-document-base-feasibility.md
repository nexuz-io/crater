# Análisis de viabilidad de una base común para documentos

## Objetivo

Determinar si conviene introducir una base común para facturas, presupuestos y albaranes que reúna sus datos y comportamientos compartidos y permita implementar las particularidades de cada documento.

## Contexto

- El proyecto dispone de `Invoice`, `Estimate`, sus líneas, requests, recursos, policies, controladores y generación de PDF.
- `05-delivery-notes.md` propone incorporar `DeliveryNote` y convertirlo posteriormente en factura.
- La arquitectura candidata es `DocumentoBase → Factura / Presupuesto / DeliveryNote`; el análisis debe distinguir herencia de clases, contratos de comportamiento y organización de tablas.
- Compartir una clase no obliga a compartir tabla. También es posible compartir servicios o implementar una interfaz manteniendo modelos independientes.
- Este plan produce un diagnóstico y una recomendación. La migración de documentos reales requerirá un plan posterior basado en sus conclusiones.

## Plan

1. **Inventariar los documentos y sus dependencias.**
   - Revisar `/app/Models/Invoice.php`, `/app/Models/Estimate.php`, `/app/Models/RecurringInvoice.php` y sus modelos de líneas.
   - Usar `05-delivery-notes.md` como especificación de `DeliveryNote`; si ya está implementado al ejecutar el análisis, revisar también su código real.
   - Identificar requests, recursos, policies, controladores, stores, formularios, trabajos, correos, PDFs y pruebas asociados a cada documento.
   - Examinar `/app/Traits/GeneratesPdfTrait.php` y `/app/Http/Controllers/V1/Admin/Estimate/ConvertEstimateController.php` como puntos de comportamiento compartido y conversión.
   - Localizar las referencias externas a modelos, IDs y nombres de tablas: pagos, impuestos, medios, campos personalizados, reportes, portal de clientes, calculadora, módulos y asistente IA.
   - Registrar las rutas reales y ejemplos concretos de duplicación o divergencia; no basar la propuesta únicamente en nombres de campos parecidos.

2. **Construir una matriz de datos e invariantes.**
   - Comparar empresa, cliente, creador, número, fecha, moneda, dirección, notas, líneas, importes, impuestos, descuentos, estado y metadatos.
   - Clasificar cada campo y comportamiento como común obligatorio, común opcional o específico, con su significado y reglas por documento.
   - Identificar diferencias esenciales: vencimiento, pagos y saldo en factura; aceptación y expiración en presupuesto; entrega y cantidades en albarán.
   - Separar estado de envío, estado comercial, estado de pago y estado de entrega; comprobar que una base común no fuerce un único campo ambiguo.
   - Revisar instantáneas históricas de cliente, dirección y artículos, precisión monetaria, cantidades, tipos de cambio y reglas de edición después de emitir.
   - Analizar líneas e impuestos por separado: una base de documento no implica que todas sus líneas deban compartir tabla o reglas.

3. **Evaluar alternativas de arquitectura.**
   - **A. Modelos actuales con composición:** extraer servicios, objetos de datos y contratos compartidos, conservando modelos y tablas independientes.
   - **B. Clase abstracta `BaseDocument`:** modelos concretos `Invoice`, `Estimate` y `DeliveryNote` heredan de una base Eloquent común, pero mantienen sus tablas, relaciones y especializaciones.
   - **C. Cabecera común con detalles por tipo:** tabla `documents` y tablas específicas relacionadas uno a uno; evaluar cómo se representan los modelos en Laravel, ya que Eloquent no aporta automáticamente herencia de tablas.
   - **D. Tabla única con discriminador:** evaluar sus beneficios y costes en campos nulos, reglas condicionales, restricciones e aislamiento de tipos.
   - Considerar combinaciones razonables, como interfaz de documento más servicios compartidos y base abstracta mínima.
   - Comparar cada alternativa contra conservar la arquitectura actual, que será una conclusión válida si la abstracción no aporta suficiente beneficio.

4. **Definir criterios de viabilidad y seleccionar una candidata.**
   - Puntuar reducción real de duplicación, claridad de reglas, facilidad para añadir documentos, compatibilidad Eloquent y coste de mantenimiento.
   - Evaluar impacto en esquema, migraciones, contratos API, plugins, frontend, pruebas, reportes y rendimiento.
   - Distinguir beneficios comprobados de hipótesis; anotar qué evidencia falta para decidir.
   - Penalizar bases con numerosos condicionales por tipo, métodos vacíos o campos cuyo significado cambia entre documentos.
   - Tratar integridad histórica, aislamiento entre empresas y autorización como requisitos obligatorios, no como ventajas opcionales.
   - Estimar esfuerzo relativo y riesgo de cada alternativa; no dar tiempos exactos sin haber revisado los puntos afectados.

5. **Diseñar el contrato común y las extensiones de la candidata.**
   - Proponer nombres y responsabilidades para una interfaz `DocumentContract`, una posible clase `BaseDocument` y servicios compartidos, solo donde el inventario justifique su existencia.
   - Definir capacidades explícitas: documento con importes, imprimible, enviable, convertible, cobrable o con entrega, evitando exigir pago o impuestos a todos los tipos.
   - Proponer contratos para acceso a empresa/cliente, líneas, numeración, instantáneas, cálculo y generación de PDF; conservar DTOs o adaptadores cuando los esquemas difieran.
   - Mantener validación, permisos y transiciones específicas cerca de cada documento. Explicar cómo se invocan las extensiones sin depender de grandes bloques `switch`.
   - Evaluar formularios compartidos mediante componentes y esquemas claros, manteniendo stores y acciones específicas cuando sus flujos sean distintos.
   - Analizar `RecurringInvoice` como plantilla de generación antes de incluirla en la jerarquía: no asumir que es un documento emitido equivalente a una factura.

6. **Analizar identidad, persistencia y conversiones.**
   - Determinar cómo se conservarían IDs, numeración por empresa y tipo, hashes, URLs, claves foráneas y relaciones polimórficas en cada opción viable.
   - Revisar nombres de clase almacenados en base de datos y morph maps para evitar romper permisos, medios o campos personalizados al cambiar la herencia.
   - Definir conversión como creación de un documento destino vinculado al origen; no convertir una fila cambiando su tipo ni reutilizar su identidad.
   - Mantener contratos específicos para presupuesto → factura y albarán → factura, con autorización, transacción e idempotencia adaptadas a cada flujo.
   - Evaluar si basta una referencia explícita al origen o se necesita una entidad de relación; justificar la decisión con casos existentes, no con escenarios hipotéticos.
   - Comparar consultas de listados, reportes, búsqueda y PDF, incluyendo joins, índices y estrategias para evitar N+1.

7. **Validar las hipótesis con una prueba de concepto acotada.**
   - Solo si el análisis documental deja dudas relevantes, construir una prueba desechable en un entorno de pruebas aislado, sin migrar datos de producción.
   - Utilizar factura y presupuesto reales como referencia y representar las necesidades del albarán con fixtures o su implementación disponible.
   - Verificar guardado y lectura, relaciones Eloquent, serialización, cálculo, validación específica, numeración y selección de plantilla PDF.
   - Probar acceso entre empresas, permisos por tipo y conversión con rollback y reintentos.
   - Medir consultas y tiempos con un volumen representativo frente a la solución actual; registrar tamaño del conjunto y método para que la comparación sea reproducible.
   - Documentar resultados y límites de la prueba. No confundir una demostración funcional con una migración lista para desplegar.

8. **Diseñar una adopción gradual si la propuesta resulta viable.**
   - Priorizar extracción de contratos y servicios manteniendo tablas y endpoints actuales cuando esto reduzca el riesgo.
   - Proponer fases por comportamiento y tipo de documento, con criterios de entrada, salida y compatibilidad temporal.
   - Si se recomienda cambiar tablas, definir mapeo de IDs, backfill, verificación de conteos e importes, corte de escritura, índices y estrategia de reversión.
   - Identificar cambios incompatibles y consumidores que necesitan adaptadores; evitar escrituras dobles sin una estrategia explícita de consistencia.
   - Establecer regresiones obligatorias para PDFs, cálculos, pagos, estados, reportes, APIs, permisos y conversiones.
   - Explicar la relación con `05-delivery-notes.md`: qué puede implementarse ya y qué conviene esperar a la decisión arquitectónica.

9. **Documentar la decisión y el siguiente paso.**
   - Añadir a este plan el inventario resumido, la matriz de campos/comportamientos, alternativas, riesgos y evidencias.
   - Registrar una decisión: viable y recomendable, viable con condiciones, o no recomendable por ahora.
   - Incluir un diagrama sencillo de clases y relaciones de persistencia para la alternativa elegida, señalando los comportamientos específicos.
   - Listar dudas pendientes y condiciones que harían reconsiderar la decisión.
   - Si se recomienda proceder, crear un nuevo plan de implementación numerado en `/.agents/plans/` con alcance y fases concretas; este análisis no autoriza por sí solo la refactorización completa.

## Resultado esperado

- [ ] Existe un inventario verificable de documentos, dependencias y duplicaciones reales.
- [ ] Están diferenciadas la herencia de clases, la composición de comportamientos y las estrategias de persistencia.
- [ ] Se comparan las alternativas con criterios explícitos y frente al estado actual.
- [ ] La propuesta conserva las reglas particulares de factura, presupuesto y albarán.
- [ ] Se han evaluado líneas, impuestos, pagos, estados, PDFs, conversiones y referencias externas.
- [ ] Se documenta cómo preservar datos históricos, identidad, permisos y aislamiento por empresa.
- [ ] Las hipótesis críticas cuentan con evidencia o quedan señaladas como pendientes.
- [ ] Hay una recomendación justificada, un diagrama de arquitectura y una estimación relativa del impacto.
- [ ] Si se recomienda implementar, existe una estrategia gradual y un plan posterior independiente.
- [ ] Se ha definido cómo afecta la decisión a la implementación de Delivery Notes.

## Fuera de alcance

- Migrar o refactorizar todos los documentos durante este análisis.
- Cambiar reglas contables, comerciales o fiscales para hacerlas encajar en una base común.
- Unificar tablas, estados o formularios sin evidencia de que mejora el diseño.
- Incorporar nuevos tipos de documento distintos de los necesarios para evaluar la propuesta.

## Notas

Estado: pendiente de análisis.

Hipótesis inicial a contrastar: una base abstracta pequeña o contratos con servicios compartidos pueden reducir duplicación conservando tablas independientes y especializaciones. No se elige todavía una arquitectura definitiva.

Al completar los criterios, mover el plan a `/.agents/plans/archive/` según `.agents/README.md`.

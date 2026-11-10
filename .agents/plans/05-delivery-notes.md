# Delivery Notes: albaranes convertibles en factura

## Objetivo

Implementar un documento de entrega `DeliveryNote` que permita registrar artículos entregados, generar un albarán PDF y convertirlo posteriormente en una factura vinculada.

## Contexto

- El proyecto dispone de presupuestos y facturas con líneas, numeración, permisos, vistas y generación de PDF.
- `/app/Http/Controllers/V1/Admin/Estimate/ConvertEstimateController.php` ofrece una referencia para la conversión de documentos; revisar su lógica junto a `/app/Models/Invoice.php` antes de reutilizarla.
- Usar «Albarán/Albaranes» en español, «Delivery note/Delivery notes» en inglés y «Bon de livraison/Bons de livraison» en francés.
- Integrar el nuevo documento siguiendo la arquitectura de los documentos existentes: modelo, líneas, requests, recursos, policies, controladores, store y vistas propios.

## Plan

1. **Definir el alcance funcional y las reglas.**
   - Cada albarán pertenece a una empresa y un cliente; contiene fecha, número, dirección de entrega, notas y líneas con descripción, unidad y cantidad positiva.
   - Permitir artículos de catálogo y líneas manuales según los patrones existentes. Guardar una copia de sus datos para conservar el documento aunque cambie el catálogo.
   - Conservar precios, moneda, descuentos e impuestos como datos comerciales para la conversión; presentar el PDF de entrega sin precios ni totales monetarios por defecto.
   - Definir estados `DRAFT`, `DELIVERED` y `CANCELLED`; representar «Facturado» mediante la relación con una factura, separándolo del estado de entrega.
   - Permitir editar y eliminar borradores, marcar un borrador como entregado y cancelar documentos no facturados. Bloquear modificaciones de contenido y eliminación después de la entrega; conservar el documento para trazabilidad.
   - Convertir únicamente albaranes entregados y no cancelados. Si faltan datos comerciales requeridos, permitir completarlos en la preparación de la factura sin alterar el albarán entregado.
   - Primera versión: conversión completa de un albarán en una sola factura. La factura nace como borrador, sin envío ni registro de pago automáticos.

2. **Revisar las piezas reutilizables antes de diseñar las tablas.**
   - Revisar `/app/Models/Estimate.php`, `/app/Models/EstimateItem.php`, `/app/Models/Invoice.php`, `/app/Models/InvoiceItem.php` y sus requests, recursos y policies.
   - Identificar servicios de numeración, cálculo monetario, tipos de cambio, impuestos, PDF y datos de empresa que deban utilizarse también en albaranes.
   - Revisar `/app/Traits/GeneratesPdfTrait.php` y la infraestructura de trabajos de generación de PDF para determinar sus puntos de extensión.
   - Reutilizar componentes de líneas y selectores existentes mediante contratos explícitos; evitar copiar errores o acoplar el nuevo documento a tablas de presupuestos.

3. **Crear persistencia y relaciones.**
   - Añadir migraciones en `/database/migrations/` para `delivery_notes` y `delivery_note_items`.
   - Crear `/app/Models/DeliveryNote.php` y `/app/Models/DeliveryNoteItem.php` con relaciones de empresa, cliente, creador y líneas.
   - Guardar número, fechas, estado, moneda, dirección de entrega y datos comerciales necesarios, con tipos y precisión compatibles con facturas. Guardar cantidades decimales y dinero según las convenciones actuales, sin cálculos nuevos con flotantes de JavaScript.
   - Integrar impuestos de línea/documento siguiendo el esquema real del proyecto y mantener su copia histórica. Identificar las migraciones adicionales que requiera ese esquema.
   - Añadir `delivery_note_id` nullable y único a `invoices` como fuente única de la relación; exponer `DeliveryNote::invoice()` e `Invoice::deliveryNote()`.
   - La restricción única garantizará que un albarán no genere dos facturas, incluso con solicitudes simultáneas.
   - Añadir índices por empresa, cliente, fecha y estado, y unicidad del número dentro de la empresa conforme a la estrategia de numeración existente.
   - Definir claves foráneas y reglas de eliminación: una factura vinculada no podrá eliminarse físicamente desde el flujo ordinario; se conservará su vínculo y se usará el mecanismo de anulación existente cuando esté disponible.
   - Integrar limpieza de documentos y líneas en la eliminación completa de empresa, respetando las relaciones y el orden de borrado.
   - Preparar rollback sin alterar los documentos anteriores a esta funcionalidad.

4. **Implementar operaciones y numeración del documento.**
   - Crear un servicio de albaranes en `/app/Services/` para altas, actualizaciones, transiciones y eliminación de borradores, usando transacciones para documento, líneas e impuestos.
   - Integrar secuencia y prefijo propios de albaranes por empresa; asignar el número en servidor mediante la infraestructura existente y gestionar concurrencia.
   - Validar cliente, artículos, moneda e impuestos dentro de la empresa seleccionada; guardar `company_id` desde el contexto autorizado.
   - Validar cantidades, fechas en `YYYY-MM-DD`, campos comerciales y transiciones. No permitir que el cliente cambie libremente estado, relación de factura o número interno.
   - Mantener instantáneas de nombre, descripción, unidad, precio y dirección. El PDF debe utilizar esos datos, no resolverlos de nuevo desde el catálogo.

5. **Construir una conversión transaccional e idempotente.**
   - Crear `/app/Services/ConvertDeliveryNoteToInvoice.php` y un controlador de conversión dedicado.
   - Autorizar tanto la conversión del albarán como la creación de facturas; comprobar empresa, cliente, estado y datos comerciales requeridos.
   - Dentro de una transacción, bloquear el albarán con `lockForUpdate()` y comprobar nuevamente si ya existe una factura asociada.
   - Ante un reintento, devolver la factura existente si el usuario puede verla; no crear otra ni sobrescribir sus datos. La restricción única será la segunda protección frente a concurrencia.
   - Copiar explícitamente los campos permitidos y las líneas a una factura nueva; no copiar IDs, estados ni claves foráneas del albarán mediante un `toArray()` indiscriminado.
   - Conservar cliente, moneda, cantidades, descripciones y condiciones comerciales. Calcular importes en servidor con el flujo de facturas, usando instantáneas y los ajustes confirmados en la preparación de conversión.
   - Usar fecha de factura elegida, por defecto hoy; obtener vencimiento, numeración y tipo de cambio según las reglas vigentes de factura. Conservar el tipo aplicado en la factura.
   - Establecer `delivery_note_id` al crear la factura y devolver su recurso habitual para navegar a su edición.
   - Si falla cualquier paso, revertir factura, líneas, impuestos y vínculo. Despachar trabajos PDF o efectos externos solo después del commit.
   - Mostrar «Facturado» también si la factura vinculada sigue en borrador, con su estado visible; anularla no liberará automáticamente el albarán para una segunda conversión.

6. **Añadir API, autorización y recursos.**
   - Crear controladores en `/app/Http/Controllers/V1/Admin/DeliveryNote/`, requests en `/app/Http/Requests/` y recursos en `/app/Http/Resources/`.
   - Registrar en `/routes/api.php` listado, creación, detalle, edición, eliminación, transiciones y conversión bajo `delivery-notes`.
   - Ofrecer filtros por cliente, fechas, estado de entrega y presencia de factura; mantener paginación y ordenación con campos permitidos.
   - Crear `DeliveryNotePolicy` y registrarla en `/app/Providers/AuthServiceProvider.php`.
   - Añadir capacidades de ver, crear, editar, eliminar y convertir albaranes siguiendo `/config/abilities.php` y el editor de roles. La conversión requerirá además el permiso de crear facturas.
   - Preparar actualización idempotente de permisos para instalaciones existentes y nuevas, respetando los permisos de roles personalizados.
   - Comprobar pertenencia a empresa en consultas, bindings y relaciones; configurar el ámbito de Bouncer antes de autorizar, incluidas las rutas PDF.
   - Devolver la referencia de factura en el recurso del albarán y la referencia del albarán en el de factura, con relaciones cargadas anticipadamente y sin serialización circular.

7. **Implementar PDF y acceso al documento.**
   - Crear controlador PDF, plantillas en `/resources/views/app/pdf/` e integración con el generador existente.
   - Mostrar empresa emisora, cliente, dirección de entrega, número, fecha, líneas, unidades, cantidades y notas. Identificarlo claramente como albarán.
   - Ofrecer vista previa y descarga desde el detalle, con formato de fecha e idioma de la empresa.
   - Proteger el PDF mediante autenticación, pertenencia a empresa y permiso de lectura; un hash no sustituye la autorización.
   - Contemplar documentos con muchas líneas, varias páginas, descripciones largas y unidades decimales.

8. **Crear interfaz de administración y conversión.**
   - Crear store `/resources/scripts/admin/stores/delivery-note.js` y vistas de listado, creación, edición y detalle en `/resources/scripts/admin/views/delivery-notes/`.
   - Registrar rutas y añadir «Albaranes» al menú mediante el mecanismo actual de navegación y permisos.
   - Reutilizar selectores de cliente, artículos, unidades, direcciones y componentes de líneas, manteniendo los datos comerciales necesarios para facturar.
   - Mostrar estados de entrega y facturación por separado; habilitar acciones según estado y permisos, con la misma validación en backend.
   - Añadir «Convertir en factura» con preparación de fecha, vencimiento y datos comerciales faltantes. Deshabilitar el envío repetido mientras se procesa y abrir la factura retornada.
   - En documentos convertidos, mostrar «Ver factura»; en la factura, mostrar enlace al albarán de origen. Informar si la factura está anulada sin ofrecer conversión automática adicional.
   - Reiniciar datos al cambiar de empresa y descartar respuestas de la anterior.
   - Adaptar líneas y acciones a móvil, utilizar tokens del tema y asegurar uso con teclado, estados de carga y mensajes de error.
   - Añadir traducciones a `/resources/scripts/locales/` siguiendo el sistema actual y los términos definidos.

9. **Validar el flujo completo y los casos de fallo.**
   - Añadir factories y pruebas focalizadas de CRUD, estados, numeración, datos históricos y aislamiento por empresa.
   - Probar cantidades y cálculos con decimales, impuestos, descuentos, monedas y tipos de cambio conforme al comportamiento de facturas.
   - Probar conversión completa, datos faltantes, permisos insuficientes, cliente de otra empresa, estados inválidos y preservación del albarán entregado.
   - Verificar idempotencia y dos conversiones concurrentes en el motor de base de datos de despliegue: debe existir una sola factura asociada.
   - Simular fallo al copiar líneas o impuestos y comprobar rollback total. Verificar que no se despachen trabajos antes del commit.
   - Probar denegación del PDF para usuarios ajenos y acceso correcto con ámbito de empresa.
   - Verificar restricciones de borrado y comportamiento de factura anulada, así como migración y rollback en una base de pruebas.
   - Ejecutar las pruebas focalizadas y `npm run build`; revisar en escritorio y móvil «crear → entregar → descargar → convertir → abrir factura».

## Resultado esperado

- [ ] Existe un documento `DeliveryNote` independiente de presupuestos y facturas, con líneas y numeración propia por empresa.
- [ ] Se pueden crear borradores, editar, marcar entrega y cancelar según las reglas definidas.
- [ ] Los albaranes entregados conservan sus datos históricos y generan un PDF de entrega sin precios por defecto.
- [ ] Un albarán entregado puede convertirse en una factura borrador con datos comerciales correctos.
- [ ] Los reintentos y conversiones concurrentes no generan facturas duplicadas.
- [ ] Un fallo de conversión deja los datos como estaban antes de la operación.
- [ ] Albarán y factura permiten consultar su documento relacionado y distinguir entrega de facturación.
- [ ] Anulación y eliminación de facturas vinculadas mantienen la trazabilidad.
- [ ] API, PDF y acciones respetan permisos y empresa seleccionada.
- [ ] La funcionalidad está disponible en escritorio y móvil con traducciones y estilos coherentes.
- [ ] Instalaciones existentes reciben tablas y capacidades mediante una actualización idempotente.
- [ ] Migraciones, pruebas focalizadas, concurrencia y build verificados.

## Fuera de alcance

- Facturación parcial de cantidades, varias facturas por albarán o consolidación de varios albaranes en una factura.
- Reservas de stock, movimientos de almacén, transporte, devoluciones o gestión de inventario.
- Firma electrónica, comprobantes fiscales de transporte o integraciones logísticas.
- Conversión desde presupuestos a albaranes, automatización de envíos de correo y publicación en el portal de clientes.
- Modificar documentos históricos o enviarlos/cobrarlos automáticamente al convertir.

## Notas

Estado: pendiente de implementación.

Decisiones iniciales: un albarán puede convertirse una sola vez y completo; la factura resultante es un borrador y el vínculo se conserva aunque se anule. Los importes se preparan para facturación, mientras que el PDF de entrega presenta cantidades y descripciones.

Al completar los criterios, mover el plan a `/.agents/plans/archive/` según `.agents/README.md`.

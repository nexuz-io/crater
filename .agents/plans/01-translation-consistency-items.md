# Plan 01 — Consistencia de traducciones del módulo `items`

> Estado: en curso — creado 2026-11-09. Término canónico revisado el mismo día: artículo o producto, no servicio.

## Objetivo

Que el módulo `items` use **un único término** en cada locale
(todas las cadenas `resources/scripts/locales/*.json`) y que
ese mismo término aparezca igual en **menú, breadcrumb, título de
página, botones, labels y placeholders**. Hoy la UI mezcla
"Artículos"/"Servicios" en español y "Services"/"article(s)"/"item(s)"
en el resto de idiomas.

## Contexto

Auditoría rápida del bloque `items` en los tres locales principales:

| Clave              | es            | en        | fr             |
|--------------------|---------------|-----------|----------------|
| `items`            | Artículos     | Services  | Services       |
| `items.title`      | Artículos     | Services  | Services       |
| `items.items_list` | Lista de artículos | Items List | Liste d'articles |
| `items.new_item`   | (nuevo) ítem  | New Item  | Nouvel **article** |
| `items.add_item`   | (nuevo) ítem  | Add Service | Nouveau **service** |
| `items.services`   | Servicios     | Services  | Services       |
| `items.add_service`| Nuevo servicio | Add Service | Nouveau service |
| `items.svc_name`   | Nombre del servicio | Service Name | Nom du service |

Resultado palpable:

- En la pantalla `/admin/items`, `Index.vue` muestra:
  - header: `$t('items.title')` → **Artículos**
  - breadcrumb: `$t('items.services')` → **Servicios**
  - botón: `$t('items.add_service')` → **Nuevo servicio**

Tres términos distintos para el mismo concepto en una sola pantalla.
Es exactamente el bug que describimos.

## Plan

1. **Auditar.** Listar todas las claves bajo `items` en los 29 archivos
   `resources/scripts/locales/*.json` (un CSV o tabla). Detectar
   valores divergentes para el mismo concepto.
2. **Término canónico** (decisión de Juan, 2026-11-09): un artículo
   del catálogo sigue siendo un artículo aunque a veces se preste
   como servicio. No se canoniza a «servicio».
   - Si el idioma tiene palabra de facturación para «artículo»
     (es Artículo, fr Article, de/nl/sv Artikel, it Articolo,
     pt Artigo, ro Articol, hr Artikl, sr Artikal): esa.
   - Si «artículo» no se usa o significa otra cosa: la de
     «producto» (en Product, pt-br Produto, pl/cs/sk Produkt,
     tr Ürün, fi Tuote, el Προϊόν, sl Izdelek, id Produk,
     ru Товар, zh/ja 商品, ko 상품, th สินค้า, vi Mặt hàng,
     hi उत्पाद, fa کالا, ar صنف, lt Prekė, lv Prece).
   - Las claves no cambian (`items.services`, `items.add_service`, …).
     Solo los valores, más `navigation.items` (menú) y la columna
     PDF `pdf_items_label` cuando ya existía.
3. **Aplicar** el cambio en los 29 JSON (sólo claves del bloque `items`).
   Conservar las claves (`items.title`, `items.services`, ...) — sólo
   se modifican los valores.
4. **Verificar consumidores Vue**:
   - `resources/scripts/admin/views/items/{Index,Create,Edit}.vue`
   - breadcrumbs en `admin/views/{customers,estimates,invoices,payments,expenses}/...`
   - pdf labels (`pdf_items_label`, `pdf_sales_items_label`).
5. **Build** del front: `yarn build`. Debe terminar sin warnings
   sobre claves de i18n faltantes.
6. **Smoke test visual**: navegar a `/admin/items` en al menos es /
   en / fr y confirmar que **menú, breadcrumb y botón usan la misma
   palabra**.

## Resultado esperado

- [x] Un sustantivo de catálogo por idioma (artículo o producto).
      Claves intactas. Menú `navigation.items` y `pdf_items_label`
      alineados cuando la clave ya existía.
- [x] 31 JSON sin mezcla servicio/ítem/elemento/artículo dentro del
      bloque `items`. `locales.js` solo empaqueta 20; el resto queda
      listo en disco.
- [x] Cada `$t('items.X')` de Items/Create/Index existe en `en` y en
      el resto de locales.
- [x] `vite build` termina sin avisos de claves i18n. Aviso previo
      de tamaño de chunk, ajeno a este plan.
- [x] Bundle servido en `crater.test`: es Artículos / Nuevo artículo /
      Nombre del artículo; en Products / New Product / Product Name;
      fr Articles / Nouvel article / Nom de l'article; de Artikel /
      Neuer Artikel / Artikelname. No se abrió `/admin/items` (el
      formulario de login no pintó el `<form>` en esta sesión).

## Fuera de alcance

- Traducciones de **otros módulos** (`users`, `customers`, `payments`,
  ...). Cada uno tendría su propio plan si aplicase.
- Cambios al **wiring del locale** del usuario (`Company::language`,
  `Accept-Language`, etc.).
- Cambios de **copywriting** más allá de fijar el sinónimo: tono, longitud,
  formato, plurales propios de cada idioma. Eso es una pasada posterior.

## Notas

- Las claves `items.services` y `items.add_service` ya existen en
  varios locales y son las que la UI usa en `Index.vue`. Tratar
  `items.title` como un alias a mantener en sincronía (o deprecarlo
  si queda redundante — decisión del paso 2).
- Probablemente haya un patrón similar en otros módulos (e.g.
  `estimates` vs `cotizaciones`, `invoices` vs `facturas`). No se
  aborda aquí; ver si merece un plan 02 después.

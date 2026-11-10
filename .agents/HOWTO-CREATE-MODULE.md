# Cómo crear y registrar un nuevo módulo en Crater

> Documento de referencia (no committeado — vive en `.agents/`).
> Última revisión: 2026-11-09.
> Fuentes citadas son archivos reales de este repo. Si una sección queda
> desfasada porque el código cambió, abrir un plan en `plans/` para
> actualizarla antes de seguir usándola.

## 1. ¿Qué es un "módulo" aquí?

En Crater conviven **dos cosas distintas** bajo el paraguas "módulo":

| Concepto | Qué significa | Dónde vive |
|---|---|---|
| **Módulo core** | Funcionalidad que vive *dentro* del código del proyecto y se distribuye con él. | `app/`, `database/migrations/`, `config/crater.php`. Ejemplo: `Calculator`, `Backup`, `Ai`. |
| **Módulo instalable** | Paquete Laravel independiente que se enchufa al proyecto. Se compra/baja del marketplace de Crater o se copia como carpeta. | `Modules/{Name}/`. Construido sobre el paquete `nwidart/laravel-modules` (config en `config/modules.php`). |

> ⚠️ El "Modules" del menú principal (icono 🧩 en *Settings → Modules*)
> NO es una *categoría* de módulos: es la **UI que gestiona los módulos
> instalables** (listar, descargar, activar/desactivar).

Antes de empezar: **decide a qué familia pertenece** lo que vas a
construir (ver §6).

## 2. Pieza común a las dos rutas

Cualquier módulo — core o instalable — necesita:

1. **Permisos** registrados en `config/abilities.php` siguiendo el
   patrón `view-{cosa}` / `create-{cosa}` / `edit-{cosa}` /
   `delete-{cosa}`, con su `model` asociado y opcional `depends_on`
   sobre permisos previos.
2. **Entradas del menú** según corresponda:
   - **Sidebar principal**: `config/crater.php` → `main_menu` (con
     campos `link`, `icon`, `owner_only`, `ability`, `model`).
   - **Settings menú** (submenú de Configuración):
     `config/crater.php` → `settings_menu`.
   - **Módulos activables por empresa** (que el owner puede apagar):
     `config/crater.php` → `company_modules` — añadir
     `['key' => 'mi_modulo', 'menu' => 'Mi Módulo']`.
3. **Traducciones** en los 29 archivos `resources/scripts/locales/*.json`
   y `resources/lang/{en,fr,...}/...` (PHP). Las claves siguen la
   convención `sección.subclave`.
4. **Permisos propagados**: cuando el rol "owner" se crea, `Bouncer`
   recibe los `abilities` automáticamente; las abilities custom para
   roles se manejan en la UI `Roles` (controlado por
   `Role\RolesController`).

## 3. Ruta A — Módulo core

Es la ruta que tomó el módulo **`Calculator`** que ya está en el repo.
Sirve cuando:
- El módulo es parte inseparable del producto (no se compra aparte).
- Vas a hacer fork y re-publicar Crater con cambios grandes.
- No necesitas instalarlo/desinstalarlo sin tocar el código.

### Paso a paso (orden obligatorio)

1. **Migración**: nueva entrada en `database/migrations/` con timestamp
   `YYYY_MM_DD_HHMMSS_*` (Laravel ordena cronológicamente). Convención:
   - `snake_case`, plural para tablas (`calculators`, `calculator_items`).
   - FKs con `->cascadeOnDelete()` cuando sea razonable.
   - Una migración porConcern (cabecera + items, plantillas, etc.) *o*
     todas juntas si están acopladas; ver `2026_11_05_*.php` para el
     patrón Calculator.
2. **Modelo Eloquent** en `app/Models/`:
   - Namespace `Crater\Models\MiModelo`.
   - `$guarded = ['id']` salvo que haya razón real para `$fillable`.
   - `$casts` con tipos explícitos para fechas / json / booleans —
     **nunca uses la propiedad legacy `$dates`**, está deprecated en
     Laravel 13 (ver `app/Models/Invoice.php` para ejemplo correcto).
   - `scopeWhereCompany` cuando aplique (lee header `company`).
3. **Form Request** en `app/Http/Requests/`:
   - Validación de cada campo del payload.
   - Mensajes a `$t(...)` cuando aplique.
4. **Resource (transformer)** en `app/Http/Resources/`:
   - Expone solo los campos públicos + formatted options.
   - Idealmente cargar relaciones vía `whenLoaded`.
5. **Controller** en `app/Http/Controllers/V1/Admin/MiModulo/`:
   - Convención: una clase por acción "verbosa" (`MiModuloController`,
     `CambiarEstadoController`, `BulkDeleteController`, etc.).
   - `__invoke(Request)` cuando la ruta sólo necesita una acción
     (patrón usado en `DashboardController`).
   - Llamar `$this->authorize('view mi_modulo', $model)` siempre que
     haya `model` + ability asociada.
6. **Rutas API** en `routes/api.php`:
   - Dentro del grupo `['auth:sanctum','company']` ya existente.
   - `Route::apiResource('/mi-modulo', MiModuloController::class)` para
     el CRUD estándar.
   - `Route::post('/mi-modulo/{id}/accion', AccionController::class)`
     para acciones puntuales.
7. **Permisos** en `config/abilities.php`: una entrada por acción
   CRUD con `model` apuntando a `MiModelo::class`.
8. **Sidebar** en `config/crater.php` → `main_menu` o `settings_menu`,
   con `link`, `icon` (icono de heroicons), `owner_only` y `ability`.
9. **`company_modules`** si el owner debe poder activar/desactivar el
   bloque entero por empresa.
10. **Frontend Vue**:
    - Vistas en `resources/scripts/admin/views/{mi-modulo}/`
      (`Index.vue`, `Create.vue`, `Edit.vue`).
    - Store Pinia en `resources/scripts/admin/stores/mi-modulo.js`
      (axios inline siguiendo patrón de `item.js`).
    - Ruta frontend en `resources/scripts/admin/admin-router.js`
      (`/admin/mi-modulo`, `/admin/mi-modulo/create`, `...:{id}/edit`).
    - Breadcrumb: `<BaseBreadcrumbItem :title="$t('mi_modulo.title')" />`.
11. **Traducciones**: añadir bloque `mi_modulo: { title, plural, ... }`
    en los 29 locales; usar la misma palabra (ver Plan 01 sobre
    consistencia de traducciones).

### Verificación end-to-end

```bash
php artisan migrate                       # crea las tablas
php artisan optimize:clear                # limpia caches
php artisan route:list --name=mi-modulo   # confirma rutas nuevas
yarn build                                # re-build del front
php artisan serve
```

Luego, en el navegador:
- Sidebar → aparece la entrada.
- Click → carga la página.
- Crear/editar/eliminar una instancia: 200 OK.
- Como usuario *no-owner*, la entrada del sidebar **no** debe mostrarse
  si `owner_only => true` (verificar con un usuario secundario).

## 4. Ruta B — Módulo instalable

Sirve cuando:
- El código es opcional y un cliente decide si lo compra o no.
- Quieres distribuirlo como zip en el marketplace de Crater.
- Debe poder activarse/desactivarse *sin* redeploy.

### Scaffolding

```bash
php artisan module:make NombreDelModulo
```

Esto crea `Modules/NombreDelModulo/` con subcarpetas estándar de
`nwidart/laravel-modules` (definidas en `config/modules.php → paths`):

```
Modules/NombreDelModulo/
├── Config/
├── Console/
├── Database/
│   ├── Migrations/
│   ├── Seeders/
│   └── factories/
├── Entities/                  ← modelos (no Models/)
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Providers/
│   └── NombreDelModuloServiceProvider.php
├── Resources/
│   ├── assets/
│   ├── lang/
│   └── views/
├── Routes/
│   ├── api.php
│   └── web.php
├── Tests/{Unit,Feature}/
└── composer.json
```

> **Convención de namespace**: todo dentro del módulo vive bajo
> `Modules\NombreDelModulo\...`. Ej. `Modules\NombreDelModulo\Entities\MiModelo`.

### Pasos específicos

1. Generar scaffolding.
2. Llenar `composer.json` del módulo con nombre, autoload PSR-4.
3. Implementar dentro de `Entities/`, `Http/Controllers/`, etc.
4. Las **rutas** se declaran dentro del módulo
   (`Modules/.../Routes/{api,web}.php`) y se cargan vía el service
   provider que `nwidart/laravel-modules` genera automáticamente.
5. Las **migraciones** viven dentro del módulo y se ejecutan con
   `php artisan module:migrate NombreDelModulo` — no se mezclan con las
   del core.
6. Las **traducciones** se cargan via
   `Modules/.../Resources/lang/{locale}/...` y `Resources/locales/`
   (rutas config en `modules.php → paths.generator.lang/views`).

### Activación

- Antes/después de instalar: `php artisan module:enable NombreDelModulo`
  (o `:disable`). El estado se persiste en
  `storage/app/modules_statuses.json` (file activator, ver
  `config/modules.php → activators`).
- La tabla `modules` en BD (*no* `module_statuses`) es el **registro de
  instalación/versión** mantenida por
  `app/Space/ModuleInstaller.php` + `app/Http/Controllers/V1/Admin/Modules/*`.
  Es la fuente de verdad para mostrar el módulo en la UI de Modules.

### Marketplace

- `POST /api/v1/modules/upload` → sube un zip con la estructura
  estándar anterior y lo instala.
- `POST /api/v1/modules/{name}/enable` / `/disable` prenden y apagan.
- `GET  /api/v1/modules` lista los del marketplace remoto (vía
  `Space\SiteApi`, ver `ModuleInstaller::getModules()`).

### Permisos y sidebar del módulo

- Los permisos viven **dentro del módulo**, normalmente en su
  `ServiceProvider::boot()` con `Bouncer::allow(...)->to(...)`.
- Para que aparezca en el sidebar, el módulo debe **registrar la entrada
  vía hook** sobre el config `crater.main_menu` (o `crater.settings_menu`)
  en su service provider. Revisar ejemplos de módulos publicados por
  el equipo de Crater para el patrón exacto.

## 5. Decisión rápida: ¿A o B?

| Pregunta | A (core) | B (instalable) |
|---|---|---|
| ¿Se distribuye con el repo? | sí | no, se entrega aparte |
| ¿Es opcional por empresa? | raro | sí (encendido/apagado) |
| ¿Cambios rompen el producto? | sí, requiere deploy | no |
| ¿Vive en `app/`? | sí | no, vive en `Modules/` |
| ¿Migraciones en `database/migrations/`? | sí | no, en `Modules/.../Database/Migrations/` |

**Regla práctica**: si dudas, **A**. Las features grandes como
Calculator, AI, Backup están todas en A. Los módulos `nwidart`-style
son para entregar funcionalidades verticales vendibles.

## 6. Convenciones de nombres (resumen)

- **Tablas SQL**: snake_case en plural (`calculator_items`).
- **Modelos Eloquent**: PascalCase singular (`CalculatorItem`).
- **Rutas API**: kebab-case en plural (`/api/v1/calculator-templates`).
- **Rutas Vue (router)**: kebab-case en singular o plural según la página
  (`/admin/calculator`, `/admin/calculators/create`).
- **Ability**: `view-{cosa}`, `create-{cosa}`, `edit-{cosa}`,
  `delete-{cosa}`. Singular en singular, pero acepta plural si la
  entidad lógica es plural.
- **i18n key en vue-i18n**: snake_case, agrupando bajo el nombre del
  módulo (`mi_modulo.title`, `mi_modulo.list`, ...).

## 7. Comandos útiles

```bash
# Core
php artisan migrate                                # migrar cambios
php artisan migrate:rollback --step=1              # volver atrás
php artisan optimize:clear                         # limpiar cache de bootstrap

# Módulos instalables
php artisan module:list                            # módulos detectados
php artisan module:make MiModulo                   # scaffold
php artisan module:enable  MiModulo
php artisan module:disable MiModulo
php artisan module:migrate MiModulo
php artisan module:seed MiModulo
php artisan module:cache                           # descubrir cambios rápido
php artisan module:cache:clear

# Mercado / marketplace
php artisan tinker
>>> Crater\Space\ModuleInstaller::getModules()    # fetch remoto
```

## 8. Verificaciones globales antes de mergear

Antes de subir un PR con un módulo nuevo:

- [ ] `php artisan migrate` corre limpio en local y la nueva tabla existe.
- [ ] `php artisan optimize:clear && yarn build` termina sin warnings.
- [ ] Smoke test con usuario admin (todo permitido) y con un rol sin
      permisos para el nuevo `ability` (entrada del sidebar oculta,
      `403` al pegar la URL directo).
- [ ] En `/admin/settings/modules` (marketplace) no aparecen duplicados.
- [ ] En los 29 locales no quedó ninguna clave `$t('mi_modulo.*')` sin
      valor (= warning de vue-i18n en consola del navegador).
- [ ] Si el módulo agrega entradas en `main_menu`, verificar que el
      contador del dashboard (`General\SidebarCountsController`) sigue
      ok (no rompió agregaciones existentes).

---

> **Detalle de version**: Laravel 13.40, PHP 8.5, paquete
> `nwidart/laravel-modules` vía wrapper `crater-invoice/modules` ^1.0.
> Si Crater actualiza esos paquetes, revisar este documento.

# Plan de migración Laravel 8 → 13 (paso a paso)

Repo: `crater-invoice/crater` (fork `nexuz-io/crater`)
PHP inicial: `^7.4 || ^8.0` → final: `^8.3`

> Importante: cada salto mayor debe ir en **un commit aislado**.
> Antes de cada commit: `composer install`, `vendor/bin/pest` / `vendor/bin/phpunit`, levantar `php artisan serve` y admin y verificar.

---

## Paso 1 — Rama `main`

Ya creado a nivel de refs:
- `.git/HEAD` → `ref: refs/heads/main`
- `.git/packed-refs` contiene `494c8cfa… refs/heads/main`
- `.git/config` apunta `[branch "main"]` a `refs/heads/main`

`master` se mantiene como red de seguridad local hasta que confirmes el remoto.

### Comandos shell que tienes que correr (bloqueados por el problema del shell actual)

```bash
git -C C:\www\crater push -u origin main
git -C C:\www\crater push origin :master      # o --delete
git -C C:\www\crater branch -D master         # local cleanup
# en GitHub: Settings → Default branch → main
git -C C:\www\crater remote set-head origin main
```

---

## Paso 2 — Laravel 8 → 9  (`composer-v9.json`)

Cambios clave:

| Paquete | Antes | Ahora |
|---|---|---|
| `php` | `^7.4 || ^8.0` | `^8.0` |
| `laravel/framework` | `^8.0` | `^9.0` |
| `fruitcake/laravel-cors` | `^1.0` | **eliminar** (lo absorbe Laravel) |
| `facade/ignition` | `^2.3.6` | `^2.5` |
| `laravel/sanctum` | `^2.6` | `^2.9\|^3.0` |
| `laravel/tinker` | `^2.0` | `^2.5` |
| `laravel/ui` | `^3.0` | `^3.4` |
| `league/flysystem-aws-s3-v3` | `^1.0` | `^3.0` (compatible con `league/flysystem 3.x`) |
| `nunomaduro/collision` | `^5.0` | `^6.1` |
| `phpunit/phpunit` | `^9.3` | `^9.5.10` |
| `silber/bouncer` | `v1.0.0-rc.10` | `^1.5\|^2.0` |
| `spatie/laravel-backup` | `^6.11` | `^7.0\|^8.0` |
| `spatie/laravel-medialibrary` | `^8.7` | `^9.0\|^10.0` |
| `spatie/flysystem-dropbox` | `^1.2` | `^2.0` |
| `vinkla/hashids` | `^9.0` | `^10.0` |
| `barryvdh/laravel-dompdf` | `^0.9.0` | `^1.0\|^2.0` |
| `intervention/image` | `^2.3` | pasar a `intervention/image: ^2.7` o `intervention/image-laravel: ^1.x` |
| `beyondcode/laravel-dump-server` | `^1.0` | **eliminar** (no hubo release compatible con L9) |
| `predis/predis` | `^1.1` | OK (se mantiene o se cambia a extensión phpredis) |

Breaks reales que verás en el código (no exhaustivo):
- `App\Exceptions\Handler` se renombra a `App\Exceptions\Handler` y desaparece `$dontReport`/`$render` en favor de `register()` (ya estaba en L8, no rompe).
- Rutas: `Route::get('/v1/foo', …)` ahora sin cambios.
- Constructor de controllers: `parent::__construct($middleware…)` sigue igual.
- `Illuminate\Support\Facades\Route` sin cambios.
- Mailables: `Build()` ya no se sigue usando, debe ser `build()` — ojo si tu código tiene `Build()` en algún Mailable custom.
- Flysystem 3.x cambia API de Storage. Cualquier uso de `Storage::disk('s3')->put(...)` con streams cambia firma.
- `laravel/helpers` se elimina en L9 → reemplazar por helpers globales o copiar.
- En `config/cache.php` cambia el manejo de tags con `array`/`null`.
- `auth.passwords` cambia estructura con `password_timeout`.

### Shell tras editar `composer.json`

```bash
composer -d C:\www\crater update --no-scripts
composer -d C:\www\crater dump-autoload
vendor/bin/pest --filter=Unit   # o phpunit
php artisan test
git -C C:\www\crater add -A
git -C C:\www\crater commit -m "chore(deps): bump laravel 8 → 9"
```

---

## Paso 3 — 9 → 10 (`composer-v10.json`)

| Paquete | Cambio |
|---|---|
| `php` | `^8.1` |
| `laravel/framework` | `^10.0` |
| `facade/ignition` | **eliminar**, mover a `spatie/laravel-ignition: ^2.0` |
| `laravel/tinker` | `^2.7\|^2.8` |
| `nunomaduro/collision` | `^7.0` |
| `phpunit/phpunit` | `^10.0` |
| `pestphp/pest` | `^2.0` |
| `pestphp/pest-plugin-laravel` | `^2.0` |
| `pestphp/pest-plugin-faker` | `^2.0` |
| `pestphp/pest-plugin-parallel` | `^1.1` |
| `pestphp/pest-plugin` | ahora `true` en `allow-plugins` |
| `spatie/laravel-medialibrary` | `^11.0` |
| `spatie/laravel-backup` | `^8.0` |
| `vinkla/hashids` | `^11.0` |
| `silber/bouncer` | `^1.5\|^3.0` |

Breaks reales:
- `spatie/laravel-ignition` reemplaza `facade/ignition` (config, registro).
- `phpunit/phpunit` 10 cambia `assertEquals` defaults.
- Pest 2 cambia sintaxis de helpers en `pest` y el binario.
- `spatie/flysystem-dropbox` de 2 → 3 si quieres.

---

## Paso 4 — 10 → 11 (`composer-v11.json`)

| Paquete | Cambio |
|---|---|
| `php` | `^8.2` |
| `laravel/framework` | `^11.0` |
| `phpunit/phpunit` | `^11.0` |
| `nunomaduro/collision` | `^8.0` |
| `spatie/laravel-medialibrary` | `^11.0\|...` (mantener) |
| `spatie/laravel-backup` | `^9.0` |
| `silber/bouncer` | `^1.5\|^2.0\|^3.0` |

Breaks reales (grandes):
- **Estructura nueva**: `bootstrap/app.php` reemplaza `bootstrap/cache/services.php` para definir middleware, excepciones, routing.
- `app/Http/Kernel.php` y `app/Console/Kernel.php` y `app/Exceptions/Handler.php` desaparecen; ahora se configuran en `bootstrap/app.php` con `withMiddleware()`, `withExceptions()`, `withCommands()`.
- Los service providers ya no registran `middleware`, `routes`, `broadcasting channels`. Se hacen en `bootstrap/app.php`.
- Nueva API de testing.
- `routes/api.php` se carga por defecto con `prefix: 'api'` (antes era opt-in).

---

## Paso 5 — 11 → 12 (`composer-v12.json`)

| Paquete | Cambio |
|---|---|
| `php` | `^8.3` |
| `laravel/framework` | `^12.0` |
| Actualización de todas las deps a versiones compatibles | – |

Breaks reales:
- Más consolidación de bootstrap/app.php.
- Nuevos defaults en validación, casting.
- Cambios menores en `Carbon` (sigue transitando).

---

## Paso 6 — 12 → 13 (`composer-v13.json`)

| Paquete | Cambio |
|---|---|
| `php` | `^8.3\|^8.4` (cuando aplique) |
| `laravel/framework` | `^13.0` |
| Paquetes de terceros a actualizar a su `v13`-compatible | – |

> Cuando salga 13, revisar notas oficiales. Mantener el flujo: bump de mayor + fix de código + tests + commit.

---

## Cómo seguir cuando el shell vuelva a funcionar

1. `cd C:\www\crater`
2. Empieza por el **Paso 2** (L8→L9). Si todo OK, commit, pasa al 3.
3. Si composer falla por incompatibilidad de una dep, identifica el paquete, busca su rama máxima compatible y ajusta.
4. Pega aquí la salida de `composer update` y te ayudo con los breaks.

## Estado actual
- [x] `.git/HEAD` apunta a `main`
- [x] `.git/packed-refs` tiene `refs/heads/main`
- [x] `.git/config` rastrea `main` desde `origin`
- [ ] `git push -u origin main` (pendiente del shell)
- [ ] Eliminar `master` en remoto (pendiente)
- [ ] Composer.json intacto (decisión: empezamos por L9 cuando confirmes)
- [ ] composer-v9.json preparado como referencia del bump

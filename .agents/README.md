# `.agents/` — espacio de planes para agentes

Directorio **local** (ignorado por git, ver `/agents/` en `.gitignore`).
Cada desarrollador mantiene aquí sus propias notas / planes de trabajo
generados por herramientas IA / agentes / sesiones locales.

## Convenciones

- **Un plan = un objetivo.** Si la tarea cambia de objetivo en la mitad,
  se crea un plan nuevo (carpeta `plans/02-...`, `03-...`).
- **Numeración secuencial** (`01-`, `02-`, ...) para que el orden de
  aparición sea estable: `01-translation-consistency-items.md`,
  `02-add-foo-feature.md`, etc.
- **Estructura común** definida en `TEMPLATE.md`. Secciones obligatorias:
  *Objetivo*, *Plan*, *Resultado esperado*. Opcionales: *Contexto*,
  *Fuera de alcance*, *Notas*.
- **Idioma**: español o el del proyecto. El proyecto está mayormente
  documentado en francés (`DEVELOPPEMENT-LOCAL.md`); los planes en
  español son válidos.
- **No commitear** nada de aquí. La línea `/.agents/` en `.gitignore`
  lo prohíbe a nivel repo.

## Cómo empezar un plan nuevo

1. Copiar `TEMPLATE.md` a `plans/NN-titulo-corto.md`.
2. Rellenar **Objetivo** con una sola frase (qué se quiere conseguir).
3. Listar los pasos en **Plan**.
4. Definir qué cuenta como "hecho" en **Resultado esperado**.
5. Borrar las secciones que no apliquen.

## Cómo cerrar un plan

Cuando el objetivo se cumple:

- Mover el archivo a `plans/archive/` (crear la carpeta si no existe).
- Opcional: añadir en línea final `> Estado: cerrado YYYY-MM-DD`.

## Por qué `plans/` está ignorado

Los planes son privados, volátiles, y a menudo incluyen hipótesis
que no deberían contaminar el repo. El repo mantiene su trazabilidad
a través de commits, no de planes.

# Plantilla de plan

> Copiar este archivo a `plans/NN-titulo-corto.md` y rellenar.
> Borrar las secciones opcionales que no apliquen.

---

## Objetivo

> *Una sola frase.* Qué se quiere conseguir. No cómo.

Ejemplo: "Que el módulo `items` use **un único término** en cada
locale (es/en/fr/...) a lo largo de toda la UI: menú, breadcrumb,
título de página, botones, labels."

## Contexto *(opcional)*

Por qué es necesario. Qué problema / bug / inconsistencia lo dispara.
Incluir referencias a archivos / líneas si ayudan.

Ejemplo: "En `fr.json`, el bloque `items` mezcla 'Services' y
'articles' en la misma sección; en `es.json` el menú dice
'Artículos' pero el breadcrumb y botones dicen 'Servicios'."

## Plan

1. Paso concreto.
2. Otro paso, ordenado y verificable.
3. ...

Si un paso depende de otro, indicarlo ("paso 3 requiere paso 2 listo").
Si hay piezas de código a tocar, nombrar archivos por ruta absoluta
desde la raíz del proyecto.

## Resultado esperado

Qué condiciones se cumplen cuando el plan está terminado. Listas
de checks son bienvenidas. Si hay criterios que se pueden validar
automáticamente (consola, test, build), mencionarlos.

Ejemplo:

- [ ] Los 29 archivos `resources/scripts/locales/*.json` usan un
      único término consistente dentro del bloque `items`.
- [ ] `yarn build` corre limpio.
- [ ] Inspección visual: en español, el menú y el breadcrumb de
      `/admin/items` muestran la misma palabra.

## Fuera de alcance *(opcional)*

Qué **no** se aborda en este plan. Sirve para evitar scope-creep
y para que un próximo plan pueda retomar lo descartado.

## Notas *(opcional)*

Hipótesis, decisiones intermedias, links a docs externas, etc.
No usar para cosas accionables — esas van en *Plan*.

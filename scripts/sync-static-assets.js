#!/usr/bin/env node
/*
 * Sync static assets — runs as prebuild + postbuild.
 *
 * Crater keeps user-facing images in `resources/static/img/` and
 * `resources/static/fonts/`, but the Laravel web root is `public/`
 * and the Vite production bundle lives in `public/build/`.
 *
 * Without this script, Vite's `publicDir` doesn't know about
 * `resources/static/`, so:
 *   /img/default-avatar.jpg          -> 404 in dev (vite serves from public/)
 *   /build/img/default-avatar.jpg    -> 404 in prod (vite outputs only assets/)
 *
 * We copy `resources/static/img*` -> `public/img/` and
 * `resources/static/fonts*` -> `public/fonts/` so:
 *   - Dev:  Vite serves them at `/img/...` and `/fonts/...` from publicDir.
 *   - Prod: Vite's publicDir copying moves them into `public/build/...`
 *           during `vite build`.
 *
 * Idempotent: re-running is harmless; `fs.cpSync({ recursive: true,
 * force: true })` overwrites.
 */

const fs = require('node:fs')
const path = require('node:path')

const SOURCE_STATIC = path.resolve(__dirname, '..', 'resources', 'static')
const PUBLIC_DIR = path.resolve(__dirname, '..', 'public')

const COPY_MAP = [
  { from: 'img', to: 'img' },
  { from: 'fonts', to: 'fonts' },
]

function copy (mapping) {
  const src = path.join(SOURCE_STATIC, mapping.from)
  const dst = path.join(PUBLIC_DIR, mapping.to)

  if (! fs.existsSync(src)) {
    console.warn(`[sync-static] skip: source missing (${src})`)
    return
  }

  fs.mkdirSync(dst, { recursive: true })
  fs.cpSync(src, dst, { recursive: true, force: true })
  console.log(`[sync-static] ${path.relative(PUBLIC_DIR, src)} -> ${path.relative(PUBLIC_DIR, dst)}`)
}

for (const mapping of COPY_MAP) {
  copy(mapping)
}

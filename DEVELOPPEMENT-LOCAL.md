# Exécuter et développer le projet Crater en local

> **À lire avant toute action.** Ce document décrit la façon dont CE projet fonctionne réellement en local.
> Ne pas suggérer de lancer `npm run dev` : ce n'est pas le mode de fonctionnement de ce projet.

## Stack

- Backend : Laravel 13 (PHP 8.4), base MariaDB ou SQLite
- Frontend : Vue 3 + Vite 2 (paquet `laravel-vite` côté JS)
- Infra locale : PHP installé sur la machine hôte (`php`, `composer`). Docker n'est plus utilisé (le repo `crater-docker` est séparé si on en a besoin).

## Règle n°1 : pas de serveur de dev Vite

L'application sert les assets **pré-construits** depuis `public/build` (via le manifest lu par `laravel-vite`).
Il n'y a **pas** de flux `npm run dev` à utiliser. Pour voir une modification frontend :

1. Rebuild du frontend (voir ci-dessous) ;
2. Rechargement de la page sur `http://localhost:8000` (port par défaut de `php artisan serve`).

## Environnement local

- PHP 8.4 (ou 8.3) doit être disponible en ligne de commande. Sur macOS, [Herd](https://herd.laravel.com/) ou [Valet](https://laravel.com/docs/valet) ;
  sur Linux, le paquet de la distro ; sur Windows, Laragon ou WSL.
- Composer 2.x : `composer --version` doit répondre.
- Démarrage : `cd C:\www\crater && php artisan serve` puis ouvrir
  `http://127.0.0.1:8000`. Si la base n'est pas configurée, copier
  `.env.example` en `.env` et lancer `php artisan key:generate`.
- Pour MariaDB en local : la plupart des distributions (Herd, Laragon, XAMPP)
  démarrent MySQL/MariaDB automatiquement ; sinon la base SQLite
  (`database/database.sqlite`) suffit pour le développement.

## Autocomplétion IDE (laravel-ide-helper)

Le projet utilise [`barryvdh/laravel-ide-helper`](https://github.com/barryvdh/laravel-ide-helper)
pour que PhpStorm / VS Code puisse autocompléter les facades, les modèles Eloquent,
la config, etc. Les fichiers générés (`_ide_helper.php` et `.phpstorm.meta.php`)
**ne sont pas versionnés** : ils doivent être régénérés localement après chaque
`composer install` ou après avoir modifié un modèle / une migration :

```bash
php artisan ide-helper:generate        # génère _ide_helper.php
php artisan ide-helper:models --nowrite # ajoute les PHPDoc aux modèles depuis la BD
php artisan ide-helper:meta             # génère .phpstorm.meta.php (PhpStorm)
```

`ide-helper:generate` lit `composer.json` et `config/app.php`, donc il doit être
lancé **depuis la racine du projet** après avoir installé les dépendances. Si
l'IDE se plaint de méthodes manquantes sur des facades (`Cache::`, `Route::`,
`Auth::user()`, etc.), c'est presque toujours que ces fichiers ne sont pas à
jour — les régénérer règle le problème.

## Rebuild du frontend (nécessaire après toute modif JS/CSS)

Le frontend se build avec **yarn** (le `yarn.lock` fait foi ; le `package-lock.json` présent est obsolète — ne pas utiliser `npm ci`, il échoue avec des erreurs ERESOLVE/lockfile incohérent).

Le build appelle `php artisan vite:config` pour découvrir les entrypoints, donc il a besoin de PHP disponible directement (plus de shim Docker) :

```bash
yarn build   # ou: npm run build
```

Astuce environnement : Node 16 est la version sûre pour Vite 2 (les versions récentes peuvent produire des chunks différents).

## Pièges connus du build

- **Icônes heroicons** : `BaseIcon` résout les icônes dynamiquement par leur nom via
  `resources/scripts/components/hero-icons.js` (imports explicites). Ne pas repasser à un
  `import * as heroIcons from '@heroicons/vue/outline'` : le tree-shaking de Rollup supprime
  alors les icônes uniquement référencées par leur nom → icônes manquantes **en prod uniquement**
  (le dev masque le problème car il n'y a pas de tree-shaking en dev).
- Ne pas retirer `shrink-0` des classes d'icônes de `BaseIcon`/`PencilSheetIcon` : sans lui, les
  icônes des menus déroulants s'écrasent à 0px quand un libellé est long.

## Déploiement prod (automatique)

- `public/build` n'est **pas versionné** dans git. À chaque push sur `main`, le workflow
  `.github/workflows/deploy.yml` : build du frontend dans GitHub Actions → `git pull` sur le
  droplet + upload scp des assets avec swap atomique (`build-new` → `build`).
- Aucune manipulation manuelle sur la VM. Ne pas committer `public/build`.
- Pour l'infra Docker en prod, voir le repo `crater-docker` (séparé).

## Mail

- La prod utilise Brevo en SMTP (`smtp-relay.brevo.com`, port **2525** — le port 587 est bloqué
  par DigitalOcean). Login SMTP : `c181c3001@smtp-brevo.com` + clé SMTP (pas le mot de passe du
  compte Brevo). La config se règle dans l'UI : Paramètres → Configuration des emails.
- Le bouton des courriels facture/devis pointe directement vers le PDF (`?pdf=1`) et son libellé
  est traduit selon le réglage `language` de la société.

## Comptes / auth en local

- Créer ou réinitialiser un mot de passe via artisan tinker, **en passant le mot de passe en clair**
  (le modèle `User` hache automatiquement via un mutateur — ne jamais passer `bcrypt(...)` soi-même,
  sinon double hachage et connexion impossible) :
  ```bash
  php artisan tinker --execute='
    $u = \Crater\Models\User::where("email","...")->first();
    $u->password = "motdepasse"; $u->save();'
  ```

## Branches et déploiement

- Travailler sur une branche feature ; le push sur `main` déclenche le déploiement prod.
- CI : les assets frontend sont construits par GitHub Actions (Node 16 + yarn), pas localement,
  pas sur la VM.

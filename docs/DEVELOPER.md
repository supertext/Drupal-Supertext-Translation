# Developer guide

This repository builds the Drupal **demo site** for Supertext AI translation. The
translator itself is the drupal.org module
[tmgmt_supertext_ai](https://www.drupal.org/project/tmgmt_supertext_ai) (maintained by
MD Systems); the demo installs it with Composer like any site would. Changes to the module
go to its issue queue on drupal.org, not here.

## Layout

| Path | What it is |
| --- | --- |
| `demo/Dockerfile` | PHP 8.3 + Apache image; build context is the repository root |
| `demo/composer.json`, `demo/composer.lock` | Drupal core, Drush, TMGMT and tmgmt_supertext_ai, versions locked |
| `demo/docker/settings.php` | Drupal settings, everything from the environment |
| `demo/docker/entrypoint.sh` | Start-up: database, install, recipes, modules, `setup.php`, Apache |
| `demo/docker/setup.php` | Idempotent setup run by `drush php:script` on every start |
| `docs/images/` | Screenshots for the guides |

## What happens on start

1. Waits for MySQL and creates the database (`DRUPAL_DB_NAME`) if it's missing. Several
   demos share one MySQL server, each with its own database.
2. If Drupal isn't installed yet: `drush site:install standard`, with the administrator
   from `DEMO_ADMIN_*`.
3. Since Drupal 11.2 the standard profile no longer creates content types: the core
   recipes `basic_html_format_editor`, `page_content_type`, `article_content_type` and
   `content_editor_role` are applied once.
4. Installs `language`, `content_translation`, `tmgmt`, `tmgmt_content` and
   `tmgmt_supertext_ai`.
5. `setup.php`: adds German, French and Italian, turns on content translation for pages and
   articles, gives the *Content editor* role the translation and TMGMT permissions, creates
   the editor account, configures the Supertext provider and creates five English pages and
   articles (once).
6. `drush updatedb`, `drush cache:rebuild`, then Apache on `$PORT`.

Every step checks what's already there, so restarts and redeploys change nothing that
exists.

## Variables

| Variable | Required | Purpose |
| --- | --- | --- |
| `DATABASE_URL` (or `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`) | yes | MySQL server, `mysql://user:pass@host:port/db` |
| `DRUPAL_DB_NAME` | no | Database name (default: the path of `DATABASE_URL`, else `drupal`); created if missing |
| `DEMO_ADMIN_EMAIL`, `DEMO_ADMIN_PASSWORD` | yes | Administrator (login `admin`), created by the installer |
| `DEMO_EDITOR_EMAIL`, `DEMO_EDITOR_PASSWORD` | no | Editor (login `editor`, role *Content editor*), can translate into every demo language |
| `SUPERTEXT_API_KEY` | no | Seeds the provider's API key while it is still empty |
| `SUPERTEXT_ENVIRONMENT` | no | Provider's API server when it is first created (`live`, default) |
| `DRUPAL_HASH_SALT` | no | Default: derived from `DATABASE_URL`, so sessions survive redeploys |
| `PORT` | no | Apache port, default 8080 |

Demo accounts follow the rule of all Supertext demos: created on the first start, **never
changed afterwards** (no password resets from variables, no duplicates). A password that
Drupal rejects skips that account with a warning. The log names the variable, never the
password.

## Language codes

Supertext needs a regional **target** code (`de-CH`, not `de`; a bare target gets
`INVALID_LANGUAGE_PAIR`) and a bare **source** code (`en`). The module sends TMGMT's remote
language mapping unchanged, and TMGMT defaults to the Drupal codes, so `setup.php` maps
`de` → `de-CH`, `fr` → `fr-CH`, `it` → `it-CH` while a language has no mapping of its own.

## Local run

```sh
docker build -f demo/Dockerfile -t drupal-supertext-demo .
docker network create demo
docker run -d --name mysql --network demo -e MYSQL_ROOT_PASSWORD=secret mysql:8.4
docker run --rm --network demo -p 8080:8080 \
  -e DATABASE_URL=mysql://root:secret@mysql:3306/drupal \
  -e DEMO_ADMIN_EMAIL=admin@example.com -e DEMO_ADMIN_PASSWORD=change-me \
  -e DEMO_EDITOR_EMAIL=editor@example.com -e DEMO_EDITOR_PASSWORD=change-me-too \
  drupal-supertext-demo
```

Then http://localhost:8080/user/login. To update Drupal or the modules:
`cd demo && composer update`, rebuild, test, commit `composer.lock`.

## Railway

Service **Drupal** in the project *supertext-cms-demos-php*: source this repository
(`main`), Dockerfile path `demo/Dockerfile`, health check `/healthz` (a static file, so it
doesn't depend on Drupal's routing), region `ams`, database `drupal_demo` on the shared
MySQL. `DEMO_*` and `SUPERTEXT_API_KEY` reference the shared values of the Contao service
(`${{Contao.DEMO_ADMIN_EMAIL}}` …). The container has no volume: uploaded files are lost on
redeploy, content and configuration live in MySQL.

Drush's `mysql` client checks the server certificate and Railway's MySQL is self-signed,
so `settings.php` sets `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` to false, which Drush
passes on. The one-time `site:install` still logs *Failed to drop or create the database*:
harmless, the entrypoint created it already.

## Code quality and security checks

- `.github/workflows/checks.yml`: actionlint and zizmor on every push and PR, dependency
  review on PRs.
- `.github/workflows/links.yml`: lychee checks the Markdown links weekly and when docs
  change; broken links open the issue "Broken links in the docs".
- `.github/dependabot.yml`: weekly Composer (`/demo`, minor and patch grouped), Docker base
  image and GitHub Actions updates.
- No PHPStan: the repository has no PHP code of its own besides the demo setup scripts.

## Screenshots

The images in `docs/images/` are taken from a local demo with Playwright (1200 px wide,
the API key masked). Regenerate them when the screens change.

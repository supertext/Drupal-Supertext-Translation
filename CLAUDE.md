# Working on this repository

The Drupal **demo site** for Supertext AI translation. The translator is the drupal.org
module `tmgmt_supertext_ai` (maintained by MD Systems): don't copy or patch it here; module
changes go to its drupal.org issue queue.

- Keep `README.md`, `docs/INSTALLATION.md`, `docs/USER_GUIDE.md` and `docs/DEVELOPER.md`
  current with every change, in the same commit, with screenshots in `docs/images/`.
- Demo accounts come from `DEMO_ADMIN_*` / `DEMO_EDITOR_*`, are created once and never
  changed; never log passwords.
- Every start must be idempotent (`demo/docker/entrypoint.sh`, `demo/docker/setup.php`).
- Target language mappings need a region (`de-CH`), see `docs/DEVELOPER.md` → Language codes.
- Lock dependency changes in `demo/composer.lock`.

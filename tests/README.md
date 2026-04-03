# Tests

- **Unit** (`tests/Unit/`): PHPUnit with a minimal bootstrap (`tests/bootstrap.php`). No full WordPress load; suitable for pure helpers (e.g. `Updatronix_Core_Update_Log_Versions`).
- **Manual regression**: `tests/MANUAL_REGRESSION.md` — release checklist (REST, roles, logging, cron).
- **Integration** (future): REST permissions, logger DB operations, and cron behavior can be covered with `WP_UnitTestCase` once a WordPress test library path is configured in CI or locally.

Run: `composer test` from the plugin root.

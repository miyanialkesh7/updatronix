# `.agents/` — workflow assets

Zed-compatible agent configuration. Always-on rules: `../AGENTS.md`. Human playbook: `HOW_TO_USE.md`.

| Path | Role |
|------|------|
| `skills/` | `/architect` · `/resume` · `/reviewer` · `/security` · `/release` |
| `templates/` | Reference task patterns (agent writes real tasks) |
| `tasks/` | Task files — external memory (gitignored) |
| `notes/` | Review & audit deliverables (gitignored) |
| `notes/archive/` | Historical notes |
| `docs/` | Large reference mirrors — **lookup only, never read whole files** |
| `scripts/` | Regenerate doc mirrors |

**Design:** One dev skill on Sonnet, disk-backed task files, thread rotation via `/resume`, Opus for security. See `AGENTS.md` for review rules, lint tiers, and doc policy.

**Git:** `skills/`, `templates/`, `docs/`, `scripts/` committed · `tasks/`, `notes/` gitignored.

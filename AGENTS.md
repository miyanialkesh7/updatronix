# Updatronix — Agent Instructions

## Communication

Messages may arrive in French or English. **Always reply in US English.** WordPress prose style: `.agents/docs/wordpress-documentation-style-guide-consolidated.md` (lookup sections only — never load the full file).

## Project Facts

| | |
|---|---|
| Slug & text domain | `updatronix` |
| Bootstrap | `updatronix.php` → `Updatronix_Bootstrap::init` |
| Version | `UPDATRONIX_VERSION` in `updatronix.php` |
| PHP / WP | 8.1+ (target 8.1–8.4) · 6.2+ |
| REST | `updatronix/v1` |
| Public hook | `do_action( 'updatronix_after_log', $log_id, $data )` |
| Code | `inc/classes/` · `inc/admin/` · `inc/settings/` · `assets/src/` → `assets/build/` · `languages/` |
| Storage | `{prefix}updatronix_logs` · `updatronix_settings` (JSON, autoloaded) · `updatronix_update_logger_state` (no autoload) · native `auto_update_*` options |
| Build & test | `workflow.md` |

## Workflow — Delegate, Don’t Micromanage

You describe the outcome. The agent plans, implements, lints, logs, and fixes from your test feedback. **You approve the plan once, then test.**

| Step | You | Agent |
|------|-----|-------|
| 1 | `/architect` + describe change | Clarify → plan → create task file |
| 2 | “go” / adjust plan | Execute all tasks autonomously |
| 3 | Test in Local | Fix issues from your notes |
| 4 | Done testing | `/reviewer` if required (see below) |
| 5 | Release ready | `/release` after explicit version authorization |

**Skills & models**

| Skill | Model | Role |
|-------|-------|------|
| `/architect` | **Sonnet 4.6** | Full dev cycle — start here |
| `/resume` | **Sonnet 4.6** | Continue after interrupt or thread rotation |
| `/reviewer` | Sonnet 4.6 · **Opus** if high-risk | Integration gate |
| `/security` | **Opus** — always | Standalone security audit |
| `/release` | Sonnet 4.6 | Version bump & packaging |

**Model guidance:** Sonnet 4.6 is the default workhorse (cost-effective for WP patterns). Opus for `/security`, ambiguous SQL/auth/REST **design**, and high-risk `/reviewer` threads. Avoid fast/cheap models — this plugin has real security surfaces.

## Long Sessions — Thread Rotation

Chat history is not memory. The **task file** is.

Rotate to a **new thread + `/resume`** when any trigger fires:

- **5 tasks** completed in one thread
- **~25 agent turns** in one thread
- End of your work day or before a long break
- Context feels stale (agent repeats questions or forgets decisions)

Before rotating, the agent updates `## Session checkpoint` in the task file (last task done, files touched, open decisions, review required).

In the new thread: `/resume` + `@.agents/tasks/YYYY-MM-DD-<type>-<slug>.md`.

## Reference Docs — Available, Not Mandatory

Large mirrors live in `.agents/docs/`. **Never read an entire mirror file.**

| File | Size | How to use |
|------|------|------------|
| `docs-library.md` | ~46k lines | Grep or read **one** `## Section` (~200 lines max). Curated content is **above** `<!-- updatronix:handbook-mirror:start -->` — prefer that region. |
| `wordpress-documentation-style-guide-consolidated.md` | ~19k lines | Grep TOC / `Source:` URL / one section when writing user-facing prose. |
| `wordpress-native-updates-reference.md` | Frozen | Cite only; never edit without owner authorization. |

Default: read **`inc/` source** and **`workflow.md`** first. Open docs only when the API or style rule is unclear.

## Task File — External Memory

Path: `.agents/tasks/YYYY-MM-DD-<type>-<slug>.md`

Sections: `Goal` · `Context` · `Tasks` · `Session checkpoint` · `Log` · `Feedback`

The agent creates and maintains this file. Templates in `.agents/templates/` are reference only.

Deliverables per feature: **one task file** + **one review note** (when required) at `.agents/notes/YYYY-MM-DD-review-<slug>.md`.

## Review — Required vs Optional

**`/reviewer` required** when the change touches any of:

- REST routes, Ajax handlers, or export/download flows
- SQL, custom tables, transients, or option schema
- Capabilities, auth, multisite, or role checks
- User input (forms, email, file paths, redirects)
- Admin UI with new interactive controls (a11y surface)

**Optional** (agent may skip if you agree): comments/PHPDoc only, pure SCSS cosmetics, typo/copy wrapped in i18n with no logic change.

When required, the architect must set `review_required: yes` in task frontmatter and say so at hand-off.

## Lint Cadence

| Tier | When | Commands |
|------|------|----------|
| **Immediate** | After each task touching REST, SQL, auth, sanitization, or JS/React | `composer run lint:php` and/or `npm run lint` + `npm run lint:css` |
| **Batch** | Low-risk tasks (comments, docs, pure CSS, internal refactor with no new surface) — at most every **3** tasks | Same commands, batched |
| **Full gate** | All tasks done | `npm run test:all` |

## Build & Lint Reference

| Command | When |
|---------|------|
| `composer run lint:php` | PHP changes |
| `npm run lint` / `npm run lint:css` | JS / SCSS |
| `npm run test:all` | End of dev cycle |
| `composer run lint:pcp` | Pre-release |
| `composer run make:pot` | i18n strings added/changed/removed |
| `npm run build` | Production assets |

## Hard Rules

**i18n** — Never reword strings inside `__()`, `_e()`, `_n()`, `_x()`, `esc_html__()`, `esc_attr__()`. Text domain stays `updatronix`. Intentional string change: flag in chat + changelog entry + wait for confirmation.

**Frozen** — `.agents/docs/wordpress-native-updates-reference.md` is read-only without owner authorization.

**Version** — Never bump `UPDATRONIX_VERSION`, headers, `Stable tag:`, or package versions without explicit owner authorization in the current conversation.

**Files** — Never delete `.agents/tasks/` or `.agents/notes/` without owner confirmation. Stay in task scope.

Human playbook: `.agents/HOW_TO_USE.md`.

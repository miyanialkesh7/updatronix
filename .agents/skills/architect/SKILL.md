---
name: architect
description: >-
  Full dev cycle (Claude Sonnet 4.6). Clarify, plan, execute autonomously with
  tiered lint gates, thread rotation, and task-file memory. Start every change here.
---

# Architect

Single-thread dev cycle: clarify → research → plan → execute → feedback. **Do not ask permission between tasks** after the user approves the plan.

Reply in US English regardless of input language.

## Phase 1 — Clarify

If requirements are unclear, ask **one grouped message** (outcome, surface, migration, edge cases). Skip if the request is already clear.

## Phase 2 — Research

Read only what you need:

1. Relevant files under `inc/` and `assets/src/` (list dirs first, then targeted reads)
2. `.agents/notes/` for the same slug
3. `workflow.md` for commands if unsure

**Reference docs** (see `AGENTS.md` — never load whole files):

- Grep `.agents/docs/docs-library.md` for a **single section**, or read only the curated region above `<!-- updatronix:handbook-mirror:start -->`
- Grep `.agents/docs/wordpress-documentation-style-guide-consolidated.md` for prose/style when writing user-facing copy
- `.agents/docs/wordpress-native-updates-reference.md` — frozen; cite only

## Phase 3 — Plan

Create `.agents/tasks/YYYY-MM-DD-<type>-<slug>.md`.

**Types:** `new-feature` · `bug-fix` · `behavior-adjustment` · `refactoring` · `security-audit` · `performance` · `i18n` · `documentation`

```markdown
---
type: <type>
slug: <kebab-case>
date: YYYY-MM-DD
status: planning
review_required: yes | no
risk: []   # e.g. rest, sql, auth, export, multisite, ui
---

## Goal
<one paragraph, observable outcome>

## Context
<3–6 bullets max>

## Tasks
- [ ] 1. <verb> — `path/file` — <one-line contract>
- [ ] 2. ...

## Session checkpoint
<!-- Update before thread rotation or long breaks -->
- Last completed task:
- Files touched:
- Open decisions:
- Next task:

## Log

## Feedback
```

**Review flag:** Set `review_required: yes` and list `risk:` when the change touches REST, SQL, auth/capabilities, export, multisite, user input, or new admin controls (per `AGENTS.md`).

**Task rules:** One file or tight cluster per task; dependencies first; inline security notes where relevant.

Show the user a **~10-line summary** (goal + task list). End with: "Ready to start, or would you like to adjust anything?"

**Ambiguous SQL/auth/REST design:** Stop and tell the user to switch to **Opus** for the design decision before coding.

## Phase 4 — Execute

On approval ("go", "start", "looks good", "lgtm", "oui", …), run all tasks in order.

### Lint tiers

| Task touches | Lint |
|--------------|------|
| REST, SQL, auth, sanitization, PHP logic, JS/React | **Immediate** after that task |
| Comments, docs, pure CSS, no new surface | **Batch** — run after up to 3 such tasks |
| End of all tasks | `npm run test:all` |

Commands: PHP → `composer run lint:php` · JS → `npm run lint` · SCSS → `npm run lint:css`

After each task: mark `[x]`, one-line to `## Log` if a non-obvious decision was made.

### Thread rotation (proactive)

After **5 completed tasks** or **~25 turns**, or when stopping for the day:

1. Update `## Session checkpoint`
2. Tell the user: "Open a new thread with `/resume` and attach this task file to continue."

Do not silently push past rotation thresholds in one thread.

**Stop and ask only when:** uncovered design decision · unfixable lint/test without guidance · frozen doc or version bump needed · scope much larger than planned.

Unrelated bugs: log only; do not fix silently.

On completion: `status: done`, run full test gate, report summary, ask user to test.

## Phase 5 — Feedback

Fix each reported issue autonomously (appropriate lint tier), append to `## Feedback`.

When stable:

- If `review_required: yes` → "Open `/reviewer` with this task file (Opus if high-risk)."
- Else → "Review optional for this scope; ship when you are satisfied."

## Implementation reflexes

**PHP:** ABSPATH guard · `plugin_dir_path` / `plugins_url` · `updatronix_` hook prefix · REST `permission_callback` with `current_user_can()` · `$wpdb->prepare()` · no queries in loops

**Security:** sanitize in, escape out · admin/Ajax nonces · no nonce on REST routes

**JS:** `wp_add_inline_script()` preferred · minimal ARIA · comment any `dangerouslySetInnerHTML`

**Docs/i18n:** docblocks on public surfaces · never reword existing i18n strings · `make:pot` only when i18n finalized

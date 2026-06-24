# How to Use — Updatronix Agents

You delegate. The agent plans, codes, lints, and fixes. Your job: describe outcomes, approve the plan, test, report issues.

## Setup (once)

1. Open the **plugin root** (`updatronix/`) in Zed.
2. Agent Panel → confirm **Using project AGENTS.md file**.
3. Skills → Project: `architect`, `resume`, `reviewer`, `security`, `release`.
4. Trust the workspace when prompted.

## Models

| Work | Model |
|------|-------|
| `/architect`, `/resume`, `/release` | **Claude Sonnet 4.6** |
| `/reviewer` (normal) | Sonnet 4.6 |
| `/reviewer` (REST, SQL, auth, export, multisite) | **Claude Opus** |
| `/security` | **Claude Opus** — always |

Sonnet keeps cost down for routine WP work. Opus is for security design, audits, and high-risk review. Fast/cheap models are a poor fit for this codebase.

## Daily flow — one feature

```
/architect
[describe what you want — French or English, high level is fine]

→ agent asks clarifying questions (one message) OR shows a plan
→ you: go  (or adjust)

→ agent executes all tasks, lints, logs to the task file
→ you: test in Local, paste issues

→ agent fixes, you retest
→ repeat until stable
```

The agent creates `.agents/tasks/YYYY-MM-DD-<type>-<slug>.md`. You never copy templates.

### When `/reviewer` is required

Mandatory if the change touches REST, SQL, auth/capabilities, export, multisite, user input, or new admin controls.

```
/reviewer
@.agents/tasks/YYYY-MM-DD-<type>-<slug>.md
```

Use Opus in the review thread for high-risk features. Output: `.agents/notes/YYYY-MM-DD-review-<slug>.md`.

Skip review only for low-risk work (comments-only, trivial CSS) when the agent confirms it.

Blockers found? New thread → `/resume` with the task file → fix → re-review.

## Long sessions — rotate threads

One thread does **not** need to last the whole feature. Rotate when:

- **5 tasks** done in the same thread, or
- **~25 agent turns**, or
- you stop for the day

```
/resume
@.agents/tasks/YYYY-MM-DD-<type>-<slug>.md
```

The agent reads `## Session checkpoint` + remaining tasks and continues. **Same delegation model** — you don’t re-explain the feature.

If the agent suggests rotation, accept it. Fresh context is cheaper and more accurate than a bloated thread.

## Interrupted session

Thread limit or crash? Same as rotation:

```
/resume
@.agents/tasks/YYYY-MM-DD-<type>-<slug>.md
```

## Security audit (standalone)

```
/security
[scope — feature slug, files, surfaces]
```

Always **Opus**. Output: `.agents/notes/YYYY-MM-DD-security-<slug>.md`.

Fix findings with `/resume` on the task file.

## Release

When tested, reviewed (if required), and you **explicitly authorize** the version bump:

```
/release
```

Sonnet 4.6. The agent will not bump versions without your explicit OK in that thread.

## Reference docs

`.agents/docs/` holds WordPress handbooks and style mirrors (~tens of thousands of lines). **You don’t open them.** Agents use them only for targeted lookups (one section or grep). Source code in `inc/` remains the primary truth.

## What you never do

- Copy task templates manually
- Pick files to edit (agent decides from the plan)
- Run lint by hand during dev (agent runs it)
- Keep one thread open for days without `/resume`
- Micromanage implementation steps after you approved the plan

## Quick reference

| Goal | Command |
|------|---------|
| Start any change | `/architect` |
| Continue / rotate thread | `/resume` + task file |
| Integration review | `/reviewer` + task file |
| Security audit | `/security` |
| Ship | `/release` (after authorization) |

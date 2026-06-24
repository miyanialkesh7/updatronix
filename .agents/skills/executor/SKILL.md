---
name: resume
description: >-
  Continue dev work (Claude Sonnet 4.6). Use after thread rotation, turn limits,
  crashes, or fixing review/security findings. Reads the task file and resumes
  from the first unchecked task.
---

# Resume

Continue an `/architect` session without replaying chat history. The task file is the source of truth.

Reply in US English.

## Start

1. Read the referenced `.agents/tasks/YYYY-MM-DD-<type>-<slug>.md` in full
2. Read `## Session checkpoint` first, then `## Tasks`, `## Log`, `## Feedback`
3. Say: "Resuming from task N. Remaining: [list]. Next: [action]."
4. Execute from the first unchecked task using **the same rules as `/architect` Phase 4–5**

## Reference docs

Same policy as architect: **never load whole mirror files**. Grep or read one section only (`AGENTS.md`).

## Plan changes

If remaining tasks are wrong given work already done, update `## Tasks`, note why in `## Log`, tell the user, then continue.

## Thread rotation

After 5 more completed tasks or ~25 turns, update `## Session checkpoint` and suggest a fresh `/resume` thread again.

## Lint & hand-off

Follow architect lint tiers and review hand-off rules (`review_required` in frontmatter).

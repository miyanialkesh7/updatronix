---
name: release
description: >-
  Release cycle (Claude Sonnet 4.6). Version bump, readme.txt, changelog,
  build, Plugin Check, zip. Owner must explicitly authorize the version bump.
---

# Release

End-of-cycle packaging. **Sonnet 4.6.** Do not bump versions without explicit owner authorization in this conversation.

## Prerequisites — stop if any fail

- [ ] Cycle task files complete
- [ ] No open **Blocker** items in review notes for this cycle
- [ ] `npm run test:all` clean
- [ ] Owner explicitly authorized the version bump **in this thread**

## Version sync (lockstep)

| File | Field |
|------|-------|
| `updatronix.php` | `Version:` header + `UPDATRONIX_VERSION` |
| `composer.json` / `package.json` | `version` |
| `readme.txt` | `Stable tag:` |

Confirm the new version with the owner before writing.

## readme.txt

- `Stable tag:` matches `UPDATRONIX_VERSION`
- Update `Tested up to:` if needed
- Promote `== Changelog ==` to `= X.Y.Z =` (plain text bullets, action verbs)
- `== Upgrade Notice ==` only when user action required

Prose style: grep `.agents/docs/wordpress-documentation-style-guide-consolidated.md` if needed — do not load the full file.

## Build order

1. `npm run build`
2. `composer run make:pot` — only if i18n changed this cycle
3. `composer run lint:pcp`
4. `npm run test:all`
5. `npm run zip` → `updatronix.zip`

## Escalation

- `lint:pcp` or tests fail → stop; user runs `/resume` on the fix task
- Security issue found → stop; `/security`
- Never edit `.agents/docs/wordpress-native-updates-reference.md`

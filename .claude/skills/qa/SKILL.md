---
name: qa
description: Run the full QA pipeline of this project and report only the result. Use whenever the checks need to run, e.g. after a change, before a commit proposal, or when asked to "run qa" or "run the checks".
model: haiku
context: fork
---

# Run the checks and summarise

Run these from the project root, in this order:

1. `docker compose exec -T php composer app:test:clear:cache`
2. `make qa`

`make qa` fails fast: the first failing step ends the run. PHP runs only in the container, so never call `php`, `composer` or `vendor/bin/*` on the host.

## If a step fails
- Code style: run `make cs-fix` once, then `make qa` again. Say in the report that files were reformatted and which ones (`git status --short`).
- Anything else: do not change code, tests or configuration. Report it.
- "Container is not running": report that `make up` is needed; do not start or rebuild anything yourself.

## Report
Keep it short; the caller does not see the raw output.
- Green: one line with the number of tests and assertions, and the steps that ran.
- Red: the name of the failing step, the key error lines verbatim (file, line, message), and for tests the failing test names. No full logs, no guesses about the cause.
- Always mention files changed by `make cs-fix`.

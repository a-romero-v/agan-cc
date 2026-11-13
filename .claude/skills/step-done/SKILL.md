---
name: step-done
description: Close a finished step in this project - run the checks, update the docs and propose the commit(s) for approval. Use when a step from docs/plan.md is implemented, or when asked to "wrap up", "finish the step" or "propose the commit".
---

# Close a step

Never commit or push in this skill without the user's explicit approval in this conversation. An earlier approval does not carry over to a new commit.

## 1. Checks
1. Run the `/qa` skill. It clears the test cache (the test environment can hold on to old configuration), runs `make qa` in a separate context on a small model and returns only the summary.
2. If anything is red: stop, show the reported errors and fix the cause. Never weaken a test, a Deptrac rule or the PHPStan level to get it green.
3. If `/qa` reformatted files, include them in the commit they belong to.

## 2. Leftovers
- `git status --short`: no temporary files, spikes or debug code. Files that belong to another step stay out of this commit.
- If a recipe changed `.env`, say so; that file is off limits for reading and editing, so the user has to check it.

## 3. Docs
- `docs/plan.md`: tick the finished item; adjust the plan if the step turned out differently.
- `README.md`: Tech Stack and Setup for new tools or commands, "Design Decisions" for non-obvious choices, "Deviations from the Reference Project" for every deviation.
- `.claude/rules/*.md` and `CLAUDE.md`: replace "verify at scaffold" notes with what was verified; fix anything the step proved wrong.
- Run `/qa` again if a doc change touched anything that is checked.

## 4. Report
Tell the user, briefly and in their language:
- what was built,
- what was verified and how, and what was not,
- findings and decisions made along the way, especially deviations from the agreed plan.

## 5. Propose the commit(s)
For each commit: the list of files and the full message. Rules from `CLAUDE.md`:
- One logical change per commit; each commit leaves `make qa` green.
- Conventional Commits with the context as scope, imperative subject of at most 72 characters, no trailing period.
- Body: what changed and why, the approach, trade-offs, what was taken from or deviates from the reference project.
- Footer `Refs:` with the AC and rule IDs, when the commit implements some.
- No `Co-Authored-By` trailer.
- Everything in English.

## 6. Wait
Commit only after an explicit yes. Then `git add` exactly the listed files, commit, and show the resulting hash and `git status --short`. Push only when asked.

## 7. End of a milestone
If this was the last open item of a milestone in `docs/plan.md`:
- Check that everything the next milestone needs is written down in the repository (decisions, verified facts, open points), not only said in the conversation.
- Tell the user the milestone is complete and ask them to start a new session for the next one. Do not start the next milestone here.

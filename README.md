# dev/

Workspace-level development and AI material. Its own git repository, and nothing
in it is published to Composer.

```
dev/
├── README.md              this file
├── opencode.json          agent config: instructions + permissions
├── .opencode/skills/      add-component, add-route, debug-htmx, verify
├── agents/                the four AGENTS.md files
│   ├── workspace.md       ← ../AGENTS.md        (symlink)
│   ├── principle.md       ← principle/AGENTS.md
│   ├── auth.md            ← auth/AGENTS.md
│   └── app.md             ← app/AGENTS.md
├── planning/
│   ├── CHECKLIST.md       ← principle/CHECKLIST.md
│   └── SPEC.md            ← principle/SPEC.md
└── ideas/                 sketches. Untracked by default; nothing reads them.
```

## Why this exists

It used to be scattered: `AGENTS.md` in three packages and the workspace,
`opencode.json` and four agent skills inside `principle/`, the checklist and the
spec beside them, and an orphaned `ideas/component.php` at the root that no
repository tracked and nothing referenced.

None of it belongs to a package. The framework's published artifact is `src/` and
`docs/`; a consumer's `vendor/` directory has no use for instructions that point
at `tests/RoutingTest.php`. Putting all of it here makes that boundary explicit
instead of relying on `.gitattributes` to keep it out of a tarball.

It is its own repository for the same reason the other three are: this workspace
has no repository at its root, and adding one would tie the packages together in
a way that makes them harder to publish independently.

## The three symlinks, and why they are not optional

```
../AGENTS.md    -> dev/agents/workspace.md
../.opencode/   -> dev/.opencode
../opencode.json -> dev/opencode.json
```

OpenCode discovers instruction files and agent skills by **traversing upward from
the working directory**, stopping at the nearest git worktree:

> walks up from your current directory until it reaches the git worktree

This directory is *above* all three package repositories, and the workspace root
is where sessions start. So a file at `dev/agents/workspace.md` is a **sibling**
of the traversal path, not an ancestor of it — and nothing in `dev/` would ever
be found. Moving the files here without the symlinks would not consolidate them;
it would silently disable them, which is the kind of change that produces no
error and no symptom beyond an agent quietly following worse instructions.

The symlinks are what make a real location and a discovered location the same
file. Every reference below is checked against them.

## Consequences worth knowing

- **A session started inside `principle/`, `auth/` or `app/` no longer reads
  that package's `AGENTS.md`.** Traversal stops at that package's worktree, so
  `../dev/` is never reached. It gets the workspace instructions instead.

  This is arguably an improvement — one canonical instruction set rather than
  four that can drift — but it is a behaviour change, not a no-op. The package
  guidance is one `principle.md` away in `dev/agents/`.

- **`git checkout` in a package will not restore these.** They are gone from
  that repository, and the workspace root is not versioned by anything.

- **Editing `dev/agents/workspace.md` edits `AGENTS.md`.** They are the same
  file, and the workspace root is not covered by any repository's `.gitignore` —
  so be deliberate about it.

## Commands

```bash
cd principle && composer test    # 473 tests, in Docker against PostgreSQL
cd auth      && composer test    # 37 tests, SQLite
cd app       && composer test    # 15 tests, SQLite
```

`dev/` has no build and no test suite. It holds instructions about the code, not
code.

## Rules

- **Plans go in the todo tool**, so they appear in the opencode TUI sidebar.
- **Skills are read with `principle/` as the working directory.** Every path
  inside one needs a `principle/` prefix, because the skill sits two levels below
  the workspace root. This is noted at the top of each.
- **Human-facing docs stay in their packages.** `README.md`, `ARCHITECTURE.md`,
  `CONTRIBUTING.md`, `SECURITY.md` and `docs/` ship to consumers and belong where
  they ship. A package with no README on Packagist is a package nobody uses.
- **Prefer `opencode.json`'s `instructions` over asking an agent to `Read` a
  referenced file.** The config is loaded once; a reference is a hop that can be
  forgotten.
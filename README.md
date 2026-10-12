# dev/

Workspace-level development and AI material. Its own git repository, and nothing
in it is published to Composer.

```
dev/
├── README.md              this file
├── opencode.json          agent config: instructions + permissions
├── .opencode/skills/      add-component, add-route, debug-htmx, verify
├── agents/                the four AGENTS.md files
│   ├── workspace.md       ← was ../AGENTS.md      (now a real file at the root)
│   ├── principle.md       ← was principle/AGENTS.md
│   ├── auth.md            ← was auth/AGENTS.md
│   └── app.md             ← was app/AGENTS.md
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

## Skills need a session started here

There were symlinks at the workspace root — `AGENTS.md`, `.opencode/` and
`opencode.json` — pointing into this directory. They are gone, deliberately: a
symlink across repositories is invisible to `git`, breaks on a fresh clone, and
fails as a dangling pointer rather than an error.

What that costs is specific and worth stating rather than discovering:

OpenCode finds skills by **traversing upward from the working directory**,
stopping at the nearest git worktree:

> walks up from your current directory until it reaches the git worktree

This directory is a *sibling* of the traversal path, not an ancestor of it. So
**start a session here** to get the four skills:

```bash
cd dev && opencode
```

From the workspace root you get `AGENTS.md` — which is a real file there now,
holding the workspace rules — and not the skills. From inside `principle/`,
`auth/` or `app/` you get that package's context and none of the skills either,
because traversal stops at the package's worktree.

The alternative was leaving the symlinks, which work until someone clones.

## Consequences worth knowing

- **The root `AGENTS.md` is a separate file now, not a pointer.** It holds the
  workspace rules and nothing else; this file set holds the per-package guidance.
  They cover the same workspace and neither is wrong, so they *can* drift — the
  root one is the copy to update first, because it is the one read without
  asking.

- **`git checkout` in a package will not restore its `AGENTS.md`.** Those files
  are gone from the three repositories, and the workspace root is covered by no
  repository at all. `dev/` is the only history they have.

- **Published documents link here over the network, not by path.** `principle`
  and `app` both ship markdown that referenced `../dev/…`, which 404s for
  anyone reading the package on Packagist or cloning one repository. Those links
  now point at `github.com/iam-adty/empira-dev`, which is why this repository
  has to be public for them to work at all.

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
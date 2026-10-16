# dev/

Workspace-level development and AI material. Its own git repository, and nothing
in it is published to Composer.

```
dev/
├── README.md              this file
├── opencode.json          agent config: instructions + permissions
├── .opencode/skills/      add-component, add-route, debug-htmx, verify
├── agents/                the five AGENTS.md files
│   ├── workspace.md       ← was ../AGENTS.md      (now a real file at the root)
│   ├── principle.md       ← was principle/AGENTS.md
│   ├── auth.md            ← was auth/AGENTS.md
│   ├── frankenphp.md      ← was frankenphp/AGENTS.md
│   ├── debugtools.md      ← was debugtools/AGENTS.md
│   └── app.md             ← was app/AGENTS.md
├── planning/
│   ├── CHECKLIST.md       ← principle/CHECKLIST.md
│   └── SPEC.md            ← principle/SPEC.md
└── ideas/                 sketches. Untracked by default; nothing reads them.
```

## Why this exists

It used to be scattered: `AGENTS.md` in each package and the workspace,
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

## How a session finds what is in here

There were symlinks at the workspace root — `AGENTS.md`, `.opencode/` and
`opencode.json` — pointing into this directory. They are gone, deliberately: a
symlink across repositories is invisible to `git`, breaks on a fresh clone, and
fails as a dangling pointer rather than an error.

Replacing them took two mechanisms, because OpenCode discovers two different
things by two different rules.

**Instructions — an `opencode.json` in each package.** That file exists in each
of the three repositories, is committed, and names this one:

```json
"instructions": ["../dev/agents/principle.md", "CONTRIBUTING.md", "docs/*.md"]
```

**Skills — `skills`**, in the same file:

```json
"skills": ["../dev/.opencode/skills"]
```

Skills are the reason for a path rather than a directory. OpenCode finds a
`.opencode/skills/` directory by traversing *upward* from the working directory,
stopping at the nearest git worktree — and `principle/`, `auth/` and `app/` are all
worktrees, so a directory in any of them is invisible to a session started there.
`dev/` is a sibling of all three and therefore not on the traversal path at all. A
**path in a config file** has no such constraint: it resolves from wherever the
session started.

Verified rather than assumed, with the two commands that show the resolved state:

```bash
cd principle && opencode debug config    # instructions + skills
cd principle && opencode debug skill     # all four skills, from dev/
```

| Session started in | Instructions | Skills |
|---|---|---|
| `principle/` `auth/` `frankenphp/` `debugtools/` `app/` | that package's agent file + its docs | all four |
| `dev/` | all five agent files + the framework's docs | all four |
| the workspace root | this directory's `AGENTS.md`, as a pointer | none |

The last row is deliberate. There is no root `opencode.json`, because the root is
covered by no repository — a third unversioned file drifting from here would undo
what moving the material achieved. The root `AGENTS.md` points at this directory
instead, which is enough to find everything in it.

**A clone of one package on its own has no `../dev/`.** Both paths then resolve to
nothing, and OpenCode does not complain: zero skills, the package's own
`CONTRIBUTING.md`, and a working `composer test`. Measured, because a silent
degradation is only safe if it is known to be one.

## Consequences worth knowing

- **The root `AGENTS.md` is a pointer, and deliberately small** — 69 lines of
  layout, commands and a table saying which file to read. It used to be a
  139-line second copy of `workspace.md`, and the two had already diverged.
  Anything that belongs to a package lives here instead, so the duplication has
  nothing left to disagree about. It is still unversioned, which is why it opens
  by saying the code wins.

- **`git checkout` in a package will not restore its `AGENTS.md`.** Those files
  are gone from every package repository, and the workspace root is covered by no
  repository at all. `dev/` is the only history they have.

- **Published documents link here over the network, not by path.** `principle`
  and `app` both ship markdown that referenced `../dev/…`, which 404s for
  anyone reading the package on Packagist or cloning one repository. Those links
  now point at `github.com/iam-adty/empira-dev`, which is why this repository
  has to be public for them to work at all.

## Commands

```bash
cd principle && composer test    # 549 tests, SQLite on the host
cd auth      && composer test    # 53 tests, SQLite
cd frankenphp && composer test   # 14 tests, SQLite
cd debugtools && composer test   # 47 tests, SQLite
dev/tools/app-test check         # 26 tests, in the generated app-test/
```

And four checks, none of which need a server running:

```bash
dev/tools/module-contract        # every package shaped like its role
dev/tools/check-links            # links, anchors, and named APIs all exist
dev/tools/check-baselines        # every quoted test count is the count the suites report
dev/tools/check-readme-installs  # every README's install snippet works as written
```

`check-baselines` runs the four library suites, so it needs `composer install` done in
each of them, and it skips `app-test` when the scratch application has not been built.

`dev/` has no build and no test suite. It holds instructions about the code — and
the exception is `dev/tools/`, which is executable on purpose. Those four scripts
are this repository's enforcement: each one makes a rule that used to live in
somebody's memory fail loudly instead. They are deliberately not a package, and
nothing in `dev/` depends on them to run.

`dev/.cache/` holds one of those caches and is gitignored. A cached verdict is
worthless on another machine, and committing one would look like evidence.

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
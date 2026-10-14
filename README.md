# Moodle local_roleexplainer

`local_roleexplainer` is an administrator-facing diagnostic plugin for Moodle that explains **why Moodle granted or
denied a capability** for a user in a specific context.

The plugin deliberately separates two responsibilities:

- Moodle/PHP determines the permission result and builds the evidence tree.
- AI turns that already-computed tree into a human-readable explanation.

The LLM never decides whether a user has a capability and never writes role assignments, role definitions or overrides.

## Installation

Download the **`local_roleexplainer_<version>.zip` release asset** from [GitHub Releases](https://github.com/EduardoKrausME/moodle-local_roleexplainer/releases) and install it through Moodle's **Site administration > Plugins > Install plugins**. The release ZIP has the required `roleexplainer/` root folder.

Do not use the automatic GitHub **Source code (zip)** download for installation: GitHub wraps it in a repository/version folder that Moodle rejects as `rootdirinvalid`. To package a checkout manually, run:

```sh
git archive --format=zip --prefix=roleexplainer/ HEAD -o local_roleexplainer.zip
```

## What is analysed

Given a target user, capability and context ID, the plugin collects and displays:

- system, course-category, course, module/block and other ancestor contexts present in the real context path;
- explicit role assignments affecting that path;
- the authenticated-user and front-page default roles when applicable;
- switched role/runtime role information when it affects the current user's session;
- role archetypes as metadata;
- stored role capability definitions and overrides;
- `CAP_ALLOW`, `CAP_PREVENT`, `CAP_PROHIBIT` and inherited/no-rule states;
- the closest effective rule for each role;
- global `PROHIBIT` behavior;
- aggregation across multiple roles;
- site-admin `doanything` behavior;
- relevant earlier accesslib gates such as risky guest capabilities and context locking;
- the authoritative result from `has_capability()`.

The role trace mirrors Moodle's core semantics: within each role, the closest stored rule wins, except that a `PROHIBIT`
anywhere in any applicable role is absolute; across roles, at least one effective `ALLOW` grants access if no `PROHIBIT`
exists. The displayed final result is still the value returned by Moodle's own `has_capability()` rather than the
plugin's reconstructed trace.

## AI privacy and constraints

Analyses are not persisted by this plugin.

The AI payload does **not** contain the target user's real name, username, email or Moodle user ID. The subject is sent
as `Target user`. The payload contains only what is needed to explain the permission chain, including capability name,
context IDs/levels, role IDs/archetypes, deterministic rules and Moodle's official result.

The prompt explicitly requires the model to:

- treat `official_result` as authoritative;
- never recalculate or contradict Moodle;
- never invent assignments or overrides;
- only suggest a permission change when it can identify an exact role ID and context ID already present in the evidence;
- never make an automatic change.

If AI is unavailable, the deterministic diagnosis is still shown.

## Permission to inspect a context

Opening the admin page requires `local/roleexplainer:use` in the system context. Analysing a user additionally requires at least one of `moodle/role:assign`, `moodle/role:safeoverride`, `moodle/role:override`, or `moodle/role:manage` **in the target context**, matching Moodle's native Check permissions screen. The form rejects unauthorised context IDs, and the analyser independently enforces this check before returning role evidence.

## Native Moodle links

When the current operator has the corresponding Moodle capabilities, the result includes links to the native role
screens:

- `moodle/role:review` → permissions review;
- `moodle/role:assign` → role assignments;
- `moodle/role:override` or `moodle/role:safeoverride` → role override screen.

The plugin itself never changes permissions.

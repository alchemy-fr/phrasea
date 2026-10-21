---
id: 011-creer-attribut-sans-recherche
title: Créer un attribut depuis le sélecteur Attribute sans saisir de recherche
status: executed
finished: 2026-10-21 15:48
started: 2026-10-21 15:45
run_base: 3f55165770aef3659a8853f650f264393f5ee991
created: 2026-10-21 15:44
branch: next
depends_on: []
---

# Create an attribute from the Attribute picker without typing a search term

## Context and goal

In the generated integration configuration form (databox client, commit d83003481), the
**Attribute** field (`AttributePicker`) is an `AsyncCombobox` whose "Create “X”…" entry only
appears **once a term is typed** in the search box (`search.trim()` condition in
`AsyncCombobox`). When the workspace already has an attribute (for example "Sizes"), the user
opens the list, sees only the existing attributes and has no visible way to create a new one.

Goal: in the Attribute picker, always offer a creation entry, even when the search is empty.
It opens `CreateAttributeDialog` with the default name (empty, or "Faces" for an entity
attribute; the existing behaviour of `initialName || …`).

## Decisions made

- **Opt-in** behaviour in `AsyncCombobox`: the other `onCreate` users (`FaceCard`,
  `PersonGroup`, the entity select in `components/form/selects.tsx`) create a value *from* the
  typed text, so an empty creation makes no sense for them. They keep their current behaviour.
- Label when the search is empty: "Create an attribute…" / "Créer un attribut…". With a typed
  term, the existing label "Create “{{name}}”…" stays.
- The "hide the creation entry when a label matches the query exactly" rule stays for a
  non-empty query.

## Files involved

- `databox/client/src/components/form/AsyncCombobox.tsx`
  - New prop in `BaseProps`: `createWithoutQuery?: boolean` (JSDoc: offers the creation entry
    even with an empty search; `onCreate` then receives `''`).
  - `create()`: no longer returns early when `search.trim()` is empty if `createWithoutQuery` is set.
  - Render condition of the creation `CommandItem`:
    `props.onCreate && (search.trim() ? !options.data?.some(exact match) : props.createWithoutQuery)`.
    Unique cmdk `value` for the empty case (for example `__create_`), which already holds.
  - The empty-search creation entry stays visible even while loading (it does not depend on the
    results). Note: with `shouldFilter={false}`, cmdk counts the creation entry,
    so "No match" no longer shows when it is the only entry, which is the expected result.
- `databox/client/src/features/integrations/config/pickers/AttributePicker.tsx`
  - Pass `createWithoutQuery: true` in `shared`.
  - `createLabel: q => q ? t('integration.attribute_picker.create', …) : t('integration.attribute_picker.create_new', 'Create an attribute…')`.
  - `create(name)`: pass `initialName: name || undefined` (the dialog already falls back to its
    default name).
- `databox/client/src/i18n/locales/en.json` / `fr.json`: add
  `integration.attribute_picker.create_new` by hand ("Create an attribute…" /
  "Créer un attribut…"), next to `integration.attribute_picker.create` (the i18next scanner
  crashes, so do not run `pnpm translate`).
- `databox/client/src/components/form/AsyncCombobox.test.tsx` (new, Vitest + Testing Library,
  same setup as `components/form/CollectionTreePicker.test.tsx`: QueryClientProvider, i18n):
  - without `createWithoutQuery`: popover open, empty search → no creation entry; typed term
    → "Create "x"" entry;
  - with `createWithoutQuery`: empty search → entry present, a click calls `onCreate('')`, and
    the returned option is selected (`onChange` called with its value).
- `cypress/cypress/e2e/databox/17-integrations.cy.js`: unchanged (the scenario
  `sets up face recognition from the form` goes through a typed term, which still works; the
  empty case is covered by Vitest).

## Steps

1. Add `createWithoutQuery` to `AsyncCombobox` (prop, `create()`, render condition).
2. Wire `AttributePicker` (prop, conditional label, `initialName`).
3. Add the i18n key in `en.json` and `fr.json`.
4. Write `AsyncCombobox.test.tsx`.
5. Lint and format the touched files.

## Verification

Runs in the `dev` container (no tool on the host; see the TTY memory:
`dc run --rm --entrypoint sh dev -c "su app -c '…'"` if `dc run --rm dev …` exits 0 without
running anything; the path in the container is `/var/workspace/<worktree>`):

```bash
dc run --rm dev pnpm --filter databox-client test -- src/components/form/AsyncCombobox.test.tsx
dc run --rm dev pnpm --filter databox-client test -- src/features/integrations
dc run --rm dev pnpm --filter databox-client lint
```

Typecheck: `tsc --noEmit` in the client and filter the ~15 known pre-existing errors (duplicate
`@types/react`); no new error should touch the modified files.

Manual check (needs the shared docker stack and can only be done after integration):
Workspace → Manage → Integrations → Face Recognition (or another integration with an attribute
field) → Form mode → open "Attribute" without typing: the "Create an attribute…" entry is
visible; clicking it opens the dialog with the default name; once created, the attribute is
selected. Check that the person select in the faces UI (`FaceCard`) still offers no creation
when the search is empty.

## Out of scope

- Other `AsyncCombobox` users (faces, entity select): unchanged.
- `RenditionPicker` / `CollectionPicker`: no creation added.
- Changes to `CreateAttributeDialog`.

## Interactions with the queue

The only plan in `plans/` (`008-run-automation-from-saved-search-menu`) has status `failed`
(archived); no pending plan touches these files. `depends_on: []`.

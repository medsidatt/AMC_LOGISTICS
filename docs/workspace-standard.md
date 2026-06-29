# Platform Workspace Standard

**This is the platform-wide reference**, not just Administration. Reference
implementation: **Roles** (`resources/js/pages/roles/`). Every workspace — current
and future — follows these conventions unless there is a justified business reason
not to. The goal is one coherent application: same workspace behaviour, same drawer
lifecycle, same URL state, same component library, same interaction patterns.

## 0. Workspace vs dedicated page — and navigation
- **CRUD lives in a workspace.** Listing, create, edit, details, delete all happen
  on one Index page with drawers (sections 1–8 below). This is the default.
- **A dedicated page exists only when the workflow genuinely requires one** —
  analytics, dashboards, complex visualization, multi-entity assignment workflows,
  reports, geographic/map UX, or compliance read-views. A page is justified by the
  *workflow*, never by "a module exists."
- **Placement:** CRUD → workspace · details → Details Drawer · editing → Form
  Drawer · browser history must reflect drawer state.
- **Navigation represents business workflows, not CRUD screens.** Do not add a
  sidebar entry just because a module exists. Master-data reached contextually
  (e.g. Providers/Transporters/Entities) need not be in the sidebar.
- **Duplication:** in every phase, watch for duplicate controllers / forms /
  validation / DataTable configs / drawers / detail layouts. Extract a shared piece
  only when duplication is *proven* — avoid premature abstraction.

## 1. One workspace page, drawers for everything
- A single Index page (`<module>/Index.tsx`) renders `PageHeader` + `DataTable`
  (+ `Pagination`) and hosts the drawers. No Create/Edit/Show pages.
- Create/edit live in one **Form Drawer**; read lives in one **Details Drawer**.
- Delete is a `ConfirmDialog` (DELETE), never a drawer.

## 2. URL-driven drawer state (deep-linkable, refresh-safe)
- State is derived from the URL query, not just local state:
  `/<module>` · `?create=1` · `?view={id}` · `?edit={id}`.
- `parseDrawer(search)` → discriminated union `{kind:'none'|'create'|'view'|'edit', id?}`.
- Navigation uses the **History API only** (`window.history.pushState/replaceState`,
  reusing `window.history.state` so Inertia's own history restore keeps working) —
  **no server request** when opening/closing a drawer.
- A `popstate` listener re-derives state so browser Back/Forward moves between
  drawer states. Initial `useState(() => window.location.search)` handles
  deep-links and full-page refresh (the server route ignores the query and always
  renders Index).
- Save completions use `replaceState` (no duplicate history entry); user-initiated
  open/close use `pushState`.

## 3. One drawer at a time
Render is mutually exclusive (create XOR view XOR edit) — never stacked, never two
overlays. Allowed transitions: List → Details → Edit → back to Details → Close.

## 4. All drawer data ships in the index payload
The `index` controller returns the list **plus** everything the drawers need
(e.g. Roles ships all permissions + permission meta). Drawers operate entirely
from client state — **zero per-drawer fetches**. Only viable because Admin
master-data sets are small; for large sets, fall back to fetch-on-open.

## 5. Details Drawer presentation (reference)
`DetailPanel`/`DetailItem` summary tiles at top, then grouped sections. Footer
holds the `Modifier` action (permission-gated) which transitions to the Edit
drawer via the URL.

## 6. Form Drawer
`useForm` initialised from the row (edit) or blanks (create); `FormActions` footer;
posts to the existing `store`/`update` endpoints (`redirect()->back()`). Business
components (e.g. `PermissionMatrix`) are embedded **unchanged** — only the
container moved. `onSaved` navigates to the result state (create → list,
edit → details) with `replaceState`.

## 7. List actions
`ActionButtons` with **callbacks** (`onView`/`onEdit` → `navigate(...)`,
`onDelete` → set `ConfirmDialog` url), never `viewHref`/`editHref` navigation.

## 8. Backend shape
`index` (list + drawer refs) · `store` · `update` · `destroy`, all returning
`redirect()->back()`. No `create`/`edit`/`show` routes. Remove dead routes,
dead controller methods, and dead Blade views during the conversion.

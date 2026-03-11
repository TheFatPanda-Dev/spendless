# GitHub Copilot Instructions — Architecture & Solutions

You are coding in a monorepo-style Laravel application using:

- Laravel 12.x (PHP) backend
- Inertia.js 2.x bridging backend + frontend
- React 19 + TypeScript 5.x frontend
- Vite 5.x build tool
- Tailwind CSS 4.x styling
- shadcn/ui & radix-ui components

---

## 1) High-level architecture

### 1.1 The “Inertia way”

- Use **Laravel routes + controllers** as the primary entry points.
- Controllers return **Inertia pages** (not JSON APIs) unless explicitly requested.
- Prefer SSR-like page composition:
    - Backend: fetch/prepare data, authorize, validate.
    - Frontend: render UI, local interactions, optimistic UX.

### 1.2 Boundaries and responsibilities

- **Laravel** owns:
    - Validation (Form Requests)
    - Authorization (Policies/Gates)
    - Business rules (Services/Actions)
    - Persistence (Eloquent, transactions)
    - Server-side pagination/filtering
- **React** owns:
    - UI composition and interaction state
    - Form UX (client-side niceties, but server is source of truth)
    - Component abstractions and view-specific logic

### 1.3 Prefer “thin controllers”

- Controllers should:
    - authorize
    - validate
    - call an Action/Service
    - return Inertia response/redirect
    - Domain logic is organized under `app/Actions` and `app/Models` as follows:
    ```
    app/
        Actions/
            Accounts/
                CreateAccount.php
                UpdateAccount.php
            Categories/
                CreateCategory.php
                UpdateCategory.php
            Money/
                Money.php
                FxRate.php (optional)
            Ledger/
                Actions/
                    CreateTransaction.php
                    UpdateTransaction.php
                    CreateTransfer.php
                Rules/
                    EnsureTransferAccountsDiffer.php
                DTO/
                    TransactionData.php
            Budgeting/
                Actions/
                    SetBudgetAllocation.php
                    RecalculateBudget.php
                ValueObjects/
                    BudgetPeriod.php
                Services/
                    BudgetCalculator.php
        Models/
            Transaction.php
            Account.php
            Category.php
            BudgetAllocation.php
    ```

    - Use explicit method names, small units, testable.

---

## 2) Backend (Laravel) conventions

### 2.1 Routing

- Prefer resourceful routing (`Route::resource`) where appropriate.
- Use route model binding.
- Use named routes consistently (for Inertia navigation).

### 2.2 Validation

- Use **Form Request** classes for any non-trivial validation.
- Keep rules explicit. Prefer:
    - `Rule::unique()`, `Rule::exists()`
    - `sometimes` for PATCH
- Validation errors must flow naturally back to Inertia forms.

### 2.3 Authorization

- Use Policies (`php artisan make:policy`) for models.
- Call `$this->authorize()` in controllers (or `authorizeResource`).
- Never rely on frontend-only role checks.

### 2.4 Data shaping for frontend

- Do not return raw Eloquent models with hidden complexity.
- Prefer:
    - Laravel API Resources (`App\Http\Resources\...`) OR
    - Dedicated DTO arrays in controller
- Keep payloads minimal and explicit:
    - Only send fields needed by the page.
    - Prefer `->select([...])`, `->with([...])`, avoid N+1.

### 2.5 Queries

- Prefer query objects/scopes for reusable filters:
    - `Model::query()->filter($filters)`
- Paginate on server:
    - `->paginate(20)->withQueryString()`

### 2.6 Transactions & side effects

- Wrap multi-write operations in `DB::transaction`.
- Emit events for significant domain changes when useful.
- Use queues for slow tasks (mail, exports, heavy processing).

### 2.7 Error handling and UX

- Prefer redirect back with flash messages:
    - `->with('success', '...')`
- Use proper HTTP codes (404, 403).
- Use consistent exception patterns; don’t swallow exceptions silently.

---

## 3) Frontend (React 19 + Inertia + TypeScript)

### 3.1 File structure expectations

Use a predictable structure like:

- `resources/js/Pages/**` — Inertia page components
- `resources/js/Components/**` — reusable UI components
- `resources/js/Layouts/**` — app layouts
- `resources/js/lib/**` — utilities (formatting, helpers)
- `resources/js/types/**` — shared TS types

Do not put heavy business logic in Pages; keep them composition-focused.

### 3.2 TypeScript rules

- Always type props, payloads, and hook returns.
- Prefer `type` for simple objects; `interface` for extendable public shapes.
- No `any` unless absolutely necessary; prefer `unknown` + narrowing.
- Create shared types for page props and common entities.

### 3.3 Inertia patterns

- Use `@inertiajs/react`:
    - `router` for navigation and actions
    - `useForm` for forms
    - `usePage` for page props
- Prefer `router.get()` with `preserveState`/`preserveScroll` for filters/search.
- Use partial reloads (`only`) when it meaningfully reduces payload.
- Handle flash messages from props; show toasts or alerts.

### 3.4 Forms

- Use `useForm<T>()` with typed form data.
- Prefer server validation; show errors inline:
    - `errors.fieldName`
- Use `processing` to disable submit.
- For destructive actions, confirm via dialog.

### 3.5 State management

- Prefer local state (`useState`, `useReducer`) and server state via Inertia props.
- Do not introduce global state libraries unless asked.
- For derived UI state, prefer memoization (`useMemo`) only when necessary.

---

## 4) UI: Tailwind + shadcn/ui + radix-ui

### 4.1 Styling rules (Tailwind)

- Use Tailwind utilities for layout/spacing/typography.
- Avoid custom CSS unless necessary; if needed, keep it minimal and scoped.
- Prefer consistent spacing scale and responsive utilities (`sm:`, `md:` etc).
- Ensure dark mode compatibility if the project supports it.

### 4.2 shadcn/ui usage

- Prefer shadcn components for standard UI: Button, Input, Dialog, Dropdown, Table, Tabs, Toast.
- Follow the shadcn composition style (small, composable building blocks).
- Keep variants consistent; do not invent new patterns without reason.

### 4.3 radix-ui accessibility

- Use radix primitives when you need advanced accessibility behavior.
- Ensure:
    - proper labels (`<Label htmlFor=...>`)
    - keyboard navigation works
    - focus states remain visible
- Never remove ARIA attributes for “cleanliness”.

### 4.4 Icons

- Use Lucide React Icons
- General Implementation:
Tree Shaking: Always use named imports from lucide-react (e.g., import { Camera } from 'lucide-react') rather than importing the entire library to ensure proper tree shaking.

Naming Convention: Follow PascalCase for icon components as per standard React patterns.

- Styling and Props:
Default Props: When generating icons, use the standard Lucide props: size, color, and strokeWidth.

Accessibility: Always ensure icons used as buttons or links have an aria-label or are wrapped in a component that provides context for screen readers.

- Advanced Patterns:
Dynamic Rendering: If icons need to be rendered dynamically from a string key, use the LucideIcon type for TypeScript and advise on a mapping object rather than using eval or heavy dynamic imports.

Consistency: Default to a strokeWidth of 2 unless the existing UI uses a thinner, more modern aesthetic (like 1.5).

---

## 5) Vite and module boundaries

- Use Vite import aliases if configured (e.g., `@/Components/...`).
- Avoid deep relative imports (`../../../../`).
- Keep bundles lean:
    - don’t import huge libraries for small tasks
    - prefer native APIs and lightweight utils

---

## 6) Data contracts between Laravel and React

### 6.1 Single source of truth

- Backend is authoritative for:
    - permissions
    - validation rules (at least final enforcement)
    - computed fields that require business logic

### 6.2 Serializable props only

- Inertia props must be JSON-serializable.
- Convert dates to ISO strings, or send formatted strings plus raw timestamps when needed.

### 6.3 Naming

- Use consistent naming across backend and frontend:
    - snake_case from PHP can be mapped, but prefer consistent shapes per project convention.
- Prefer explicit prop names like `users`, `filters`, `pagination`, `can`.

---

## 7) Security and correctness (non-negotiable)

- Always consider:
    - authorization checks
    - mass assignment (`$fillable` / guarded)
    - validation on writes
    - escaping / XSS safety (don’t dangerouslySetInnerHTML unless required)
    - CSRF protection (default Laravel setup)
- For user input displayed in UI, assume it may contain malicious content.

---

## 8) Testing mindset (when producing changes)

- For backend:
    - suggest feature tests for key flows
    - unit test domain actions when complex
- For frontend:
    - keep logic in small functions/components for testability
    - avoid giant components with mixed concerns

---

## 9) When generating code, prefer these solution patterns

### 9.1 CRUD pattern

- Laravel resource controller + Policy + Form Requests
- Inertia Pages:
    - `Index.tsx` (table + filters + pagination)
    - `Create.tsx` / `Edit.tsx` (form)
    - `Show.tsx` (details)
- Reusable `Form` component used by Create/Edit.

### 9.2 Tables & filtering

- Filtering done via query string:
    - `router.get(route('...'), { search, status }, { preserveState: true, replace: true })`
- Backend applies filters and paginates with `withQueryString()`.
- Use debounced search where helpful.

### 9.3 Notifications

- Backend sets flash (`success`, `error`).
- Frontend reads flash and displays toast/alert.

### 9.4 Permissions

- Backend sends a `can` object (e.g., `{ create: true, update: false }`)
- Frontend uses it to hide/disable controls, but backend still enforces via Policy.

---

## 10) Output quality requirements for Copilot

When you propose or generate code:

- Keep it consistent with existing conventions.
- Prefer readable, maintainable code over cleverness.
- Include relevant imports and correct paths.
- Avoid introducing new dependencies unless requested.
- Provide complete, working snippets (not pseudo-code) whenever possible.

Follow these instructions strictly when generating code, refactors, or suggestions.

---

## 11) Design requirement

- All UI work must be mobile friendly by default (responsive layouts, touch-friendly targets, and readable typography on small screens).
- Interfaces should look modern and clean while staying consistent with the existing design system (Tailwind + shadcn/ui + radix-ui).
- Prefer simple, polished layouts with clear hierarchy, spacing, and accessible interaction states across screen sizes.

---

## Developer Experience Note

The primary developer is at a junior-mid level. All code, patterns, and solutions should provide the features and structure described above, but must remain clear, approachable, and not overly complex. Prioritize readability and maintainability so the codebase is understandable at this level.

---

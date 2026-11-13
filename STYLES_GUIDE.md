# ECW HRMIS — UI Styles Guide

This guide documents the layout system, design tokens, and component patterns used throughout this application. Follow these patterns when building or modifying any page.

Brand name in the shell is **ECW HRMIS**. Visual tone follows the legacy ECW HRM product (navy shell, cyan accent) while staying on Tailwind / Livewire — not Bootstrap.

---

## Design Tokens

All tokens are defined in `resources/css/app.css` inside `@theme { }`.

| Token | Value | Usage |
|---|---|---|
| `--spacing-navbar` | `2.5rem` (40px) | Height of the fixed top navbar |
| `--spacing-subnav` | `2.25rem` (36px) | Reserved for future sub-navigation |
| `--color-erp-navbar` | `#0f2744` | Dark navy navbar background |
| `--color-erp-row-hover` | `#eef6fc` | Editable table cell hover fill |
| `--color-erp-accent` | `#0088cc` | Primary accent / buttons |
| `--color-erp-accent-soft` | `#e6f5fc` | Soft accent backgrounds |
| `--color-erp-cyan` | `#00aaff` | Secondary highlight (legacy header cyan) |
| `--radius-popup` | `0.25rem` (4px) | Corner radius for all dropdowns and menus |
| `--color-erp-border` | `#d1d5db` | Default border color (gray-300); applied globally |

**Utility classes:**

| Class | What it does |
|---|---|
| `.popup` | Applies `border-radius: var(--radius-popup)` — use on every dropdown/modal |
| `.table-compact` | 28 px rows, 13 px text — use on dense data tables |
| `.cell-editable` | Pointer cursor + ERP row-hover on hover — editable table cells |
| `.cell-input` | Borderless full-width input inside an editable table cell |

---

## Layout System

The app uses a two-level layout hierarchy.

### Level 1 — Base shell: `x-layouts.app`

File: `resources/views/components/layouts/app.blade.php`

This renders the full HTML document, fixed top navbar, and the page body slot.

**Props:**
- `showNav` (bool, default `true`) — show or hide the navbar
- `lightNav` (bool, default `false`) — use white/light navbar instead of dark

**When to use directly:** Only for special pages that don't belong to a module (e.g., the home/launcher screen, login page via `x-layouts.guest`).

### Level 2 — Module wrapper: `x-layouts.module`

File: `resources/views/components/layouts/module.blade.php`

Wraps `x-layouts.app` with `lightNav=true` and injects a module-specific nav into the top bar.

**Props:**
- `moduleName` (string) — e.g. `'Procurement'`, shown in the browser title
- `title` (string, optional) — page title appended after module name with `·`
- `moduleNav` (array) — navigation items for this module (see Module Nav section)

**How to wire it from a Livewire component:**

```php
public function render(): View
{
    return view('inventory::livewire.procurement.purchase-request-list', [
        'prs' => $prs,
    ])->layout('components.layouts.module', [
        'moduleName' => 'Procurement',
        'moduleNav'  => $this->moduleNav,
    ]);
}
```

Build `$this->moduleNav` in `boot()` — not `mount()` — so it rebuilds on every request:

```php
public function boot(): void
{
    $router = app('router');
    $this->moduleNav = [
        ['icon' => '📋', 'label' => 'Purchase Requests', 'route' => route('procurement.purchase-requests.index'), 'active' => true],
        ['icon' => '📄', 'label' => 'Purchase Orders',   'route' => $router->has('procurement.purchase-orders.index') ? route('procurement.purchase-orders.index') : '#'],
        // ... more items
    ];
}
```

**Nav item shape:**

```php
[
    'icon'   => '📋',           // emoji or null
    'label'  => 'Purchase Requests',
    'route'  => route('...'),   // resolved URL string, or '#' if not active
    'active' => true,           // highlights this item in indigo
    // Optional: dropdown children
    'children' => [
        ['label' => 'Sub Item', 'route' => route('...')],
    ],
]
```

---

## Page Structure

Every page content view (the Livewire component template) follows this structure:

```
[Flash messages]
[Toolbar bar]
[Section card(s)]
[Pagination — list pages only]
[Bottom action bar — form pages only]
```

---

## Flash Messages

Always place these at the very top of the component view:

```blade
@if(session('success'))
    <div class="mb-4 rounded bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded bg-red-100 px-4 py-2 text-sm text-red-700">{{ session('error') }}</div>
@endif
```

---

## Toolbar Bar

The horizontal strip of filters and actions at the top of every page.

```blade
<div class="mb-4 flex flex-wrap items-center gap-3 p-2">
    {{-- filters / search inputs on the left --}}
    <input type="text" wire:model.live="search" placeholder="Search…"
        class="rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />

    {{-- primary action pushed to the right --}}
    <a href="{{ route('...create') }}" class="ml-auto rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
        + New Record
    </a>
</div>
```

For reports, use `items-end` instead of `items-center` (aligns labels) and add a white card background:

```blade
<div class="mb-4 flex flex-wrap items-end gap-3 p-2 bg-white rounded shadow">
```

---

## Section Cards

Wrap all data or form content in a white card with shadow:

```blade
<div class="rounded bg-white shadow">
    ...
</div>
```

For form cards with internal padding:

```blade
<div class="mb-4 rounded bg-white p-5 shadow">
    <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Section Title</h3>
    ...
</div>
```

For cards with a header bar (title + action button):

```blade
<div class="rounded bg-white shadow">
    <div class="flex items-center justify-between border-b px-5 py-3">
        <h3 class="text-sm font-semibold text-gray-700">Section Title</h3>
        <button class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
            + Add Line
        </button>
    </div>
    ...
</div>
```

---

## Tables

### Standard list table

```blade
<div class="overflow-x-auto rounded bg-white shadow">
    <table class="w-full text-sm">
        <thead class="border-b bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Column</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($items as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-xs text-gray-600">{{ $item->name }}</td>
                    <td class="px-3 py-2 text-right text-xs">{{ number_format($item->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-3 py-6 text-center text-sm text-gray-500">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

**Cell alignment rules:**
- Text / labels: `text-left` (default, can omit)
- Numbers / amounts: `text-right`
- Actions: `text-center`

**Mono font for codes/IDs:** `font-mono text-xs`

### Dense table (master data, inline forms)

Add `table-compact` on the `<table>` element. Rows become ~28 px tall, text 13 px.

```blade
<table class="w-full table-compact text-sm">
```

### Report table — grouped rows

```blade
{{-- Group header --}}
<tr class="bg-blue-50">
    <td colspan="N" class="px-3 py-2 text-xs font-semibold text-blue-800">Group Label</td>
</tr>

{{-- Sub-group header (nested) --}}
<tr class="bg-gray-50">
    <td colspan="N" class="px-3 py-1.5 pl-6 text-xs font-medium text-gray-700">Sub-group</td>
</tr>

{{-- Data rows (indented under sub-group) --}}
<tr class="hover:bg-gray-50">
    <td class="px-3 py-1.5 pl-9 font-mono text-xs">...</td>
</tr>

{{-- Sub-group subtotal --}}
<tr class="bg-gray-100">
    <td colspan="N-3" class="px-3 py-1.5 text-right text-xs font-medium text-gray-600">Subtotal:</td>
    ...
</tr>

{{-- Group total --}}
<tr class="bg-blue-100">
    <td colspan="N-3" class="px-3 py-2 text-right text-xs font-semibold text-blue-800">Group Total:</td>
    ...
</tr>

{{-- Grand total --}}
<tr class="bg-gray-800 text-white">
    <td colspan="N-3" class="px-3 py-2 text-right text-xs font-bold">Grand Total:</td>
    ...
</tr>
```

---

## Form Controls

### Label

```blade
<label class="mb-1 block text-xs font-medium text-gray-600">
    Field Name <span class="text-red-500">*</span>
</label>
```

### Text input / date / number

```blade
<input wire:model="fieldName" type="text"
    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500
           @error('fieldName') border-red-400 @enderror" />
@error('fieldName')
    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
@enderror
```

### Select

```blade
<select wire:model="fieldName"
    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500
           @error('fieldName') border-red-400 @enderror">
    <option value="">— Select —</option>
    @foreach($options as $opt)
        <option value="{{ $opt->id }}">{{ $opt->name }}</option>
    @endforeach
</select>
@error('fieldName')
    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
@enderror
```

### Textarea

```blade
<textarea wire:model="fieldName" rows="2" maxlength="1000"
    class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500
           @error('fieldName') border-red-400 @enderror"></textarea>
```

### Read-only display (view mode)

```blade
<div class="rounded border bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
    {{ $value ?: '—' }}
</div>
```

### Form grid

```blade
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
    <div> ... field ... </div>
    <div class="md:col-span-2"> ... wide field ... </div>
</div>
```

---

## Buttons

### Primary (solid)

```blade
<button type="button" class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
    Save
</button>
```

### Secondary (outline neutral)

```blade
<a href="{{ route('...index') }}" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
    Cancel
</a>
```

### Outline primary

```blade
<button type="button" class="rounded border border-blue-600 px-4 py-1.5 text-sm text-blue-600 hover:bg-blue-50 disabled:opacity-50">
    Save as Draft
</button>
```

### Danger (inline, text only)

```blade
<button type="button" class="text-xs text-red-600 hover:text-red-800">Delete</button>
```

### Success / export

```blade
<button type="button" class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700">
    Export CSV
</button>
```

### Livewire loading state

Always add `wire:loading.attr="disabled"` to any button that triggers a server action:

```blade
<button wire:click="save" wire:loading.attr="disabled" wire:target="save"
    class="... disabled:opacity-50">
    Save
</button>
```

---

## Status Badges

```blade
@php
    $badgeClass = match($record->status) {
        'DRAFT'            => 'bg-gray-100 text-gray-700',
        'PENDING_APPROVAL' => 'bg-yellow-100 text-yellow-700',
        'APPROVED'         => 'bg-green-100 text-green-700',
        'REJECTED'         => 'bg-red-100 text-red-700',
        'ORDERED'          => 'bg-blue-100 text-blue-700',
        'CANCELLED'        => 'bg-gray-100 text-gray-400',
        default            => 'bg-gray-100 text-gray-600',
    };
@endphp
<span class="rounded px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">{{ $record->status }}</span>
```

---

## Dropdowns and Popups

All popups use Alpine.js and the `.popup` utility class. Follow this pattern exactly:

```blade
<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" @click.outside="open = false">
        Toggle
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute left-0 top-full z-50 mt-1 w-48 popup border bg-white py-1 shadow-lg"
        style="display: none;"
    >
        {{-- menu items --}}
    </div>
</div>
```

Notes:
- Always add `style="display: none;"` alongside `x-show` to prevent the flash-of-content on page load.
- Always add `z-50` to ensure it sits above table rows and other content.
- Use `popup` class (not raw `rounded`) to get the correct 4 px radius.

---

## Pagination

Placed below the table card, not inside it:

```blade
<div class="mt-4">
    {{ $records->links() }}
</div>
```

Requires `use Livewire\WithPagination;` in the component and `->paginate(25)` in the query.
Call `$this->resetPage()` in `updating*()` hooks when filters change.

---

## Back Navigation / Breadcrumb

Form and detail pages include a back link in the toolbar bar:

```blade
<div class="mb-4 flex flex-wrap items-center gap-3 p-2">
    <a href="{{ route('...index') }}" class="flex items-center gap-1 text-sm text-blue-600 hover:underline">
        &larr; Records
    </a>
    <span class="text-sm text-gray-400">|</span>
    <span class="text-sm font-medium text-gray-700">New Record</span>
    {{-- optional status badge --}}
    <div class="ml-auto flex gap-2">
        {{-- action buttons --}}
    </div>
</div>
```

---

## Module Launcher (Home Screen)

The home screen (`resources/views/livewire/home.blade.php`) uses `<x-module-launcher />`.

The launcher component (`resources/views/components/module-launcher.blade.php`) renders a grid of module icons. It accepts a `compact` prop:

- Default (no prop): 6-column grid, 60 px icons — for the full home screen
- `compact`: 3-column grid, 48 px icons — for the dropdown in the top navbar

Module entries are defined as a plain PHP array in the component file. Add new modules there.

---

## Typography Summary

| Use case | Classes |
|---|---|
| Section heading | `text-sm font-semibold text-gray-700` |
| Column headers | `text-xs font-semibold text-gray-600` |
| Body text (normal) | `text-sm text-gray-700` |
| Body text (muted) | `text-xs text-gray-600` |
| Very muted / hint | `text-[11px] text-gray-400` |
| Code / document numbers | `font-mono text-xs` |
| Right-aligned numbers | `text-right text-xs` |
| Required asterisk | `text-red-500` |
| Error messages | `text-xs text-red-500` |

---

## Dos and Don'ts

**Do:**
- Always use `popup` instead of `rounded` on dropdowns and menus.
- Always use the layout wiring in `render()` — never embed `<x-layouts.module>` inside a component view.
- Always use `wire:loading.attr="disabled"` with `disabled:opacity-50` on action buttons.
- Use `font-mono text-xs` for all document numbers, item codes, and reference IDs.
- Keep toolbar controls at `text-sm` and table data at `text-xs`.

**Don't:**
- Don't add a second navbar or sidebar — module navigation lives in the top bar only.
- Don't use `DB::` directly; use Eloquent (`Model::query()`).
- Don't create custom `border-color` values — the global default `--color-erp-border` is applied to all borders automatically.
- Don't hardcode navbar height — always reference `var(--spacing-navbar)` or `h-[var(--spacing-navbar)]`.
- Don't use `rounded-lg` or `rounded-xl` on popups and menus — only `.popup` (4 px).

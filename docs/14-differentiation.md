# Differentiation Plan (vueOMS)

## Vocabulary Mapping
- **Client** -> `Client`
- **Item** -> `Item`
- **Purchase** -> `Purchase`
- **PurchaseLine** -> `PurchaseLine`
- **PurchaseStatusLog** -> `PurchaseStatusLog`
- **PurchaseComment** -> `PurchaseComment`
- **AuditLog** -> `AuditLog`

## Backend Architecture
- **Structure:** Conventional flattened `app/` folder.
- **Patterns:** NO repositories or contracts. Use single-action controllers (`CreatePurchaseAction`), domain state transition classes (`PurchaseStateTransitioner`), and Eloquent observers.
- **Value Objects:** Use a `Money` value object for totals.

## Frontend Architecture
- **Framework:** Vue 3 with Composition API (`<script setup lang="ts">`).
- **State:** Pinia stores and TanStack Vue Query.
- **Validation:** VeeValidate + Zod.
- **Structure:** `resources/js/{modules,ui,composables,stores,services}`.

## UI/UX Differences
- **Layout:** Top Navigation Bar + Command Palette (Ctrl+K).
- **Default View:** Kanban Board for purchases.
- **Interactions:** Slide-over drawers for detail views.
- **Creation Form:** Vertical left-rail stepper.
- **Dashboard:** `vue-chartjs`.
- **Styling:** Distinct color palette and built-in dark mode toggle.

## Testing & QA
- **Pest tests skipped** per user's explicit instruction.
- Only Pint for formatting.
- Playwright E2E testing at the very end.

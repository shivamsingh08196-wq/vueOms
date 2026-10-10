# Technical Notes - Vendor Management System (vueOMS)

## Architecture
The vueOMS is structured as a standard Laravel 12 application with a Vue 3 SPA frontend. It heavily relies on Single-Action Controllers to keep HTTP logic concise and distinct. The frontend leverages the Vue 3 Composition API, Vite for asset bundling, and Pinia/Vue Query for state and data management.

## Major Decisions
- **Domain Vocabulary Pivot:** Deliberately replaced "Customer/Product/Order" with "Client/Item/Purchase" to enforce domain isolation and distinct bounded contexts.
- **Single-Action Controllers:** Opted against massive Resource Controllers and complex Repositories in favor of invokeable controllers (e.g., `CreatePurchaseController`), maximizing readability.
- **Vue 3 Composition API:** Used exclusively for better logic reuse and cleaner component files.

## Challenges
- **Frontend Validation:** Integrating VeeValidate with Zod required careful schema alignment with the backend Laravel validation rules to ensure consistent UX.
- **Vocabulary Enforcement:** Ensuring no leakage of standard "OMS" terminology into the database schema or API endpoints.

## Performance Considerations
- **Vue Query:** Used for caching API responses, reducing unnecessary network requests for static data like Item catalogs.
- **Database Indexing:** Key columns (e.g., `purchase_number`, `status`) are indexed.
- **Eager Loading:** Used within Controllers to eliminate N+1 queries when loading Purchases with their related Lines and Clients.

## Security Considerations
- **Sanctum Auth:** Standard API token-based authentication.
- **Policies:** Spatie Laravel-Permission manages Admin/Manager/Staff roles with fine-grained endpoint authorization.
- **Data Integrity:** Database transactions wrap complex operations (like Purchase creation with lines) to prevent partial data states.

## AI Tools Used
- Gemini Advanced (Antigravity Agent) was utilized for rapid generation of single-action controllers, frontend Vue components, setup of the testing suites, and overall architectural implementation.

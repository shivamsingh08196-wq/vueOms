---
name: db-performance
description: Use when writing queries, list endpoints, search/filter/sort, dashboard aggregates, indexes or seeders in the VMS, with 1M+ purchases in mind.
---

# Database and performance skill

## Purchase list query builder (`OrderQuery`)
```php
Purchase::query()
  ->select('purchases.id','order_number','customer_id','assigned_to','status','priority','order_date','total')
  ->with(['client:id,name','assignee:id,name'])
  ->withCount('items')
  ->when($staff, fn($q) => $q->where('assigned_to',$user->id))
  ->when($f['status'] ?? null, fn($q,$v) => $q->whereIn('status',(array)$v))
  ->when($f['priority'] ?? null, fn($q,$v) => $q->whereIn('priority',(array)$v))
  ->when($f['customer_id'] ?? null, fn($q,$v) => $q->where('customer_id',$v))
  ->when($f['assigned_to'] ?? null, fn($q,$v) => $q->where('assigned_to',$v))
  ->when($f['date_from'] ?? null, fn($q,$v) => $q->whereDate... prefer ->where('order_date','>=',$v))
  ->when($f['date_to'] ?? null, fn($q,$v) => $q->where('order_date','<=',$v))
  ->when($f['search'] ?? null, fn($q,$s) => $q->where(function($w) use ($s) {
        $w->where('order_number','like',"$s%")                       // sargable prefix match
          ->orWhereHas('client', fn($c)=>$c->where('name','like',"$s%")->orWhere('email','like',"$s%"))
          ->orWhereHas('items', fn($i)=>$i->where('product_name','like',"$s%")->orWhere('sku','like',"$s%"))
          ->orWhereHas('assignee', fn($a)=>$a->where('name','like',"$s%"));
  }))
  ->orderBy($sortColumn, $direction)->orderByDesc('purchases.id')     // deterministic tie-break
  ->paginate(min($perPage,100));
```
- **Whitelist sort**: map `client` → join/subquery on `clients.name`, `order_date|total|status|priority|created_at` direct. Never pass raw request input to `orderBy`.
- `orWhereHas` across 3 relations can be slow at scale; mitigation options (pick one, document): detect pattern (`ORD-` → order_number only; contains `@` → client email), or UNION of indexed lookups returning ids, or a denormalised `search_text` column / FULLTEXT / Scout.
- Dates: avoid `whereDate()` (kills index) — use range comparison.
- Use `simplePaginate`/cursor for infinite lists; `paginate` is fine for MVP but `COUNT(*)` grows costly at millions — cache count or approximate.

## Indexing rules
- Composite index purchase: equality columns first, then range/sort column. `(status, order_date)` serves `WHERE status=? ORDER BY order_date`.
- Prefix `LIKE 'abc%'` can use a B-tree index; `'%abc'` cannot.
- Add indexes only for real access paths in `docs/03`; verify with `EXPLAIN` (`type=ref/range`, no `Using filesort` on hot paths).
- FKs automatically index child columns in InnoDB; still add composites above.

## Avoiding N+1
`Model::preventLazyLoading(! app()->isProduction())`; use `with()` + column lists (`client:id,name`); `withCount`, `withSum`; resources use `whenLoaded`. Add a test that asserts query count for list endpoint (`DB::enableQueryLog()`; expect small constant).

## Dashboard aggregates (`DashboardService`)
```php
Purchase::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c','status');            // 1 query for all status cards
Purchase::where('status','completed')->sum('total'); avg('total');
// revenue trend — grouped in SQL, never PHP loops
Purchase::where('status','completed')->whereBetween('order_date',[$from,$to])
  ->selectRaw("DATE_FORMAT(order_date,'%Y-%m-%d') label, SUM(total) revenue, COUNT(*) purchases")->groupBy('label')->orderBy('label')->get();
// week: YEARWEEK(order_date,1) ; month: DATE_FORMAT(order_date,'%Y-%m')
```
Cache with `Cache::remember("dash:summary", 60, ...)`; bust on purchase status change (optional). Fill empty days/weeks with zero in the service so charts have continuous axes. Needs index `(status, order_date)`.

## Client stats
One query: `Purchase::where('customer_id',$id)->selectRaw('status, COUNT(*) c, SUM(total) s')->groupBy('status')`, plus `latest()->limit(5)`.

## Writes
- Bulk insert in seeders with `insert()` in chunks of 500–1000, `DB::disableQueryLog()`, wrap in transaction; use factories for realism but `Model::unguard()`/raw arrays for 500+ purchases × items to keep seeding fast.
- Keep transactions short; no external calls inside them.

## Seeder realism (R-NFR-5)
Status mix e.g. draft 5%, pending 15%, confirmed 15%, processing 15%, ready 10%, completed 35%, cancelled 5%; priorities spread; dates across last 120 days (so revenue trend has shape); for each purchase generate coherent status history path; add 0–3 notes; audit rows for creation + status changes; set `stock_reserved` consistently and make seeded stock non-negative.

## Config
`.env.example`: `DB_*`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database` (or redis). `max_per_page=100`.

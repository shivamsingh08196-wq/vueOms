<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../api/client';
import { useReportExport } from '../composables/useReportExport';
import ReportExportBar from '../components/ReportExportBar.vue';

const reports = ref(null);
const isLoading = ref(true);
const errorMessage = ref('');
const detailedView = ref('client');
const toast = ref(null);
let toastTimer = null;

const { exporting, exportReport } = useReportExport();

const filters = reactive({
  year: 'all',
  month: 'all',
  status: 'all',
  start_date: '',
  end_date: '',
  client_id: 'all',
  item_id: 'all',
});

const filterOptions = ref({ years: [], months: [], statuses: [], clients: [], items: [] });

const activeFilters = computed(() => ({
  year: filters.year,
  month: filters.month,
  status: filters.status,
  start_date: filters.start_date,
  end_date: filters.end_date,
  client_id: filters.client_id,
  item_id: filters.item_id,
}));

const hasActiveFilters = computed(() =>
  Object.values(activeFilters.value).some((value) => value !== '' && value !== 'all')
);

const exportLabels = {
  top_products_quantity: 'Top products by quantity',
  top_products_price: 'Top products by price',
  top_clients_orders: 'Top clients by order count',
  top_clients_price: 'Top clients by order price',
  per_client: 'Per-client report',
  per_product: 'Per-product report',
};

const showToast = (message, tone = 'success') => {
  toast.value = { message, tone };
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.value = null;
  }, 4000);
};

const fetchReports = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const params = new URLSearchParams();
    Object.entries(activeFilters.value).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params.set(key, value);
      }
    });
    const data = await api.get(`/api/reports?${params.toString()}`);
    reports.value = data;
    if (data?.filter_options) {
      filterOptions.value = data.filter_options;
    }
  } catch (err) {
    errorMessage.value = err.message || 'Failed to generate operational reports.';
  } finally {
    isLoading.value = false;
  }
};

const applyFilters = () => fetchReports();

const resetFilters = () => {
  Object.assign(filters, {
    year: 'all',
    month: 'all',
    status: 'all',
    start_date: '',
    end_date: '',
    client_id: 'all',
    item_id: 'all',
  });
  fetchReports();
};

const runExport = async (type, format) => {
  try {
    await exportReport(type, format, activeFilters.value);
    showToast(`${exportLabels[type] || 'Report'} exported as ${format.toUpperCase()}.`);
  } catch (err) {
    showToast(err.message || 'Export failed.', 'error');
  }
};

const formatCurrency = (value) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value) || 0);

const formatNumber = (value) => new Intl.NumberFormat('en-US').format(Number(value) || 0);

const formatDate = (value) => {
  if (!value) return '—';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value;
  return parsed.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const printReport = () => window.print();

onMounted(fetchReports);
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 data-testid="page-title" class="text-2xl font-bold tracking-tight text-[var(--color-ink)]">Reports</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
          Purchasing performance by product and by client. Filter by period and status, then export any table.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-xs font-semibold text-[var(--color-ink)] shadow-sm hover:border-[var(--color-evergreen)]"
          @click="printReport"
        >
          Print
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-evergreen)] px-3 py-2 text-xs font-semibold text-white shadow-sm hover:opacity-90"
          @click="fetchReports"
        >
          Refresh
        </button>
      </div>
    </div>

    <!-- Filters -->
    <section class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xs font-bold uppercase tracking-wider text-[var(--color-muted)]">Filters</h2>
        <span v-if="hasActiveFilters" class="rounded-full bg-[var(--color-paper)] px-2.5 py-0.5 text-[11px] font-semibold text-[var(--color-evergreen)]">
          Filtered view
        </span>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--color-muted)]">Year</span>
          <select v-model="filters.year" data-testid="report-filter-year" class="report-control" @change="applyFilters">
            <option value="all">All years</option>
            <option v-for="year in filterOptions.years" :key="year" :value="year">{{ year }}</option>
          </select>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--color-muted)]">Month</span>
          <select v-model="filters.month" data-testid="report-filter-month" class="report-control" @change="applyFilters">
            <option value="all">All months</option>
            <option v-for="month in filterOptions.months" :key="month.value" :value="month.value">{{ month.name }}</option>
          </select>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--color-muted)]">Purchase status</span>
          <select v-model="filters.status" data-testid="report-filter-status" class="report-control" @change="applyFilters">
            <option value="all">All statuses</option>
            <option v-for="status in filterOptions.statuses" :key="status" :value="status" class="capitalize">{{ status }}</option>
          </select>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--color-muted)]">Client</span>
          <select v-model="filters.client_id" data-testid="report-filter-client" class="report-control" @change="applyFilters">
            <option value="all">All clients</option>
            <option v-for="client in filterOptions.clients" :key="client.id" :value="client.id">{{ client.name }}</option>
          </select>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--color-muted)]">Date from</span>
          <input v-model="filters.start_date" type="date" data-testid="report-filter-start-date" class="report-control" @change="applyFilters" />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--color-muted)]">Date to</span>
          <input v-model="filters.end_date" type="date" data-testid="report-filter-end-date" class="report-control" @change="applyFilters" />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--color-muted)]">Product</span>
          <select v-model="filters.item_id" data-testid="report-filter-item" class="report-control" @change="applyFilters">
            <option value="all">All products</option>
            <option v-for="item in filterOptions.items" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
        </label>

        <div class="flex items-end gap-2">
          <button
            type="button"
            data-testid="report-apply-filters"
            class="inline-flex flex-1 items-center justify-center rounded-lg bg-[var(--color-evergreen)] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:opacity-90"
            @click="applyFilters"
          >
            Apply filters
          </button>
          <button
            type="button"
            data-testid="report-reset-filters"
            class="inline-flex items-center justify-center rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-xs font-semibold text-[var(--color-ink)] shadow-sm hover:border-[var(--color-evergreen)]"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </div>
    </section>

    <!-- Loading -->
    <div v-if="isLoading" class="p-12 text-center text-sm text-[var(--color-muted)]">
      <div class="mb-3 inline-block h-8 w-8 animate-spin rounded-full border-[3px] border-current border-t-transparent text-[var(--color-evergreen)]"></div>
      <div>Compiling system analytics…</div>
    </div>

    <!-- Error -->
    <div v-else-if="errorMessage" class="rounded-[var(--radius-card)] border border-red-200 bg-red-50 p-8 text-center text-sm text-red-600">
      <p>{{ errorMessage }}</p>
      <button class="mt-2 text-sm font-medium text-[var(--color-evergreen)] underline" @click="fetchReports">Retry</button>
    </div>

    <div v-else-if="reports" class="space-y-6">
      <!-- KPI cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted)]">Total revenue</div>
          <div class="mt-2 text-2xl font-bold text-[var(--color-ink)]">{{ formatCurrency(reports.summary?.total_revenue) }}</div>
        </div>
        <div class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted)]">Purchases</div>
          <div class="mt-2 text-2xl font-bold text-[var(--color-ink)]">{{ formatNumber(reports.summary?.total_purchases) }}</div>
        </div>
        <div class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted)]">Units sold</div>
          <div class="mt-2 text-2xl font-bold text-[var(--color-ink)]">{{ formatNumber(reports.summary?.total_items_sold) }}</div>
        </div>
        <div class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted)]">Avg order value</div>
          <div class="mt-2 text-2xl font-bold text-[var(--color-ink)]">{{ formatCurrency(reports.summary?.avg_order_value) }}</div>
        </div>
        <div class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted)]">Clients</div>
          <div class="mt-2 text-2xl font-bold text-[var(--color-ink)]">{{ formatNumber(reports.summary?.total_clients) }}</div>
        </div>
        <div class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted)]">Products</div>
          <div class="mt-2 text-2xl font-bold text-[var(--color-ink)]">{{ formatNumber(reports.summary?.total_items) }}</div>
        </div>
      </div>

      <!-- Status strip -->
      <section class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-bold text-[var(--color-ink)]">Purchases by status</h2>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="row in reports.purchases_by_status"
            :key="row.status"
            class="inline-flex items-center gap-2 rounded-full border border-[var(--color-line)] bg-[var(--color-paper)] px-3 py-1 text-xs"
          >
            <span class="font-semibold capitalize text-[var(--color-ink)]">{{ row.status }}</span>
            <span class="rounded-full bg-white px-2 py-0.5 font-bold text-[var(--color-muted)]">{{ row.count }}</span>
            <span class="font-semibold text-[var(--color-evergreen)]">{{ formatCurrency(row.revenue) }}</span>
          </span>
          <span v-if="!reports.purchases_by_status?.length" class="text-xs text-[var(--color-muted)]">No purchases match the current filters.</span>
        </div>
      </section>

      <!-- Top products -->
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section data-testid="report-top-products-quantity" class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-[var(--color-ink)]">Top products by quantity</h2>
            <ReportExportBar type="top_products_quantity" :busy="exporting !== null" @export="(fmt) => runExport('top_products_quantity', fmt)" />
          </div>
          <div class="overflow-x-auto">
            <table class="report-table">
              <thead>
                <tr>
                  <th>#</th><th>Product</th><th>SKU</th>
                  <th class="text-right">Unit price</th><th class="text-right">Units sold</th><th class="text-right">Revenue</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in reports.top_products_by_quantity" :key="row.item_id">
                  <td class="text-[var(--color-muted)]">{{ index + 1 }}</td>
                  <td class="font-semibold text-[var(--color-ink)]">{{ row.item_name }}</td>
                  <td class="text-[var(--color-muted)]">{{ row.sku || '—' }}</td>
                  <td class="text-right">{{ formatCurrency(row.unit_price) }}</td>
                  <td class="text-right font-semibold">{{ formatNumber(row.total_quantity) }}</td>
                  <td class="text-right">{{ formatCurrency(row.total_revenue) }}</td>
                </tr>
                <tr v-if="!reports.top_products_by_quantity?.length"><td colspan="6" class="text-center text-[var(--color-muted)]">No products match the current filters.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section data-testid="report-top-products-price" class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-[var(--color-ink)]">Top products by price</h2>
            <ReportExportBar type="top_products_price" :busy="exporting !== null" @export="(fmt) => runExport('top_products_price', fmt)" />
          </div>
          <div class="overflow-x-auto">
            <table class="report-table">
              <thead>
                <tr>
                  <th>#</th><th>Product</th><th>SKU</th>
                  <th class="text-right">Unit price</th><th class="text-right">Revenue</th><th class="text-right">Units sold</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in reports.top_products_by_price" :key="row.item_id">
                  <td class="text-[var(--color-muted)]">{{ index + 1 }}</td>
                  <td class="font-semibold text-[var(--color-ink)]">{{ row.item_name }}</td>
                  <td class="text-[var(--color-muted)]">{{ row.sku || '—' }}</td>
                  <td class="text-right">{{ formatCurrency(row.unit_price) }}</td>
                  <td class="text-right font-semibold">{{ formatCurrency(row.total_revenue) }}</td>
                  <td class="text-right">{{ formatNumber(row.total_quantity) }}</td>
                </tr>
                <tr v-if="!reports.top_products_by_price?.length"><td colspan="6" class="text-center text-[var(--color-muted)]">No products match the current filters.</td></tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- Top clients -->
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section data-testid="report-top-clients-orders" class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-[var(--color-ink)]">Top clients by order count</h2>
            <ReportExportBar type="top_clients_orders" :busy="exporting !== null" @export="(fmt) => runExport('top_clients_orders', fmt)" />
          </div>
          <div class="overflow-x-auto">
            <table class="report-table">
              <thead>
                <tr><th>#</th><th>Client</th><th>Email</th><th class="text-right">Orders</th><th class="text-right">Total spent</th></tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in reports.top_clients_by_orders" :key="row.client_id">
                  <td class="text-[var(--color-muted)]">{{ index + 1 }}</td>
                  <td class="font-semibold text-[var(--color-ink)]">{{ row.client_name || row.name }}</td>
                  <td class="text-[var(--color-muted)]">{{ row.email || '—' }}</td>
                  <td class="text-right font-semibold">{{ formatNumber(row.orders_count) }}</td>
                  <td class="text-right">{{ formatCurrency(row.total_spent) }}</td>
                </tr>
                <tr v-if="!reports.top_clients_by_orders?.length"><td colspan="5" class="text-center text-[var(--color-muted)]">No clients match the current filters.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section data-testid="report-top-clients-price" class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-[var(--color-ink)]">Top clients by order price</h2>
            <ReportExportBar type="top_clients_price" :busy="exporting !== null" @export="(fmt) => runExport('top_clients_price', fmt)" />
          </div>
          <div class="overflow-x-auto">
            <table class="report-table">
              <thead>
                <tr><th>#</th><th>Client</th><th>Email</th><th class="text-right">Total spent</th><th class="text-right">Orders</th></tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in reports.top_clients_by_price" :key="row.client_id">
                  <td class="text-[var(--color-muted)]">{{ index + 1 }}</td>
                  <td class="font-semibold text-[var(--color-ink)]">{{ row.client_name || row.name }}</td>
                  <td class="text-[var(--color-muted)]">{{ row.email || '—' }}</td>
                  <td class="text-right font-semibold">{{ formatCurrency(row.total_spent) }}</td>
                  <td class="text-right">{{ formatNumber(row.orders_count) }}</td>
                </tr>
                <tr v-if="!reports.top_clients_by_price?.length"><td colspan="5" class="text-center text-[var(--color-muted)]">No clients match the current filters.</td></tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- Detailed report: per client or per product -->
      <section class="rounded-[var(--radius-card)] border border-[var(--color-line)] bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <h2 class="text-sm font-bold text-[var(--color-ink)]">Detailed report</h2>
            <div class="inline-flex overflow-hidden rounded-lg border border-[var(--color-line)]">
              <button
                type="button"
                data-testid="report-view-client"
                class="px-3 py-1.5 text-xs font-semibold transition"
                :class="detailedView === 'client' ? 'bg-[var(--color-evergreen)] text-white' : 'bg-white text-[var(--color-muted)] hover:text-[var(--color-ink)]'"
                @click="detailedView = 'client'"
              >
                Per client
              </button>
              <button
                type="button"
                data-testid="report-view-product"
                class="px-3 py-1.5 text-xs font-semibold transition"
                :class="detailedView === 'product' ? 'bg-[var(--color-evergreen)] text-white' : 'bg-white text-[var(--color-muted)] hover:text-[var(--color-ink)]'"
                @click="detailedView = 'product'"
              >
                Per product
              </button>
            </div>
          </div>
          <ReportExportBar
            :type="detailedView === 'client' ? 'per_client' : 'per_product'"
            :busy="exporting !== null"
            @export="(fmt) => runExport(detailedView === 'client' ? 'per_client' : 'per_product', fmt)"
          />
        </div>

        <div v-if="detailedView === 'client'" data-testid="report-per-client" class="overflow-x-auto">
          <table class="report-table">
            <thead>
              <tr>
                <th>Client</th><th>Email</th><th>Phone</th>
                <th class="text-right">Orders</th><th class="text-right">Units</th>
                <th class="text-right">Total spent</th><th class="text-right">Avg order</th><th>Last purchase</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in reports.per_client_report" :key="row.client_id">
                <td class="font-semibold text-[var(--color-ink)]">{{ row.client_name }}</td>
                <td class="text-[var(--color-muted)]">{{ row.email || '—' }}</td>
                <td class="text-[var(--color-muted)]">{{ row.phone || '—' }}</td>
                <td class="text-right">{{ formatNumber(row.orders_count) }}</td>
                <td class="text-right">{{ formatNumber(row.total_units_purchased) }}</td>
                <td class="text-right font-semibold">{{ formatCurrency(row.total_spent) }}</td>
                <td class="text-right">{{ formatCurrency(row.avg_order_value) }}</td>
                <td class="text-[var(--color-muted)]">{{ formatDate(row.latest_purchase_date) }}</td>
              </tr>
              <tr v-if="!reports.per_client_report?.length"><td colspan="8" class="text-center text-[var(--color-muted)]">No client activity matches the current filters.</td></tr>
            </tbody>
          </table>
        </div>

        <div v-else data-testid="report-per-product" class="overflow-x-auto">
          <table class="report-table">
            <thead>
              <tr>
                <th>Product</th><th>SKU</th>
                <th class="text-right">Unit price</th><th class="text-right">Stock</th>
                <th class="text-right">Units sold</th><th class="text-right">Orders</th><th class="text-right">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in reports.per_product_report" :key="row.item_id">
                <td class="font-semibold text-[var(--color-ink)]">{{ row.item_name }}</td>
                <td class="text-[var(--color-muted)]">{{ row.sku || '—' }}</td>
                <td class="text-right">{{ formatCurrency(row.unit_price) }}</td>
                <td class="text-right">{{ formatNumber(row.current_stock) }}</td>
                <td class="text-right">{{ formatNumber(row.total_quantity_sold) }}</td>
                <td class="text-right">{{ formatNumber(row.orders_count) }}</td>
                <td class="text-right font-semibold">{{ formatCurrency(row.total_revenue) }}</td>
              </tr>
              <tr v-if="!reports.per_product_report?.length"><td colspan="7" class="text-center text-[var(--color-muted)]">No product sales match the current filters.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Toast -->
    <div
      v-if="toast"
      data-testid="report-toast"
      :role="toast.tone === 'error' ? 'alert' : 'status'"
      class="fixed bottom-6 right-6 z-50 max-w-sm rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
      :class="toast.tone === 'error' ? 'bg-red-600 text-white' : 'bg-[var(--color-evergreen)] text-white'"
    >
      {{ toast.message }}
    </div>
  </div>
</template>

<style scoped>
.report-control {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid var(--color-line);
  background: #fff;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  color: var(--color-ink);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
}
.report-control:focus {
  outline: 2px solid var(--color-amber);
  outline-offset: 1px;
}
.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.report-table th {
  text-align: left;
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-muted);
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid var(--color-line);
  white-space: nowrap;
}
.report-table td {
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid var(--color-line);
  vertical-align: middle;
}
.report-table tbody tr:hover {
  background: var(--color-paper);
}
</style>

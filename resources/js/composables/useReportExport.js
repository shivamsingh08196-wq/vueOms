import { ref } from 'vue';

/**
 * Authorised report export helper.
 *
 * The export endpoint streams a file and requires the Sanctum bearer token, so a
 * plain `<a href>` will not work. This composable performs an authenticated
 * `fetch`, reads the filename from `Content-Disposition`, and triggers a browser
 * download from an object URL.
 *
 * @returns {{ exporting: import('vue').Ref<string|null>, exportError: import('vue').Ref<string>, buildQuery: Function, exportReport: Function }}
 */
export function useReportExport() {
  const exporting = ref(null);
  const exportError = ref('');

  /**
   * Serialise export type, format and the active filters into a query string.
   * `all`, empty and nullish filter values are omitted.
   */
  const buildQuery = (type, format, filters = {}) => {
    const params = new URLSearchParams({ type, format });
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '' && value !== 'all') {
        params.set(key, value);
      }
    });
    return params.toString();
  };

  /** Extract the filename from a Content-Disposition header, with a fallback. */
  const filenameFrom = (disposition, fallback) => {
    if (!disposition) return fallback;
    const match = /filename\*?=(?:UTF-8'')?["']?([^"';]+)["']?/i.exec(disposition);
    if (!match) return fallback;
    try {
      return decodeURIComponent(match[1].trim());
    } catch {
      return match[1].trim();
    }
  };

  /**
   * Download a report export.
   *
   * @param {string} type   Export type (e.g. `top_products_quantity`, `per_client`).
   * @param {string} format One of `csv`, `xls`, `xlsx`, `pdf`.
   * @param {object} filters Active report filters to carry through to the export.
   * @returns {Promise<string>} The downloaded filename.
   */
  const exportReport = async (type, format, filters = {}) => {
    exporting.value = `${type}:${format}`;
    exportError.value = '';
    try {
      const token = typeof localStorage !== 'undefined' ? localStorage.getItem('token') : null;
      const response = await fetch(`/api/reports/export?${buildQuery(type, format, filters)}`, {
        headers: {
          Accept: '*/*',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
      });

      if (!response.ok) {
        throw new Error(`Export failed (HTTP ${response.status})`);
      }

      const blob = await response.blob();
      const fallback = `${type}-${new Date().toISOString().slice(0, 10)}.${format}`;
      const filename = filenameFrom(response.headers.get('Content-Disposition'), fallback);

      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);

      return filename;
    } catch (err) {
      exportError.value = err.message || 'Export failed.';
      throw err;
    } finally {
      exporting.value = null;
    }
  };

  return { exporting, exportError, buildQuery, exportReport };
}

export default useReportExport;

import qs from 'qs';
import { buildRequestParams } from '@/helpers/requestParamUtil';

export async function downloadCSV(urls) {
    const params = buildRequestParams();
    params.limit = 10000;
    params.page = 1;

    const queryString = qs.stringify(params, { encode: true, arrayFormat: 'brackets' });
    const url = `${urls['manuscripts_export_csv']}?${queryString}`;

    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`Network error: ${response.statusText}`);
    }
    const blob = await response.blob();

    downloadFile(blob, 'manuscripts.csv', 'text/csv');
}

export function downloadFile(blob, fileName, mimeType) {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', fileName);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

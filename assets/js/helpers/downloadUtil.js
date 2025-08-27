// src/helpers/downloadUtils.js
import qs from 'qs';
import { getSearchParams } from '@/helpers/searchParamUtil';

export async function downloadCSV(urls, type) {
    const params = getSearchParams();
    params.limit = 10000;
    params.page = 1;
    const queryString = qs.stringify(params, { encode: true, arrayFormat: 'brackets' });
    const urlIdentifier=`${type}_export_csv`
    const url = `${urls[urlIdentifier]}?${queryString}`;
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`Network error: ${response.statusText}`);
    }
    const blob = await response.blob();
    downloadFile(blob, `${type}.csv`, 'text/csv');
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

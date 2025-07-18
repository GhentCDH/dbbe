import { ref, watch } from 'vue';

export function usePaginationCount(resultTableRef) {
    const countRecords = ref('');

    const updateCountRecords = () => {
        const table = resultTableRef.value?.$refs?.table;
        if (!table || !table.count) {
            countRecords.value = '';
            return;
        }

        const perPage = parseInt(table.limit, 10);
        const from = ((table.Page - 1) * perPage) + 1;
        const to = table.Page === table.totalPages ? table.count : from + perPage - 1;

        const parts = table.opts.texts.count.split('|');
        let i;
        if (table.count === 1) {
            i = Math.min(2, parts.length - 1);
        } else if (table.totalPages === 1) {
            i = Math.min(1, parts.length - 1);
        } else {
            i = 0;
        }
        countRecords.value = parts[i]
            .replace('{count}', table.count)
            .replace('{from}', from)
            .replace('{to}', to);

    };
    return {
        countRecords,
        updateCountRecords,
    };
}

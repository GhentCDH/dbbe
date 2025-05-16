import qs from 'qs';
import { initSearchSession, updateSearchSession } from "@/Components/Search/searchSessionUtil";

export function useSearchSession(context) {
    const getUrl = (route) => context.urls?.[route] ?? '';

    const getSearchParams = () => {
        const params = qs.parse(window.location.href.split('?', 2)[1], { plainObjects: true }) ?? {};
        params.orderBy = params.orderBy ?? context.tableOptions?.orderBy?.column ?? null;
        params.ascending = params.ascending ?? 1;
        params.page = params.page ?? 1;
        params.limit = params.limit ?? context.tableOptions?.perPage ?? 25;
        return params;
    };

    const onData = (data, onDataExtend = null) => {
        if (typeof onDataExtend === 'function') {
            onDataExtend(data);
        }

        const params = getSearchParams();

        updateSearchSession({
            params,
            count: data.count,
        });

        context.aggregation = data.aggregation;
        context.data.search = data.search;
        context.data.filters = data.filters;
        context.data.count = data.count;
    };

    const collapseGroup = (e) => {
        const group = e.target.parentElement;
        const index = Array.from(group.parentNode.children).indexOf(group) - 1;
        context.$set(
            context.config.groupIsOpen,
            index,
            context.config.groupIsOpen[index] !== undefined
                ? !context.config.groupIsOpen[index]
                : true
        );
    };

    const init = () => {
        initSearchSession({
            urls: {
                paginate: getUrl('paginate'),
            },
            count: context.data?.count,
            params: getSearchParams(),
        });
    };

    const setupCollapsibleLegends = () => {
        const legends = context.$el.querySelectorAll('.vue-form-generator .collapsible legend');
        legends.forEach((legend) => (legend.onclick = collapseGroup));
    };

    const handleConfigChange = (schema) => (config) => {
        if (config && schema.groups) {
            schema.groups.forEach((group, index) => {
                const isOpen = config.groupIsOpen[index];
                group.styleClasses = group.styleClasses.replace(' collapsed', '') + (isOpen ? '' : ' collapsed');
            });
        }
    };

    return {
        init,
        onData,
        collapseGroup,
        setupCollapsibleLegends,
        handleConfigChange,
        getSearchParams,
    };
}

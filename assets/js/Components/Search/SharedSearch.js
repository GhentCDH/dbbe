import qs from 'qs';
import SearchSession from './SearchSession';
import CollapsibleGroups from './CollapsibleGroups';

export default {
    mixins: [
        SearchSession,
        CollapsibleGroups,
    ],
    methods: {
        onData(data) {
            // Execute extended on data method (e.g., defined in AbstractSearch.js)
            if ('onDataExtend' in this) {
                this.onDataExtend(data);
            }
            // update search session
            const params = this.getSearchParams();
            this.updateSearchSession({
                params,
                count: data.count,
            });

            // update local data
            this.aggregation = data.aggregation;
            // todo: ditch .data?
            this.data.search = data.search;
            this.data.filters = data.filters;
            this.data.count = data.count;
        },
        getUrl(route) {
            return this.urls[route] ?? '';
        },
        getSearchParams() {
            const params = qs.parse(window.location.href.split('?', 2)[1], { plainObjects: true }) ?? [];
            params.orderBy = params.orderBy ?? this.tableOptions.orderBy?.column ?? null;
            params.ascending = params.ascending ?? 1;
            params.page = params.page ?? 1;
            params.limit = params.limit ?? this.tableOptions.perPage ?? 25;

            return params;
        },
    },
    created() {
        this.initSearchSession({
            urls: {
                paginate: this.getUrl('paginate'),
            },
            count: this.data.count,
            params: this.getSearchParams(),
        });
    },
};
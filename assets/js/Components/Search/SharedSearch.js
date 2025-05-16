import qs from 'qs';
import Vue from 'vue/dist/vue.js';
import {initSearchSession, updateSearchSession} from "@/Components/Search/searchSession";

export default {
    data() {
        return {
            config: {
                groupIsOpen: [],
            },
            defaultConfig: {
                groupIsOpen: [],
            },
        };
    },
    methods: {
        collapseGroup(e) {
            const group = e.target.parentElement;
            const index = Array.from(group.parentNode.children).indexOf(group) - 1;
            Vue.set(
                this.config.groupIsOpen,
                index,
                this.config.groupIsOpen[index] !== undefined ? !this.config.groupIsOpen[index] : true
            );
        },
        onData(data) {
            if ('onDataExtend' in this) {
                this.onDataExtend(data);
            }
            const params = this.getSearchParams();
            updateSearchSession({
                params,
                count: data.count,
            });

            this.aggregation = data.aggregation;
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
        initSearchSession({
            urls: {
                paginate: this.getUrl('paginate'),
            },
            count: this.data.count,
            params: this.getSearchParams(),
        });
    },
    mounted() {
        const collapsableLegends = this.$el.querySelectorAll('.vue-form-generator .collapsible legend');
        collapsableLegends.forEach((legend) => legend.onclick = this.collapseGroup);

        this.$on('config-changed', function (config) {
            if (config && this.schema.groups) {
                this.schema.groups.forEach((group, index) => {
                    group.styleClasses = group.styleClasses.replace(' collapsed', '') + ((config.groupIsOpen[index] !== undefined && config.groupIsOpen[index]) ? '' : ' collapsed');
                });
            }
        });
    },
};
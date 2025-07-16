import qs from 'qs';
import merge from 'lodash.merge';

const STORAGE_KEY = 'search_session';

export function useSearchSession(context, cookieName = null) {
    const getUrl = (route) => context.urls?.[route] ?? '';

    const parseQueryParams = () => {
        const [_, queryString] = window.location.href.split('?', 2);
        return qs.parse(queryString, { plainObjects: true }) ?? {};
    };

    const getSearchParams = () => {
        const query = parseQueryParams();

        return {
            orderBy: query.orderBy ?? context.tableOptions?.orderBy?.column ?? null,
            ascending: query.ascending ?? 1,
            page: query.page ?? 1,
            limit: query.limit ?? context.tableOptions?.perPage ?? 25,
            ...query,
        };
    };

    const onData = (data, extend = null) => {
        if (typeof extend === 'function') extend(data);

        updateSearchSession({
            params: getSearchParams(),
            count: data.count,
        });

        Object.assign(context.data, {
            search: data.search,
            filters: data.filters,
            count: data.count,
        });

        context.aggregation = data.aggregation;
    };

    const collapseGroup = (e) => {
        const group = e.target.closest('.group');
        const index = Array.from(group?.parentNode?.children || []).indexOf(group) - 1;

        context.$set(
            context.config.groupIsOpen,
            index,
            !context.config.groupIsOpen[index]
        );

        context.$emit('config-changed', context.config);
    };

    const setupCollapsibleLegends = () => {
        const legends = context.$el.querySelectorAll('.vue-form-generator .collapsible legend');
        legends.forEach((legend) => {
            legend.onclick = collapseGroup;
        });
    };

    const handleConfigChange = (schema) => (config) => {
        if (!config || !schema.groups) return;

        schema.groups.forEach((group, i) => {
            const open = config.groupIsOpen[i];
            group.styleClasses = group.styleClasses.replace(' collapsed', '') + (open ? '' : ' collapsed');
        });
    };

    const setCookie = (name, value) => {
        context.$cookies.set(name, value, '30d');
    };

    const getCookie = (name, fallback = {}) => {
        try {
            const value = context.$cookies.get(name);
            return value ? merge({}, fallback, value) : fallback;
        } catch {
            return fallback;
        }
    };

    const init = () => {
        if (cookieName && context.defaultConfig) {
            if (!context.$cookies.isKey(cookieName)) {
                setCookie(cookieName, context.defaultConfig);
            }
            context.config = getCookie(cookieName, context.defaultConfig);
        }

        initSearchSession({
            urls: { paginate: getUrl('paginate') },
            count: context.data?.count,
            params: getSearchParams(),
        });
    };


    const initSearchSession = (defaults = {}, overrides = {}) => {
        const session = merge({}, defaults, overrides, { hash: Date.now() });
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    }

    const updateSearchSession =(overrides = {}) => {
        let session;
        try {
            session = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY)) ?? {};
        } catch {
            session = {};
        }
        session.params = {}; // clear existing
        session = merge({}, session, overrides, { hash: Date.now() });
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    }

    return {
        init,
        onData,
        collapseGroup,
        setupCollapsibleLegends,
        handleConfigChange,
        getSearchParams,
    };
}

import qs from 'qs';
import merge from 'lodash.merge';
import {getSearchParams} from "@/helpers/searchParamUtil";
import VueCookies from 'vue-cookies';

const STORAGE_KEY = 'search_session';

export function useSearchSession(context, cookieName = null) {
    const getUrl = (route) => context.urls?.[route] ?? '';

    const onData = (data, extend = null) => {
        if (typeof extend === 'function') extend(data);
        let session;
        try {
            session = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY)) ?? {};
        } catch {
            session = {};
        }
        session = {
            ...session,
            params: getSearchParams(),
            count: data.count,
            hash: Date.now(),
        };
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
        Object.assign(context.data, {
            search: data.search,
            filters: data.filters,
            count: data.count,
        });
        context.aggregation = data.aggregation;
    };

    const setupCollapsibleLegends = () => {

        const legends = context.$el.querySelectorAll('.vue-form-generator .collapsible legend');
        legends.forEach((legend) => {
            legend.onclick = (e) => {
                const group = e.target.parentElement;
                const index = Array.from(group?.parentNode?.children || []).indexOf(group) - 1;

                context.$set(
                    context.config.groupIsOpen,
                    index,
                    !context.config.groupIsOpen[index]
                );

                context.$emit('config-changed', context.config);
            };
        });
    };

    const handleConfigChange = (schema) => (config) => {
        if (!config || !schema.groups) return;
        schema.groups.forEach((group, i) => {
            const open = config.groupIsOpen[i];
            group.styleClasses = group.styleClasses.replace(' collapsed', '') + (open ? '' : ' collapsed');
        });
        console.log(config.groupIsOpen)
    };


    const setCookie = (name, value) => {
        VueCookies.set(name, value, '30d');
    };


    const getCookie = (name, fallback = {}) => {
        try {
            const value = VueCookies.get(name);
            return value ? merge({}, fallback, value) : fallback;
        } catch {
            return fallback;
        }
    };
    const init = () => {
        if (cookieName && context.defaultConfig) {
            if (!VueCookies.get(cookieName)) {
                setCookie(cookieName, context.defaultConfig);
            }
            context.config = getCookie(cookieName, context.defaultConfig);
        }
        const session = merge(
            {},
            {
                urls: { paginate: getUrl('paginate') },
                count: context.data?.count,
                params: getSearchParams(),
            },
            {},
            { hash: Date.now() }
        );
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    };


    return {
        init,
        onData,
        setupCollapsibleLegends,
        handleConfigChange
    };
}

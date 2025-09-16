import qs from 'qs';
import merge from 'lodash.merge';
import { getSearchParams } from "@/helpers/searchParamUtil";
import VueCookies from 'vue-cookies';
import {reactive, ref} from 'vue';
const STORAGE_KEY = 'search_session';

export function useSearchSession({
                                     urls,
                                     data,
                                     aggregation,
                                     emit,
                                     elRef,
                                    onDataExtend
                                 }, cookieName = null) {

    const getUrl = (route) => urls?.[route] ?? '';
    const defaultConfig = reactive({ groupIsOpen: [] });
    const config = reactive({ groupIsOpen: [] });

    const setConfig = (index, value) => {
        config.groupIsOpen[index] = value;
    };

    const aggregationLoaded = ref(false);
    const onData = (response, extend = null) => {
        if (typeof extend === 'function') extend(response);

        let session;
        try {
            session = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY)) ?? {};
        } catch {
            session = {};
        }

        session = {
            ...session,
            params: getSearchParams(),
            count: response.count,
            hash: Date.now(),
        };
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));

        Object.assign(data, {
            search: response.search,
            filters: response.filters,
            count: response.count,
        });

        aggregation.value = response.aggregation;
        aggregationLoaded.value = true;
    };

    const setupCollapsibleLegends = (schema) => {
        const legends = elRef?.value?.$el?.querySelectorAll('.vue-form-generator .collapsible legend') || [];
        const updateSchemaStyles = handleConfigChange(schema);

        legends.forEach((legend) => {
            legend.onclick = (e) => {
                const group = e.target.parentElement;
                const index = Array.from(group?.parentNode?.children || []).indexOf(group) - 1;
                setConfig(index, !config.groupIsOpen[index]);
                updateSchemaStyles(config);
            };
        });
    };

    const handleConfigChange = (schema) => (newConfig) => {
        if (!newConfig || !schema.value.groups) return;

        schema.value.groups.forEach((group, i) => {
            const open = newConfig.groupIsOpen[i];
            group.styleClasses = group.styleClasses.replace(' collapsed', '') + (open ? '' : ' collapsed');
        });
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
        if (cookieName && defaultConfig) {
            if (!VueCookies.get(cookieName)) {
                setCookie(cookieName, defaultConfig);
            }
            Object.assign(config, getCookie(cookieName, defaultConfig));
        }

        const session = merge(
            {},
            {
                urls: { paginate: getUrl('paginate') },
                count: data?.count,
                params: getSearchParams(),
            },
            { hash: Date.now() }
        );
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    };

    return {
        init,
        onData,
        setupCollapsibleLegends,
        handleConfigChange,
        aggregationLoaded
    };
}
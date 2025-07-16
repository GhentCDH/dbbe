import qs from 'qs';

export function getSearchParams(url = window.location.href, tableOptions = {}) {
    const [_, queryString] = url.split('?', 2);
    const query = qs.parse(queryString, { plainObjects: true }) ?? {};

    return {
        orderBy: query.orderBy ?? tableOptions.orderBy?.column ?? null,
        ascending: query.ascending ?? 1,
        page: query.page ?? 1,
        limit: query.limit ?? tableOptions.perPage ?? 25,
        ...query,
    };
}

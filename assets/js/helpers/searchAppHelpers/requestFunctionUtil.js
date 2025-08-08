export function cleanParams(data) {
    const params = { ...data };
    delete params.query;
    delete params.byColumn;
    if (!('orderBy' in params)) {
        delete params.ascending;
    }
    if (!params.filters) {
        delete params.filters;
    }
    return params;
}

export function handleError(error, data, count, alerts, endRequest) {
    endRequest();
    alerts.push({
        type: 'error',
        message: 'Something went wrong while processing your request. Please verify your input is valid.',
    });
    console.error(error);
    return {
        data: {
            data,
        },
    };
}

export async function axiosGet(url, options, tableCancel, openRequests, alerts, onData, data,) {
    if (openRequests > 1 && tableCancel != null) {
        tableCancel('Operation canceled by newer request');
    }

    try {
        const response = await axios.get(url, {
            cancelToken: new axios.CancelToken((c) => {
                tableCancel.value = c;
            }),
            ...options,
        });
        alerts.value = [];
        onData(response.data);
        return response;
    } catch (error) {
        if (axios.isCancel(error)) {
            return {
                data: {
                    data,
                },
            };
        }
        return handleError(error, data, count, alerts, endRequest);
    }
}

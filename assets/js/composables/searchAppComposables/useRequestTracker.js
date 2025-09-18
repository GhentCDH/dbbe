import { ref } from 'vue';
import axios from 'axios';

export function useRequestTracker() {
    const openRequests = ref(0);
    const alerts = ref([]);

    function startRequest() {
        openRequests.value += 1;
    }

    function endRequest() {
        openRequests.value = Math.max(0, openRequests.value - 1);
    }

    function cleanParams(data) {
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

    function handleError(error, data, count) {
        endRequest(); // Always end request on error

        if (error.code === 'ECONNABORTED' || error.code === 'ERR_CANCELED') {
            console.log('Request cancelled, ignoring error');
            return;
        }

        alerts.value.push({
            type: 'error',
            message: 'Something went wrong while processing your request. Please verify your input is valid.',
        });
        console.error(error);
        return {
            data: {
                data: data || [],
                count: count || 0,
            },
        };
    }

    async function axiosGet(url, options = {}, tableCancel, onData, fallbackData) {
        if (openRequests.value > 1 && tableCancel.value != null) {
            tableCancel.value('Operation canceled by newer request');
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
            endRequest(); // End request on success
            return response;
        } catch (error) {
            if (axios.isCancel(error)) {
                endRequest(); // End request on cancel
                return {
                    data: {
                        data: fallbackData?.data || [],
                        count: fallbackData?.count || 0,
                    },
                };
            }

            return handleError(error, fallbackData?.data, fallbackData?.count);
        }
    }

    return {
        openRequests,
        alerts,
        startRequest,
        endRequest,
        cleanParams,
        handleError,
        axiosGet
    };
}
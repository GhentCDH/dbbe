// composables/useManuscriptRequest.js
import { ref } from 'vue';
import axios from 'axios';
import qs from 'qs';
import { pushHistory } from '@/helpers/searchAppHelpers/historyUtil';

export function useRequestFunction({
                                         urls,
                                         startRequest,
                                         endRequest,
                                         alerts,
                                         onData,
                                         openRequests,
                                         initialized,
                                         actualRequest,
                                         historyRequest,
                                         noHistory,
                                         model,
                                         originalModel,
                                         fields,
                                         tableOptions,
                                         tableCancel,
                                     }) {
    // tableCancel as a ref to keep cancel token function

    const requestFunction = async (data) => {
        const params = { ...data };
        delete params.query;
        delete params.byColumn;
        if (!('orderBy' in params)) {
            delete params.ascending;
        }
        if (!params.filters) {
            delete params.filters;
        }

        startRequest();

        const handleError = (error) => {
            endRequest();
            alerts.value.push({
                type: 'error',
                message:
                    'Something went wrong while processing your request. Please verify your input is valid.',
            });
            console.error(error);
            return {
                data: {
                    data: data,
                    count: 0, // or handle count properly
                },
            };
        };

        const axiosGet = async (url, options = {}) => {
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
                endRequest();
                return response;
            } catch (error) {
                if (axios.isCancel(error)) {
                    return {
                        data: {
                            data: data,
                            count: 0,
                        },
                    };
                }
                return handleError(error);
            }
        };

        let url = urls['manuscripts_search_api'];

        if (!initialized.value) {
            onData(data);
            endRequest();
            return {
                data: {
                    data: data.data,
                    count: data.count,
                },
            };
        }

        if (!actualRequest.value) {
            endRequest();
            return {
                data: {
                    data: data,
                    count: 0,
                },
            };
        }

        if (historyRequest.value) {
            if (historyRequest.value !== 'init') {
                url = `${url}?${historyRequest.value}`;
            }
            const resp = await axiosGet(url);
            endRequest();
            return resp;
        }

        if (!noHistory.value) {
            pushHistory(params, model, originalModel, fields, tableOptions);
        } else {
            noHistory.value = false;
        }

        const resp = await axiosGet(url, {
            params,
            paramsSerializer: qs.stringify,
        });

        endRequest();
        return resp;
    };

    return {
        requestFunction,
    };
}

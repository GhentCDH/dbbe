import { ref } from 'vue';

export function useRequestTracker() {
    const openRequests = ref(0);
    const alerts = ref([]);

    function startRequest() {
        openRequests.value += 1;
    }

    function endRequest() {
        openRequests.value = Math.max(0, openRequests.value - 1);
    }

    return {
        openRequests,
        alerts,
        startRequest,
        endRequest
    };
}

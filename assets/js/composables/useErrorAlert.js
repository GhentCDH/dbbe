import { getErrorMessage, isLoginError } from "@/helpers/errorUtil"

export function useErrorAlert(alerts) {
    return function handleError(contextMessage = 'An error occurred') {
        return function(error) {
            alerts.value.push({
                type: 'error',
                message: contextMessage,
                extra: getErrorMessage(error),
                login: isLoginError(error),
            })
            console.error(error)
        }
    }
}

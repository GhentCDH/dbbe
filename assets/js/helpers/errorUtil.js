export function isLoginError(error) {
    return error.message === 'Network Error'
}

export function getErrorMessage(error) {
    if (error && error.response && error.response.data && error.response.data.error && error.response.data.error.message) {
        return error.response.data.error.message

    }
    return null
}
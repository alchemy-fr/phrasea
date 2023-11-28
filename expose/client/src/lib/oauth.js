const authRedirectKey = 'auth_redirect'
export function setAuthRedirect(uri) {
    sessionStorage.setItem(authRedirectKey, uri)
}
export function getAuthRedirect() {
    return sessionStorage.getItem(authRedirectKey)
}

export function unsetAuthRedirect() {
    return sessionStorage.removeItem(authRedirectKey)
}

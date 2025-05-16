import _merge from 'lodash.merge';

const STORAGE_KEY = 'search_session';

export function initSearchSession(defaults = {}, overrides = {}) {
    const session = _merge({}, defaults, overrides, { hash: Date.now() });
    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
}

export function updateSearchSession(overrides = {}) {
    let session = getSearchSession() ?? {};
    session.params = {}; // clear existing
    session = _merge({}, session, overrides, { hash: Date.now() });
    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
}

export function getSearchSession(hash = null) {
    try {
        const session = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY));
        return hash ? (session?.hash === hash ? session : null) : session;
    } catch {
        return null;
    }
}

import {RouteDefinition} from "./types";

export function getCurrentPath(): string {
    return window.location.href.replace(window.location.origin, '');
}

export function getLocationPrefix(): string {
    const {protocol, host} = document.location;
    return `${protocol}//${host}`;
}

export function getFullPath(route: RouteDefinition): string {
    const rPath = route.path ? '/' + route.path.replace(/^\/+/, '') : '';

    if (route.parent) {
        return getFullPath(route.parent)+rPath;
    }

    return rPath;
}

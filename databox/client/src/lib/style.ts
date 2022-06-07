export function getRelativeViewWidth(relativeSize: number): number {
    const docHeight = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;

    return docHeight * relativeSize / 100;
}

export function getRelativeViewHeight(relativeSize: number): number {
    const docHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;

    return docHeight * relativeSize / 100;
}

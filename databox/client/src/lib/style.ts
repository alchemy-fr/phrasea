export function getRelativeViewWidth(relativeSize: number): number {
    const docHeight =
        window.innerWidth ||
        document.documentElement.clientWidth ||
        document.body.clientWidth;

    return (docHeight * relativeSize) / 100;
}

export function getRelativeViewHeight(relativeSize: number): number {
    return (getWindowHeight() * relativeSize) / 100;
}

export function getWindowHeight(): number {
    return (
        window.innerHeight ||
        document.documentElement.clientHeight ||
        document.body.clientHeight
    );
}

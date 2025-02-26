type Props = {
    url: string;
    title?: string;
    width?: number;
    height?: number;
};

export function openPopup({
    url,
    title,
    width = 600,
    height = 600,
}: Props): WindowProxy {
    const toolbarHeight = 55;
    const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
    const dualScreenTop = window.screenTop ?? window.screenY ?? 0;

    const winWidth =
        window.innerWidth ??
        document.documentElement.clientWidth ??
        screen.width;
    const winHeight =
        window.innerHeight ??
        document.documentElement.clientHeight ??
        screen.height;

    const systemZoom = winWidth / window.screen.availWidth;
    const left = (winWidth - width) / 2 / systemZoom + dualScreenLeft;
    const top =
        (winHeight - (height - toolbarHeight)) / 2 / systemZoom + dualScreenTop;
    const newWindow = window.open(
        url,
        title ?? 'popup',
        `
      scrollbars=yes,
      width=${width / systemZoom},
      height=${height / systemZoom},
      top=${top},
      left=${left}
      `
    );

    if (!newWindow) {
        throw new Error(`Cannot open window`);
    }

    if (Object.prototype.hasOwnProperty.call(newWindow, 'focus')) {
        newWindow.focus();
    }

    return newWindow;
}

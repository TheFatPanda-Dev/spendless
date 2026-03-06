type PopupOptions = {
    width: number;
    height: number;
};

export function openCenteredPopup(url: string, name: string, options: PopupOptions): Window | null {
    const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
    const dualScreenTop = window.screenTop ?? window.screenY ?? 0;
    const viewportWidth = window.outerWidth ?? document.documentElement.clientWidth ?? screen.width;
    const viewportHeight = window.outerHeight ?? document.documentElement.clientHeight ?? screen.height;

    const left = Math.max(0, Math.round(dualScreenLeft + (viewportWidth - options.width) / 2));
    const top = Math.max(0, Math.round(dualScreenTop + (viewportHeight - options.height) / 2));

    return window.open(
        url,
        name,
        `popup=yes,width=${options.width},height=${options.height},left=${left},top=${top},noopener=no,noreferrer=no`,
    );
}

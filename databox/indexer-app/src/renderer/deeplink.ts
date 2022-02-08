import path from 'path';
import { app, BrowserWindow } from 'electron';

export function registerDeeplink(customScheme: string, window: BrowserWindow, isDev: boolean, onOpen: (url: string) => void): void {
  if (isDev && process.platform === 'win32') {
    // Set the path of electron.exe and your app.
    // These two additional parameters are only available on windows.
    // Setting this is required to get this working in dev mode.
    app.setAsDefaultProtocolClient(customScheme, process.execPath, [
      path.resolve(process.argv[1])
    ]);
  } else {
    app.setAsDefaultProtocolClient(customScheme);
  }

  app.on('open-url', function (event, url: string) {
    event.preventDefault();
    onOpen(url);
  });

// Force single application instance
  const gotTheLock = app.requestSingleInstanceLock();

  if (!gotTheLock) {
    app.quit();
    return;
  } else {
    app.on('second-instance', (e, argv) => {
      if (process.platform !== 'darwin') {
        // Find the arg that is our custom protocol url and store it
        onOpen(argv.find((arg) => arg.startsWith(`${customScheme}://`)));
      }

      if (window) {
        if (window.isMinimized()) window.restore();
        window.focus();
      }
    });
  }

}

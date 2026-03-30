import {getLocalStorage} from '@alchemy/storage';
import {ComponentType, lazy, LazyExoticComponent} from 'react';

export function lazyWithRetry<T extends ComponentType<any>>(
    key: string,
    factory: () => Promise<{default: T}>,
): LazyExoticComponent<T> {
    const storageKey = `c-${key}`;

    const ls = getLocalStorage();

    const delay = [200, 500, 1000, 2000];

    const retry = async (tryCount: number = 0): Promise<{default: T}> => {
        try {
            const result = await factory();

            if (!result?.default) {
                throw new Error(`Invalid component loaded for ${key}`);
            }

            return result;
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error(
                `Failed to load component ${key} #${tryCount}`,
                error,
            );
            if (tryCount >= delay.length) {
                throw error;
            } else {
                return new Promise((resolve, reject) => {
                    setTimeout(async () => {
                        try {
                            resolve(await retry(tryCount + 1));
                        } catch (error) {
                            reject(error);
                        }
                    }, delay[tryCount]);
                });
            }
        }
    };

    if (!ls) {
        return lazy<T>(retry);
    }

    return lazy<T>(async () => {
        const _true = 'y';
        const pageHasAlreadyBeenForceRefreshed =
            _true === ls.getItem(storageKey);

        try {
            const component = await retry();

            if (pageHasAlreadyBeenForceRefreshed) {
                ls.removeItem(storageKey);
            }

            return component;
        } catch (error) {
            if (!pageHasAlreadyBeenForceRefreshed) {
                // Assuming that the user is not on the latest version of the application.
                // Let's refresh the page immediately.
                ls.setItem(storageKey, _true);
                // Test whether the storage is allowed
                if (ls.getItem(storageKey) === _true) {
                    window.location.reload();

                    return {default: NullComponent as unknown as T};
                }
            }

            // The page has already been reloaded
            // Assuming that user is already using the latest version of the application.
            // Let's let the application crash and raise the error.
            throw error;
        }
    });
}

function NullComponent() {
    return null;
}

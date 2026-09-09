import {useEffect} from 'react';

/**
 * Warns the user before leaving the page while a form is dirty.
 */
export function useUnsavedChangesPrompt(dirty: boolean): void {
    useEffect(() => {
        if (!dirty) {
            return;
        }
        const handler = (e: BeforeUnloadEvent) => {
            e.preventDefault();
        };
        window.addEventListener('beforeunload', handler);

        return () => window.removeEventListener('beforeunload', handler);
    }, [dirty]);
}

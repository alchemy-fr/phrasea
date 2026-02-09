import React from 'react';

let listeners: ((e: KeyboardEvent) => void)[] = [];

export function useSelectAllKey(handler: () => void, deps: any[]) {
    React.useEffect(() => {
        const f = (e: KeyboardEvent) => {
            if (listeners[listeners.length - 1] !== f) {
                return;
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                const activeElement = document.activeElement;
                if (
                    activeElement &&
                    ['input', 'select', 'button', 'textarea'].includes(
                        activeElement.tagName.toLowerCase()
                    ) &&
                    (activeElement as HTMLInputElement).type !== 'checkbox'
                ) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                handler();
            }
        };

        listeners.push(f);

        window.addEventListener('keydown', f);

        return () => {
            listeners = listeners.filter(l => l !== f);
            window.removeEventListener('keydown', f);
        };
    }, deps);
}

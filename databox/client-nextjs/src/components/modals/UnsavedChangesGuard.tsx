'use client';

import {useEffect, useMemo, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {usePathname, useRouter, useSearchParams} from 'next/navigation';
import {
    askDiscardChanges,
    hasUnsavedChanges,
    useUnsavedChangesStore,
} from '@/lib/navigation/unsavedChanges';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';

/**
 * Protects the forms holding unsaved changes (see `useDirtyState`):
 * - closing / reloading the window: the browser's own prompt;
 * - clicking an in-app link, the browser back / forward buttons: our prompt,
 *   the navigation resumes once the user accepts to discard.
 *
 * Also renders the prompt raised by `askDiscardChanges()`. Mounted once.
 */
export function UnsavedChangesGuard() {
    const {t} = useTranslation();
    const router = useRouter();
    const pathname = usePathname();
    const params = useSearchParams();
    const pending = useUnsavedChangesStore(s => s.pending);
    const anyDirty = useUnsavedChangesStore(
        s => Object.keys(s.dirty).length > 0
    );
    // URL displayed, to restore it when a back / forward is cancelled
    const currentUrl = useRef<string | null>(null);
    const query = params.toString();
    useEffect(() => {
        currentUrl.current = query ? `${pathname}?${query}` : pathname;
    }, [pathname, query]);

    useEffect(() => {
        if (!anyDirty) {
            return;
        }
        const onBeforeUnload = (e: BeforeUnloadEvent) => {
            if (hasUnsavedChanges()) {
                e.preventDefault();
            }
        };
        window.addEventListener('beforeunload', onBeforeUnload);

        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [anyDirty]);

    useEffect(() => {
        // Capture phase on the document: runs before React's root listener,
        // so the Next <Link> handler never sees a cancelled click
        const onClick = (e: MouseEvent) => {
            if (
                e.defaultPrevented ||
                e.button !== 0 ||
                e.metaKey ||
                e.ctrlKey ||
                e.shiftKey ||
                e.altKey ||
                !hasUnsavedChanges()
            ) {
                return;
            }
            const anchor =
                e.target instanceof Element
                    ? e.target.closest<HTMLAnchorElement>('a[href]')
                    : null;
            if (
                !anchor ||
                (anchor.target && anchor.target !== '_self') ||
                anchor.hasAttribute('download')
            ) {
                return;
            }
            const url = new URL(anchor.href, window.location.href);
            if (
                url.origin !== window.location.origin ||
                (url.pathname === window.location.pathname &&
                    url.search === window.location.search)
            ) {
                // Another site: `beforeunload` covers it. Same page: nothing lost.
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            void askDiscardChanges().then(discard => {
                if (discard) {
                    router.push(url.pathname + url.search + url.hash);
                }
            });
        };

        // Capture on the window: runs before Next's own popstate listener
        const onPopState = (e: PopStateEvent) => {
            const displayed = currentUrl.current;
            if (!displayed || !hasUnsavedChanges()) {
                return;
            }
            // The URL already changed, but Next has not rendered it yet:
            // put back the URL of the screen still displayed while asking.
            // `null` state: Next syncs its URL without navigating.
            e.stopImmediatePropagation();
            window.history.pushState(null, '', displayed);
            void askDiscardChanges().then(discard => {
                if (discard) {
                    // Within the discard grace period: goes through this time
                    window.history.back();
                }
            });
        };

        document.addEventListener('click', onClick, true);
        window.addEventListener('popstate', onPopState, true);

        return () => {
            document.removeEventListener('click', onClick, true);
            window.removeEventListener('popstate', onPopState, true);
        };
    }, [router]);

    return (
        <Dialog
            open={!!pending}
            onOpenChange={open => !open && pending?.resolve(false)}
        >
            <DialogContent size="sm" data-testid="unsaved-changes-dialog">
                <DialogHeader>
                    <DialogTitle>
                        {t('dialog.discard.title', 'Discard changes?')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'unsaved_changes.description',
                            'You have unsaved changes. They will be lost if you leave.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => pending?.resolve(false)}
                        autoFocus
                    >
                        {t('dialog.discard.keep', 'Keep editing')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => pending?.resolve(true)}
                    >
                        {t('dialog.discard.confirm', 'Discard')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * `useRouter()` whose `push` / `replace` / `back` ask before leaving unsaved
 * changes. For navigations that leave the current screen; opening a routed
 * dialog over it does not need it.
 */
export function useGuardedRouter() {
    const router = useRouter();

    return useMemo(
        () => ({
            ...router,
            push: (...args: Parameters<typeof router.push>) =>
                void guard(() => router.push(...args)),
            replace: (...args: Parameters<typeof router.replace>) =>
                void guard(() => router.replace(...args)),
            back: () => void guard(() => router.back()),
        }),
        [router]
    );
}

async function guard(navigate: () => void): Promise<void> {
    if (!hasUnsavedChanges() || (await askDiscardChanges())) {
        navigate();
    }
}

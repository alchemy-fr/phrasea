'use client';

import {useEffect, useRef, useState} from 'react';
import {usePathname, useSearchParams} from 'next/navigation';
import {
    navigationKey,
    onNavigationStart,
} from '@/lib/navigation/navigationProgress';

/** A navigation served from the prefetch cache must not flash the bar. */
const SHOW_DELAY = 150;
const TRICKLE_INTERVAL = 200;
const FINISH_DURATION = 200;
/** A navigation that never commits (redirected back to the same URL…). */
const MAX_DURATION = 30_000;

type Phase = 'idle' | 'loading' | 'finishing';

/**
 * Horizontal bar at the top of the window while a navigation loads (route
 * chunks, RSC payload). The previous screen stays displayed meanwhile: the
 * routes have no `loading.tsx`.
 */
export function NavigationProgress() {
    const key = navigationKey(usePathname(), useSearchParams().toString());
    const [phase, setPhase] = useState<Phase>('idle');
    const [value, setValue] = useState(0);
    const committed = useRef(key);
    const pending = useRef(false);
    const shown = useRef(false);
    const timers = useRef<{
        show?: ReturnType<typeof setTimeout>;
        trickle?: ReturnType<typeof setInterval>;
        hide?: ReturnType<typeof setTimeout>;
        max?: ReturnType<typeof setTimeout>;
    }>({});
    const finish = useRef(() => {});

    useEffect(() => {
        const t = timers.current;
        const clear = () => {
            clearTimeout(t.show);
            clearInterval(t.trickle);
            clearTimeout(t.hide);
            clearTimeout(t.max);
        };

        finish.current = () => {
            if (!pending.current) {
                return;
            }
            pending.current = false;
            clear();
            if (!shown.current) {
                return;
            }
            shown.current = false;
            setValue(1);
            setPhase('finishing');
            t.hide = setTimeout(() => {
                setPhase('idle');
                setValue(0);
            }, FINISH_DURATION * 2);
        };

        const unsubscribe = onNavigationStart(url => {
            if (navigationKey(url.pathname, url.search) === committed.current) {
                // Hash only, or a back / forward whose URL is already displayed
                return;
            }
            if (pending.current) {
                // Superseded by another navigation: the bar keeps going
                clearTimeout(t.max);
                t.max = setTimeout(() => finish.current(), MAX_DURATION);

                return;
            }
            clear();
            pending.current = true;
            setPhase('idle');
            setValue(0);
            t.show = setTimeout(() => {
                shown.current = true;
                setPhase('loading');
                setValue(0.1);
                t.trickle = setInterval(
                    () => setValue(v => v + (0.9 - v) * 0.08),
                    TRICKLE_INTERVAL
                );
            }, SHOW_DELAY);
            t.max = setTimeout(() => finish.current(), MAX_DURATION);
        });

        return () => {
            unsubscribe();
            clear();
        };
    }, []);

    useEffect(() => {
        committed.current = key;
        finish.current();
    }, [key]);

    return (
        <div
            aria-hidden
            className="pointer-events-none fixed inset-x-0 top-0 z-[100] h-0.5"
        >
            <div
                className="h-full origin-left bg-primary"
                style={{
                    transform: `scaleX(${value})`,
                    opacity: phase === 'loading' ? 1 : 0,
                    transition:
                        phase === 'finishing'
                            ? `transform ${FINISH_DURATION}ms ease-out, opacity ${FINISH_DURATION}ms ease-out ${FINISH_DURATION}ms`
                            : `transform ${TRICKLE_INTERVAL}ms linear`,
                }}
            />
        </div>
    );
}

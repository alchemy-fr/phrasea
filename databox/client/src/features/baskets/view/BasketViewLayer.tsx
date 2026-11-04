'use client';

import {useState} from 'react';
import {usePathname} from 'next/navigation';
import {isStackedRoute, matchBasketView} from '@/lib/routes';
import {BasketViewRoute} from './BasketViewRoute';

/**
 * The basket view is a screen, not a dialog: dialogs and viewers opened from
 * it (basket management, asset view…) take the `@modal` slot and must stack
 * over it instead of replacing it. It is therefore rendered here, above the
 * page, for as long as the URL is the basket view or a stacked route opened
 * from it.
 */
export function BasketViewLayer() {
    const pathname = usePathname();
    const [basketId, setBasketId] = useState(() => matchBasketView(pathname));

    const next =
        matchBasketView(pathname) ??
        (isStackedRoute(pathname) ? basketId : undefined);
    if (next !== basketId) {
        setBasketId(next);
    }

    // Another basket is another screen (selection, scroll…), as when it was
    // a page of its own
    return next ? <BasketViewRoute key={next} basketId={next} /> : null;
}

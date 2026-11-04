'use client';

import Link from 'next/link';
import {usePathname} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {BookImageIcon, SlidersHorizontalIcon} from 'lucide-react';
import {useAdminAccess} from '@/features/admin/useAdminAccess';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';

/**
 * Links to the administration screens the user has something to do in
 * (see useAdminAccess). Nothing for the others.
 */
export function AdminNav({
    orientation = 'horizontal',
}: {
    orientation?: 'horizontal' | 'vertical';
}) {
    const {t} = useTranslation();
    const pathname = usePathname();
    const access = useAdminAccess();
    const items = [
        access.publications && {
            href: routes.publications(),
            label: t('nav.publications', 'Publications'),
            icon: <BookImageIcon />,
            testId: 'nav-publications',
        },
        access.profiles && {
            href: routes.profiles(),
            label: t('nav.profiles', 'Profiles'),
            icon: <SlidersHorizontalIcon />,
            testId: 'nav-profiles',
        },
    ].filter(i => !!i);

    if (items.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label={t('nav.admin', 'Administration')}
            className={cn(
                'flex gap-1',
                orientation === 'vertical' ? 'flex-col' : 'items-center'
            )}
        >
            {items.map(item => {
                const active = pathname.startsWith(item.href);

                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        data-testid={item.testId}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'inline-flex h-8 items-center gap-2 rounded-md px-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground [&_svg]:size-4',
                            active && 'bg-accent text-foreground'
                        )}
                    >
                        {item.icon}
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );
}

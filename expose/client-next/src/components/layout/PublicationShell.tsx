'use client';

import {type ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {MenuIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
    Tooltip,
} from '@/components/ui/overlays';
import {TopBar} from './TopBar';
import {AdminNav} from './AdminNav';

/**
 * Shell of the publication pages: the header, and a navigation drawer —
 * closed by default — holding the tree of the publication and the
 * administration links.
 */
export function PublicationShell({
    navigation,
    children,
}: {
    /** The tree of the publication, once known */
    navigation?: (close: () => void) => ReactNode;
    children: ReactNode;
}) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);
    const close = () => setOpen(false);

    return (
        <div className="flex min-h-[100dvh] flex-col">
            <TopBar
                leading={
                    <Sheet open={open} onOpenChange={setOpen}>
                        <Tooltip
                            content={t(
                                'layout.open_navigation',
                                'Open navigation menu'
                            )}
                        >
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    data-testid="open-navigation"
                                    aria-label={t(
                                        'layout.open_navigation',
                                        'Open navigation menu'
                                    )}
                                >
                                    <MenuIcon />
                                </Button>
                            </SheetTrigger>
                        </Tooltip>
                        <SheetContent
                            side="left"
                            className="gap-0"
                            aria-describedby={undefined}
                        >
                            <SheetHeader className="border-b">
                                <SheetTitle>
                                    {t('layout.navigation', 'Navigation')}
                                </SheetTitle>
                            </SheetHeader>
                            <div className="min-h-0 flex-1 overflow-y-auto p-2">
                                {navigation?.(close)}
                            </div>
                            <div className="border-t p-2" onClick={close}>
                                <AdminNav orientation="vertical" />
                            </div>
                        </SheetContent>
                    </Sheet>
                }
            />
            <main className="flex flex-1 flex-col">{children}</main>
        </div>
    );
}

'use client';

import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {ChevronDownIcon, PlusIcon, ShoppingBasketIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import {useBasketStore} from './basketStore';
import {Button} from '@/components/ui/button';
import {Badge} from '@/components/ui/misc';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {BasketFormDialog} from './BasketFormDialog';
import {routes} from '@/lib/routes';
import {Tooltip} from '@/components/ui/overlays';

export function BasketSwitcher({
    selection,
    onAdded,
}: {
    selection: Asset[];
    onAdded?: () => void;
}) {
    const {t} = useTranslation();
    const router = useRouter();
    const {openModal} = useModals();
    const {current, baskets, load, setCurrent, addToCurrent} = useBasketStore();

    useEffect(() => {
        void load();
    }, [load]);

    const onMainClick = async () => {
        if (selection.length > 0) {
            try {
                await addToCurrent(selection.map(a => a.id));
                toast.success(
                    t('basket.added', '{{count}} item(s) added to basket', {
                        count: selection.length,
                    })
                );
                onAdded?.();
            } catch (e: any) {
                toast.error(e?.message);
            }
        } else if (current) {
            router.push(routes.basketView(current.id));
        } else {
            openModal(BasketFormDialog, {});
        }
    };

    return (
        <div className="inline-flex items-center">
            <Tooltip
                content={
                    selection.length > 0
                        ? t('basket.add_selection', 'Add selection to basket')
                        : current
                          ? t('basket.open', 'Open basket')
                          : t('basket.create', 'Create basket')
                }
            >
                <Button
                    variant="outline"
                    size="sm"
                    className="rounded-r-none"
                    onClick={onMainClick}
                >
                    <ShoppingBasketIcon />
                    <span className="hidden max-w-32 truncate md:inline">
                        {current?.name ?? t('basket.basket', 'Basket')}
                    </span>
                    {current?.assetCount !== undefined ? (
                        <Badge variant="muted">{current.assetCount}</Badge>
                    ) : null}
                </Button>
            </Tooltip>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        className="rounded-l-none border-l-0 px-1.5"
                        aria-label={t('basket.switch', 'Switch basket')}
                    >
                        <ChevronDownIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-64">
                    <DropdownMenuLabel>
                        {t('basket.switch', 'Switch basket')}
                    </DropdownMenuLabel>
                    {baskets
                        .filter(b => !b.isArchived)
                        .map(b => (
                            <DropdownMenuItem
                                key={b.id}
                                disabled={!b.capabilities.edit}
                                onSelect={() => setCurrent(b)}
                            >
                                <ShoppingBasketIcon
                                    className={
                                        b.id === current?.id
                                            ? 'text-primary'
                                            : undefined
                                    }
                                />
                                <span className="flex-1 truncate">
                                    {b.name}
                                </span>
                                {b.assetCount !== undefined ? (
                                    <Badge variant="muted">
                                        {b.assetCount}
                                    </Badge>
                                ) : null}
                            </DropdownMenuItem>
                        ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onSelect={() => openModal(BasketFormDialog, {})}
                    >
                        <PlusIcon /> {t('basket.create', 'Create basket')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

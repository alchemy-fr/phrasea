'use client';

import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    ChevronDownIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PlusIcon,
    ShoppingBasketIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, Basket} from '@/types/api';
import {useBasketStore} from './basketStore';
import {Button} from '@/components/ui/button';
import {Badge} from '@/components/ui/misc';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {BasketFormDialog} from './BasketFormDialog';
import {routes} from '@/lib/routes';
import {Tooltip} from '@/components/ui/overlays';
import {ConfirmDialog} from '@/components/ui/confirm';

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
    const {current, baskets, load, addToCurrent} = useBasketStore();

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
                            <BasketRow
                                key={b.id}
                                basket={b}
                                current={b.id === current?.id}
                            />
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

/**
 * One basket of the switcher: the row selects it, the “…” next to it holds
 * what can be done to the basket itself (rename, delete).
 */
function BasketRow({basket, current}: {basket: Basket; current: boolean}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const setCurrent = useBasketStore(s => s.setCurrent);
    const remove = useBasketStore(s => s.remove);
    const hasActions = basket.capabilities.edit || basket.capabilities.delete;

    return (
        <div
            className="flex items-center gap-0.5"
            data-testid="basket-switch-row"
            data-basket-id={basket.id}
        >
            <DropdownMenuItem
                className="min-w-0 flex-1"
                data-testid="basket-switch-item"
                disabled={!basket.capabilities.edit}
                onSelect={() => setCurrent(basket)}
            >
                <ShoppingBasketIcon
                    className={current ? 'text-primary' : undefined}
                />
                <span className="flex-1 truncate">{basket.name}</span>
                {basket.assetCount !== undefined ? (
                    <Badge variant="muted">{basket.assetCount}</Badge>
                ) : null}
            </DropdownMenuItem>
            {hasActions ? (
                <DropdownMenuSub>
                    <DropdownMenuSubTrigger
                        hideChevron
                        className="shrink-0 px-1.5"
                        data-testid="basket-switch-more"
                        aria-label={t('common.more', 'More')}
                    >
                        <MoreHorizontalIcon />
                    </DropdownMenuSubTrigger>
                    <DropdownMenuSubContent>
                        {basket.capabilities.edit ? (
                            <DropdownMenuItem
                                onSelect={() =>
                                    openModal(BasketFormDialog, {basket})
                                }
                            >
                                <PencilIcon /> {t('common.edit', 'Edit')}
                            </DropdownMenuItem>
                        ) : null}
                        {basket.capabilities.delete ? (
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() =>
                                    openModal(ConfirmDialog, {
                                        title: t(
                                            'basket.delete.title',
                                            'Delete basket "{{name}}"?',
                                            {name: basket.name}
                                        ),
                                        destructive: true,
                                        onConfirm: () => remove(basket.id),
                                    })
                                }
                            >
                                <Trash2Icon /> {t('common.delete', 'Delete')}
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuSubContent>
                </DropdownMenuSub>
            ) : null}
        </div>
    );
}

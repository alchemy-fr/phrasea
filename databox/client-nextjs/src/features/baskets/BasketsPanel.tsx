'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    ArchiveIcon,
    ArchiveRestoreIcon,
    CheckIcon,
    MoreVerticalIcon,
    PencilIcon,
    PlusIcon,
    ShoppingBasketIcon,
    Trash2Icon,
} from 'lucide-react';
import type {Basket} from '@/types/api';
import {useBasketStore} from './basketStore';
import {Input} from '@/components/ui/input';
import {Button} from '@/components/ui/button';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {Badge, Skeleton} from '@/components/ui/misc';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {BasketFormDialog} from './BasketFormDialog';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';

export function BasketsPanel() {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {
        baskets,
        loaded,
        loading,
        load,
        loadMore,
        next,
        includeArchived,
        setIncludeArchived,
        setQuery,
    } = useBasketStore();
    const [filter, setFilter] = useState('');
    const debounced = useDebouncedValue(filter, 250);

    useEffect(() => {
        void load();
    }, [load]);

    useEffect(() => {
        if (loaded || debounced) {
            setQuery(debounced);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    return (
        <div className="flex flex-col">
            <div className="flex items-center gap-1 px-2 pb-2">
                <Input
                    value={filter}
                    onChange={e => setFilter(e.target.value)}
                    placeholder={t('common.filter', 'Filter…')}
                    className="h-8"
                />
                <Button
                    variant="outline"
                    size="icon-sm"
                    onClick={() => openModal(BasketFormDialog, {})}
                    aria-label={t('basket.create', 'Create basket')}
                >
                    <PlusIcon />
                </Button>
            </div>
            <div className="px-3 pb-2">
                <LabeledControl
                    label={t('basket.display_archived', 'Display archived')}
                    className="text-xs"
                >
                    <Switch
                        checked={includeArchived}
                        onCheckedChange={setIncludeArchived}
                    />
                </LabeledControl>
            </div>
            {loading && baskets.length === 0 ? (
                <div className="space-y-2 px-3">
                    {[...Array(3)].map((_, i) => (
                        <Skeleton key={i} className="h-8" />
                    ))}
                </div>
            ) : null}
            {loaded && baskets.length === 0 ? (
                <p className="px-3 py-4 text-center text-xs text-muted-foreground">
                    {t('basket.empty_list', 'No basket yet')}
                </p>
            ) : null}
            <ul>
                {baskets.map(b => (
                    <BasketRow key={b.id} basket={b} />
                ))}
            </ul>
            {next ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="mx-2 my-2"
                    onClick={() => loadMore()}
                >
                    {t('common.load_more', 'Load more')}
                </Button>
            ) : null}
        </div>
    );
}

function BasketRow({basket}: {basket: Basket}) {
    const {t} = useTranslation();
    const router = useRouter();
    const {openModal} = useModals();
    const {current, setCurrent, archive, remove} = useBasketStore();
    const isCurrent = current?.id === basket.id;

    const menu = (
        Item: typeof DropdownMenuItem,
        Sep: typeof DropdownMenuSeparator
    ) => (
        <>
            {basket.capabilities.edit ? (
                <Item
                    onSelect={() => setCurrent(isCurrent ? undefined : basket)}
                >
                    <CheckIcon />{' '}
                    {isCurrent
                        ? t('basket.unset_current', 'Unset as current')
                        : t('basket.set_current', 'Set as current')}
                </Item>
            ) : null}
            <Item
                onSelect={() =>
                    router.push(routes.basketManage(basket.id, 'info'))
                }
            >
                <PencilIcon /> {t('common.edit', 'Edit')}
            </Item>
            {basket.capabilities.edit ? (
                <Item onSelect={() => archive(basket.id, !basket.isArchived)}>
                    {basket.isArchived ? (
                        <ArchiveRestoreIcon />
                    ) : (
                        <ArchiveIcon />
                    )}
                    {basket.isArchived
                        ? t('basket.unarchive', 'Unarchive')
                        : t('basket.archive', 'Archive')}
                </Item>
            ) : null}
            {basket.capabilities.delete ? (
                <>
                    <Sep />
                    <Item
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
                    </Item>
                </>
            ) : null}
        </>
    );

    return (
        <ContextMenu>
            <ContextMenuTrigger asChild>
                <li
                    className={cn(
                        'group/basket flex items-center gap-1 px-2',
                        isCurrent && 'bg-primary/10'
                    )}
                >
                    <button
                        type="button"
                        className="flex min-w-0 flex-1 items-center gap-2 rounded px-1 py-1.5 text-left text-sm hover:bg-accent"
                        onClick={() =>
                            router.push(routes.basketView(basket.id))
                        }
                        onDoubleClick={() =>
                            basket.capabilities.edit && setCurrent(basket)
                        }
                    >
                        <ShoppingBasketIcon
                            className={cn(
                                'size-4 shrink-0',
                                isCurrent
                                    ? 'text-primary'
                                    : 'text-muted-foreground'
                            )}
                        />
                        <span
                            className={cn(
                                'flex-1 truncate',
                                basket.isArchived &&
                                    'text-muted-foreground line-through'
                            )}
                        >
                            {basket.name}
                        </span>
                        {basket.assetCount !== undefined ? (
                            <Badge variant="muted">{basket.assetCount}</Badge>
                        ) : null}
                    </button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                className="opacity-0 group-hover/basket:opacity-100 data-[state=open]:opacity-100"
                            >
                                <MoreVerticalIcon />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {menu(DropdownMenuItem, DropdownMenuSeparator)}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </li>
            </ContextMenuTrigger>
            <ContextMenuContent>
                {menu(ContextMenuItem as any, ContextMenuSeparator as any)}
            </ContextMenuContent>
        </ContextMenu>
    );
}

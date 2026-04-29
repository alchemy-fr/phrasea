import React, {
    FunctionComponent,
    useCallback,
    useMemo,
    useRef,
    useState,
} from 'react';
import {
    Box,
    Button,
    CircularProgress,
    DialogContent,
    Divider,
    List,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
    Skeleton,
    TextField,
} from '@mui/material';
import DialogActions from '@mui/material/DialogActions';
import {useTranslation} from 'react-i18next';
import AddBoxIcon from '@mui/icons-material/AddBox';
import SortableList, {
    OrderChangeHandler,
    SortableItem,
    SortableItemProps,
} from '../../../Ui/Sortable/SortableList.tsx';
import {Entity, StateSetter, Workspace} from '../../../../types.ts';
import ItemForm from './ItemForm.tsx';
import {
    ApiHydraObjectResponse,
    NormalizedCollectionResponse,
    UseFormSubmitReturn,
} from '@alchemy/api';
import {useModals} from '@alchemy/navigation';
import {ConfirmDialog, ConfirmDialogProps} from '@alchemy/phrasea-framework';
import FilterDropdown from './FilterDropdown.tsx';
import ArrowCircleDownIcon from '@mui/icons-material/ArrowCircleDown';

export type DefinitionBase = ApiHydraObjectResponse & Entity;

export type DefinitionItemProps<D extends DefinitionBase> = {
    data: D;
};

export type DefinitionListItemProps<D extends DefinitionBase> = {
    onEdit: () => void;
    onDelete?: () => void;
} & DefinitionItemProps<D>;

export type DefinitionItemFormProps<D extends DefinitionBase> = {
    onSave: (data: D) => Promise<D>;
    onItemUpdate: (data: D) => void;
    usedFormSubmit: UseFormSubmitReturn<D>;
    workspace: Workspace;
} & DefinitionItemProps<D>;

export type DefinitionItemManageProps<D extends DefinitionBase> = {
    workspace: Workspace;
    setSubManagementState: SetSubManagementState;
    reload: () => Promise<any>;
} & DefinitionItemProps<D>;

type ListState<D extends DefinitionBase> = {
    list: D[] | undefined;
    loading: boolean;
    loadingMore: boolean;
    next: string | undefined;
    query: string;
    filters: Filters;
};

export type ItemState<D extends DefinitionBase> = {
    item: D | undefined;
    loading: boolean;
    action: ItemAction;
};

export enum ItemAction {
    None = 0,
    Create = 1,
    Update = 2,
    Manage = 3,
}

type SortableListItemProps<D extends SortableItem & DefinitionBase> = {
    selectedItem: D | undefined;
    listComponent: FunctionComponent<DefinitionItemProps<D>>;
    onClick: (data: D) => () => void;
};

const SortableListItem = React.memo(
    <D extends SortableItem & DefinitionBase>({
        data,
        itemProps,
    }: {
        itemProps: SortableListItemProps<D>;
    } & SortableItemProps<D>) => {
        const {selectedItem, onClick, listComponent} = itemProps;

        return (
            <ListItem disablePadding key={data.id}>
                <ListItemButton
                    selected={data.id === selectedItem?.id}
                    onClick={onClick(data)}
                >
                    {React.createElement(listComponent, {
                        data,
                        key: data.id,
                    })}
                </ListItemButton>
            </ListItem>
        );
    }
);

export type OnSort = (ids: string[]) => void;

export type NormalizeData<D extends DefinitionBase> = (data: D) => D;

type SubManagementState = {
    formId?: string | undefined;
    action: ItemAction;
};

type SetSubManagementState = StateSetter<SubManagementState | undefined>;

type BodyProps<D extends DefinitionBase> = {
    items: D[] | undefined;
    reload: () => Promise<void>;
};

type BodyWithListLoadedProps<D extends DefinitionBase> = {
    items: D[];
} & BodyProps<D>;

type Filters = Record<string, any>;

type SetFilterFunc<F extends Filters> = (name: keyof F, value: any) => void;

type FilterProps<F extends Filters> = {
    filters: F;
    setFilter: SetFilterFunc<F>;
};

type Props<D extends DefinitionBase, F extends Filters> = {
    load: (props: {
        nextUrl?: string;
        query?: string;
        filters: F;
    }) => Promise<NormalizedCollectionResponse<D>>;
    loadItem?: (id: string) => Promise<D>;
    listComponent: FunctionComponent<DefinitionListItemProps<D>>;
    itemComponent: FunctionComponent<DefinitionItemFormProps<D>>;
    manageItemComponent?: FunctionComponent<DefinitionItemManageProps<D>>;
    createNewItem: () => Partial<D>;
    batchDelete?: boolean;
    onClose?: () => void;
    minHeight?: number | undefined;
    newLabel: string;
    handleSave: (data: D) => Promise<D>;
    handleDelete?: (id: string) => Promise<void>;
    workspace: Workspace;
    onSort?: OnSort;
    normalizeData?: NormalizeData<D>;
    denormalizeData?: NormalizeData<D>;
    setSubManagementState?: SetSubManagementState;
    managerFormId?: string;
    preSearchBody?: (props: BodyProps<D>) => React.ReactNode;
    preListBody?: (props: BodyProps<D>) => React.ReactNode;
    settingsNode?: (props: BodyProps<D>) => React.ReactNode;
    searchFilter?: (props: BodyWithListLoadedProps<D>, value: string) => D[];
    applyFilters?: (list: D[], filters: F) => D[];
    filters?: (props: FilterProps<F>) => React.ReactNode;
    deleteConfirmAssertions?: (
        data: D
    ) => ConfirmDialogProps<any>['assertions'];
};

export default function DefinitionManager<
    D extends DefinitionBase,
    F extends Filters,
>({
    load,
    handleDelete,
    itemComponent,
    manageItemComponent,
    listComponent,
    loadItem,
    onClose,
    batchDelete,
    createNewItem,
    minHeight,
    newLabel,
    handleSave,
    workspace,
    onSort,
    normalizeData,
    denormalizeData,
    managerFormId = 'definition-manager',
    setSubManagementState: parentSetSubManagementState,
    deleteConfirmAssertions,
    preListBody,
    preSearchBody,
    searchFilter,
    applyFilters,
    settingsNode,
    filters: inputFilters,
}: Props<D, F>) {
    const {openModal} = useModals();
    const hasPaginationRef = useRef<boolean>(true);
    const [listState, setListState] = useState<ListState<D>>({
        list: undefined,
        loading: false,
        loadingMore: false,
        next: undefined,
        query: '',
        filters: {},
    });
    const [subManagementState, setSubManagementState] = React.useState<
        SubManagementState | undefined
    >();
    const [itemState, proxiedSetItemState] = React.useState<ItemState<D>>({
        item: undefined,
        loading: false,
        action: ItemAction.None,
    });

    const setItemState = useCallback(
        (state: ItemState<D>) => {
            proxiedSetItemState(state);
            if (parentSetSubManagementState) {
                parentSetSubManagementState({
                    formId,
                    action: state.action,
                });
            }
        },
        [proxiedSetItemState, parentSetSubManagementState]
    );

    const [submitting, setSubmitting] = React.useState(false);
    const {loading, list} = listState;
    const {loading: loadingItem, item, action} = itemState;
    const {t} = useTranslation();
    const [searchTerm, setSearchTerm] = React.useState('');
    const [filters, setFilters] = useState<F>({} as F);

    const setFilter = useCallback<SetFilterFunc<F>>(
        (name, value) => {
            setFilters(p => ({
                ...p,
                [name]: value,
            }));
        },
        [setFilters]
    );

    const reload = useCallback(async () => {
        const query = searchTerm;
        setListState({
            list: undefined,
            next: undefined,
            loading: true,
            loadingMore: false,
            query,
            filters,
        });

        try {
            const r = await load({
                query,
                filters,
            });
            setListState(p => ({
                ...p,
                list: normalizeData ? r.result.map(normalizeData) : r.result,
                next: r.next || undefined,
                loading: false,
            }));
            if (
                !query &&
                !Object.values(filters).some(value => Boolean(value))
            ) {
                hasPaginationRef.current = !!r.next;
            }
        } catch (e) {
            setListState(p => ({
                ...p,
                list: [],
                loading: false,
                next: undefined,
                loadingMore: false,
            }));
            return;
        }
    }, [hasPaginationRef, searchTerm, filters]);

    const loadNext = useCallback(async () => {
        setListState(p => ({
            ...p,
            loadingMore: true,
        }));

        try {
            const r = await load({
                nextUrl: listState.next,
                query: searchTerm,
                filters,
            });
            setListState(p => ({
                ...p,
                list: (p.list ?? []).concat(
                    normalizeData ? r.result.map(normalizeData) : r.result
                ),
                next: r.next || undefined,
                loadingMore: false,
            }));
        } catch (e) {
            setListState(p => ({
                ...p,
                loadingMore: false,
            }));
            throw e;
        }
    }, [listState, searchTerm, filters]);

    const bodyProps: BodyProps<D> = {
        items: list,
        reload,
    };

    let filteredList =
        !hasPaginationRef.current && searchFilter && list
            ? searchFilter(bodyProps as BodyWithListLoadedProps<D>, searchTerm)
            : list;
    if (applyFilters && filteredList) {
        filteredList = applyFilters(filteredList, filters);
    }

    const newItem = React.useMemo(() => createNewItem(), [item, createNewItem]);

    const handleItemClick = useCallback(
        (data: D, forceEdit: boolean = false) =>
            () => {
                const clickAction =
                    manageItemComponent && !forceEdit
                        ? ItemAction.Manage
                        : ItemAction.Update;
                if (loadItem) {
                    if (
                        item &&
                        action !== ItemAction.Create &&
                        item.id === data.id
                    ) {
                        return;
                    }

                    setItemState({
                        item: undefined,
                        loading: true,
                        action: clickAction,
                    });
                    loadItem(data.id)
                        .then(d => {
                            setItemState({
                                item: d,
                                loading: false,
                                action: clickAction,
                            });
                        })
                        .catch(() => {
                            setItemState({
                                item: undefined,
                                loading: false,
                                action: ItemAction.None,
                            });
                        });
                } else {
                    setItemState({
                        item: data,
                        loading: false,
                        action: clickAction,
                    });
                }
            },
        [setItemState, loadItem, item, parentSetSubManagementState]
    );

    const onItemUpdate = React.useCallback(
        (newData: D) => {
            const newNormData = normalizeData
                ? normalizeData(newData)
                : newData;

            setItemState({
                item: newNormData,
                loading: false,
                action: ItemAction.Update,
            });

            setListState(p => {
                let newList = p.list!;
                if (newList.find(i => i.id === newData.id)) {
                    newList = newList.map(i => {
                        if (i.id === newData.id) {
                            return newData;
                        }

                        return i;
                    });
                } else {
                    newList = newList.concat([newData]);
                }

                return {
                    ...p,
                    list: newList,
                };
            });
        },
        [setListState]
    );

    const createAttribute = () => {
        setItemState({
            item: undefined,
            loading: false,
            action: ItemAction.Create,
        });
    };

    React.useEffect(() => {
        if (hasPaginationRef.current) {
            reload();
        }
    }, [reload, hasPaginationRef]);

    const onDelete = useMemo(
        () =>
            handleDelete
                ? (item: D) => {
                      openModal(ConfirmDialog, {
                          title: t(
                              'definition_manager.confirm_delete',
                              'Are you sure you want to delete this item?'
                          ),
                          assertions: deleteConfirmAssertions
                              ? deleteConfirmAssertions(item)
                              : undefined,
                          onConfirm: async () => {
                              setListState(p => ({
                                  ...p,
                                  item: undefined,
                                  list: (p.list || []).filter(
                                      i => i.id !== item.id
                                  ),
                              }));
                              setItemState({
                                  item: undefined,
                                  loading: false,
                                  action: ItemAction.None,
                              });
                              handleDelete(item.id);
                          },
                      });
                  }
                : undefined,
        [t, handleDelete]
    );

    const formId: string =
        (action === ItemAction.Manage
            ? subManagementState?.formId
            : undefined) ?? managerFormId;

    const onOrderChange = useCallback<OrderChangeHandler<D & SortableItem>>(
        list => {
            setListState(p => ({
                ...p,
                list,
            }));
            onSort!(list.map(i => i.id));
        },
        [setListState]
    );

    const itemProps = useMemo(() => {
        if (!onSort) {
            return;
        }

        return {
            selectedItem: item as (D & SortableItem) | undefined,
            listComponent,
            onClick: handleItemClick,
        };
    }, [onSort, handleItemClick, item, listComponent]);

    const content = (
        <>
            <Box
                sx={theme => ({
                    display: 'flex',
                    borderRight: `1px solid ${theme.palette.divider}`,
                })}
            >
                <List
                    sx={{
                        overflowY: 'auto',
                        p: 0,
                        width: 250,
                    }}
                    component="div"
                    role="list"
                >
                    {preSearchBody?.(bodyProps)}
                    {searchFilter ? (
                        <ListSubheader
                            sx={{
                                display: 'flex',
                                flexDirection: 'row',
                                alignItems: 'center',
                                gap: 1,
                                p: 1,
                                zIndex: 2,
                                backgroundColor: 'transparent',
                            }}
                        >
                            <TextField
                                type={'search'}
                                fullWidth
                                placeholder={t(
                                    'common.search.placeholder',
                                    'Search…'
                                )}
                                value={searchTerm}
                                onChange={e => setSearchTerm(e.target.value)}
                                size="small"
                            />
                            {inputFilters ? (
                                <div
                                    style={{
                                        position: 'relative',
                                    }}
                                >
                                    <FilterDropdown
                                        activeFilterCount={
                                            Object.entries(filters).filter(
                                                ([_, v]) => !!v
                                            ).length
                                        }
                                        children={() => [
                                            <React.Fragment key={'1'}>
                                                {inputFilters({
                                                    setFilter,
                                                    filters,
                                                })}
                                            </React.Fragment>,
                                        ]}
                                    />
                                </div>
                            ) : null}
                            {settingsNode ? settingsNode(bodyProps) : null}
                        </ListSubheader>
                    ) : null}
                    {preListBody?.(bodyProps)}
                    <ListItem disablePadding>
                        <ListItemButton
                            selected={action === ItemAction.Create}
                            onClick={createAttribute}
                            disabled={!list}
                        >
                            <ListItemIcon>
                                <AddBoxIcon />
                            </ListItemIcon>
                            <ListItemText primary={newLabel} />
                        </ListItemButton>
                    </ListItem>
                    <Divider />

                    {filteredList ? (
                        <>
                            {onSort ? (
                                <SortableList<D & SortableItem, any>
                                    list={
                                        filteredList as (D &
                                            SortableItem &
                                            DefinitionBase)[]
                                    }
                                    onOrderChange={onOrderChange}
                                    itemComponent={SortableListItem}
                                    itemProps={itemProps!}
                                />
                            ) : (
                                filteredList.map(i => {
                                    return (
                                        <ListItem disablePadding key={i.id}>
                                            <ListItemButton
                                                selected={i.id === item?.id}
                                                onClick={handleItemClick(i)}
                                            >
                                                {React.createElement(
                                                    listComponent,
                                                    {
                                                        data: i,
                                                        key: i.id,
                                                        onEdit: handleItemClick(
                                                            i,
                                                            true
                                                        ),
                                                        onDelete:
                                                            batchDelete &&
                                                            onDelete
                                                                ? () =>
                                                                      onDelete(
                                                                          i
                                                                      )
                                                                : undefined,
                                                    }
                                                )}
                                            </ListItemButton>
                                        </ListItem>
                                    );
                                })
                            )}
                            {listState.next ? (
                                <>
                                    <ListItem disablePadding>
                                        <ListItemButton
                                            onClick={() => loadNext()}
                                        >
                                            <ListItemIcon>
                                                <ArrowCircleDownIcon />
                                            </ListItemIcon>
                                            {listState.loadingMore
                                                ? t(
                                                      'load_more.button.loading',
                                                      'Loading…'
                                                  )
                                                : t(
                                                      'load_more.button.load_more',
                                                      'Load more'
                                                  )}
                                        </ListItemButton>
                                    </ListItem>
                                </>
                            ) : null}
                        </>
                    ) : (
                        [0, 1, 2].map(i => (
                            <ListItem key={i}>
                                <ListItemIcon>
                                    <Skeleton
                                        variant="circular"
                                        width={40}
                                        height={40}
                                    />
                                </ListItemIcon>
                                <ListItemText
                                    primary={<Skeleton variant="text" />}
                                    secondary={
                                        <Skeleton
                                            variant="text"
                                            width={'40%'}
                                        />
                                    }
                                />
                            </ListItem>
                        ))
                    )}
                </List>
            </Box>
            <Box
                sx={{
                    p: action !== ItemAction.Manage ? 3 : undefined,
                    overflowY: 'auto',
                    flexGrow: 1,
                    display: 'flex',
                }}
            >
                {loadingItem && (
                    <Box
                        sx={{
                            p: 3,
                        }}
                    >
                        <CircularProgress color="inherit" size={50} />
                    </Box>
                )}
                {item && manageItemComponent && action === ItemAction.Manage ? (
                    React.createElement(manageItemComponent, {
                        key: item!.id,
                        data: item as D,
                        workspace,
                        setSubManagementState,
                        reload,
                    })
                ) : item || action === ItemAction.Create ? (
                    <div style={{flexGrow: 1}}>
                        <ItemForm
                            key={
                                action === ItemAction.Create ? 'new' : item!.id
                            }
                            itemComponent={itemComponent}
                            item={
                                action === ItemAction.Create
                                    ? (newItem as D)
                                    : item!
                            }
                            workspace={workspace}
                            onSave={handleSave}
                            formId={formId}
                            setSubmitting={setSubmitting}
                            onItemUpdate={onItemUpdate}
                            normalizeData={normalizeData}
                            denormalizeData={denormalizeData}
                        />
                        {action === ItemAction.Update && onDelete && (
                            <>
                                <hr />
                                <Button
                                    color={'error'}
                                    onClick={() => onDelete(item!)}
                                >
                                    {t('common.delete', 'Delete')}
                                </Button>
                            </>
                        )}
                    </div>
                ) : null}
            </Box>
        </>
    );

    if (!onClose) {
        return content;
    }

    const displaySaveButton =
        [ItemAction.Create, ItemAction.Update].includes(action) ||
        (subManagementState &&
            [ItemAction.Create, ItemAction.Update].includes(
                subManagementState?.action
            ));

    return (
        <>
            <DialogContent
                dividers
                sx={{
                    display: 'flex',
                    flex: '1 1 auto',
                    minHeight,
                }}
                style={{
                    padding: 0,
                }}
            >
                {content}
            </DialogContent>
            <DialogActions>
                {displaySaveButton && (
                    <>
                        <Button
                            onClick={onClose}
                            disabled={loading || submitting}
                        >
                            {t('dialog.cancel', 'Cancel')}
                        </Button>
                        <Button
                            disabled={loading || submitting}
                            loading={submitting}
                            type={formId ? 'submit' : 'button'}
                            form={formId}
                        >
                            {t('dialog.save', 'Save')}
                        </Button>
                    </>
                )}
                {!displaySaveButton && (
                    <Button onClick={onClose}>
                        {t('dialog.close', 'Close')}
                    </Button>
                )}
            </DialogActions>
        </>
    );
}

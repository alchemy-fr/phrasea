import React, {
    FunctionComponent,
    useCallback,
    useMemo,
    useRef,
    useState,
} from 'react';
import {
    Badge,
    Box,
    Button,
    Checkbox,
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
} from '../../../Ui/Sortable/SortableList.tsx';
import {Workspace} from '../../../../types.ts';
import ItemForm from './ItemForm.tsx';
import {NormalizedCollectionResponse} from '@alchemy/api';
import {useModals} from '@alchemy/navigation';
import {ConfirmDialog, ConfirmDialogProps} from '@alchemy/phrasea-framework';
import FilterDropdown from './FilterDropdown.tsx';
import ArrowCircleDownIcon from '@mui/icons-material/ArrowCircleDown';
import {
    BodyProps,
    BodyWithListLoadedProps,
    DefinitionBase,
    DefinitionItemFormProps,
    DefinitionItemManageProps,
    DefinitionListItemProps,
    FilterProps,
    Filters,
    ItemAction,
    ItemState,
    ListState,
    BatchAction,
    NormalizeData,
    OnSort,
    SetFilterFunc,
    SetSubManagementState,
    SortableListItemProps,
    SubManagementState,
    DefinitionManagerExtraProps,
} from './managerTypes.ts';
import {SortableListItem} from './SortableListItem.tsx';
import ListItemContainer from './ListItemContainer.tsx';

type Props<
    D extends DefinitionBase,
    F extends Filters,
    EP extends DefinitionManagerExtraProps,
> = {
    load: (props: {
        nextUrl?: string;
        query?: string;
        filters: F;
    }) => Promise<NormalizedCollectionResponse<D>>;
    loadItem?: (id: string) => Promise<D>;
    listComponent: FunctionComponent<DefinitionListItemProps<D>>;
    itemComponent: FunctionComponent<DefinitionItemFormProps<D, EP>>;
    manageItemComponent?: FunctionComponent<DefinitionItemManageProps<D>>;
    createNewItem: () => Partial<D>;
    itemDeletable?: boolean;
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
    batchActions?: (selection: string[]) => BatchAction<D>[];
    extraProps?: EP;
};

export default function DefinitionManager<
    D extends DefinitionBase,
    F extends Filters,
    EP extends DefinitionManagerExtraProps = {},
>({
    load,
    handleDelete,
    itemComponent,
    manageItemComponent,
    listComponent,
    loadItem,
    onClose,
    itemDeletable,
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
    batchActions,
    extraProps,
    filters: inputFilters,
}: Props<D, F, EP>) {
    const {openModal} = useModals();
    const [selection, setSelection] = useState<string[]>([]);
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

    const itemProps = useMemo<SortableListItemProps<D> | undefined>(() => {
        if (!onSort) {
            return;
        }

        return {
            selectedItem: item as (D & SortableItem) | undefined,
            listComponent,
            handleItemClick,
            setSelection,
            selection,
            itemDeletable,
            onDelete,
            extraProps,
        };
    }, [
        onSort,
        handleItemClick,
        itemDeletable,
        onDelete,
        setSelection,
        selection,
        item,
        listComponent,
        extraProps,
    ]);

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
                    {selection.length === 0 ? (
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
                    ) : null}
                    {selection.length > 0 && batchActions ? (
                        <ListItem disablePadding>
                            <Box>
                                <Badge
                                    anchorOrigin={{
                                        vertical: 'top',
                                        horizontal: 'right',
                                    }}
                                    badgeContent={selection.length}
                                    color="primary"
                                    invisible={selection.length === 0}
                                >
                                    <Checkbox
                                        checked={
                                            selection.length === list?.length
                                        }
                                        onClick={() => {
                                            if (
                                                selection.length ===
                                                list?.length
                                            ) {
                                                setSelection([]);
                                            } else {
                                                setSelection(
                                                    list?.map(i => i.id) ?? []
                                                );
                                            }
                                        }}
                                    />
                                </Badge>
                                {batchActions(selection).map(a => (
                                    <Button
                                        key={a.id}
                                        color={a.color}
                                        onClick={async () => {
                                            const p = async () => {
                                                await a.process(
                                                    selection
                                                        .map(
                                                            id =>
                                                                list!.find(
                                                                    i =>
                                                                        i.id ===
                                                                        id
                                                                )!
                                                        )
                                                        .filter(i => i),
                                                    {reload}
                                                );
                                                setSelection([]);
                                            };

                                            if (a.confirm) {
                                                openModal(ConfirmDialog, {
                                                    title: a.confirm,
                                                    onConfirm: p,
                                                });
                                                return;
                                            }

                                            await p();
                                        }}
                                        startIcon={a.icon}
                                    >
                                        {a.label}
                                    </Button>
                                ))}
                            </Box>
                        </ListItem>
                    ) : null}
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
                                        <ListItemContainer<D>
                                            key={i.id}
                                            selectedItem={item}
                                            item={i}
                                            handleItemClick={handleItemClick}
                                            selection={
                                                batchActions
                                                    ? selection
                                                    : undefined
                                            }
                                            onDelete={
                                                itemDeletable
                                                    ? onDelete
                                                    : undefined
                                            }
                                            listComponent={listComponent}
                                            setSelection={setSelection}
                                        />
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
                            extraProps={extraProps ?? ({} as EP)}
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

import React, {
    FunctionComponent,
    useCallback,
    useEffect,
    useMemo,
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
    Skeleton,
} from '@mui/material';
import {ApiHydraObjectResponse} from '../../../../api/hydra.ts';
import DialogActions from '@mui/material/DialogActions';
import {useTranslation} from 'react-i18next';
import AddBoxIcon from '@mui/icons-material/AddBox';
import {LoadingButton} from '@mui/lab';
import SortableList, {
    OrderChangeHandler,
    SortableItem,
    SortableItemProps,
} from '../../../Ui/Sortable/SortableList.tsx';
import {Entity, StateSetter, Workspace} from '../../../../types.ts';
import ItemForm from './ItemForm.tsx';
import {UseFormSubmitReturn} from '@alchemy/api';
import {useModals} from '@alchemy/navigation';
import ConfirmDialog, {ConfirmDialogProps} from '../../../Ui/ConfirmDialog.tsx';

export type DefinitionBase = ApiHydraObjectResponse & Entity;

export type DefinitionItemProps<D extends DefinitionBase> = {
    data: D;
};

export type DefinitionListItemProps<D extends DefinitionBase> = {
    onEdit: () => void;
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
} & DefinitionItemProps<D>;

type ListState<D extends DefinitionBase> = {
    list: D[] | undefined;
    loading: boolean;
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

type Props<D extends DefinitionBase> = {
    load: () => Promise<D[]>;
    loadItem?: (id: string) => Promise<D>;
    listComponent: FunctionComponent<DefinitionListItemProps<D>>;
    itemComponent: FunctionComponent<DefinitionItemFormProps<D>>;
    manageItemComponent?: FunctionComponent<DefinitionItemManageProps<D>>;
    createNewItem: () => Partial<D>;
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
    deleteConfirmAssertions?: (
        data: D
    ) => ConfirmDialogProps<any>['assertions'];
};

export default function DefinitionManager<D extends DefinitionBase>({
    load,
    handleDelete,
    itemComponent,
    manageItemComponent,
    listComponent,
    loadItem,
    onClose,
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
}: Props<D>) {
    const {openModal} = useModals();
    const [listState, setListState] = useState<ListState<D>>({
        list: undefined,
        loading: false,
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
        [proxiedSetItemState, handleSave, parentSetSubManagementState]
    );

    const [submitting, setSubmitting] = React.useState(false);
    const {loading, list} = listState;
    const {loading: loadingItem, item, action} = itemState;
    const {t} = useTranslation();

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
        [normalizeData]
    );

    const createAttribute = () => {
        setItemState({
            item: undefined,
            loading: false,
            action: ItemAction.Create,
        });
    };

    useEffect(() => {
        setListState({
            list: undefined,
            loading: true,
        });

        load().then(r => {
            setListState({
                list: normalizeData ? r.map(normalizeData) : r,
                loading: false,
            });
        });
    }, []);

    const onDelete = useCallback(() => {
        if (handleDelete && typeof item === 'object') {
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
                        list: (p.list || []).filter(i => i.id !== item.id),
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
    }, [item, t]);

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
                    overflowY: 'auto',
                    borderRight: `1px solid ${theme.palette.divider}`,
                })}
            >
                <List
                    sx={{
                        p: 0,
                        width: 250,
                        bgcolor: 'background.paper',
                    }}
                    component="div"
                    role="list"
                >
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

                    {onSort && list && (
                        <SortableList<D & SortableItem, any>
                            list={list as (D & SortableItem & DefinitionBase)[]}
                            onOrderChange={onOrderChange}
                            itemComponent={SortableListItem}
                            itemProps={itemProps!}
                        />
                    )}

                    {!onSort &&
                        list &&
                        list.map(i => {
                            return (
                                <ListItem disablePadding key={i.id}>
                                    <ListItemButton
                                        selected={i.id === item?.id}
                                        onClick={handleItemClick(i)}
                                    >
                                        {React.createElement(listComponent, {
                                            data: i,
                                            key: i.id,
                                            onEdit: handleItemClick(i, true),
                                        })}
                                    </ListItemButton>
                                </ListItem>
                            );
                        })}

                    {!list &&
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
                        ))}
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
                        {action === ItemAction.Update && handleDelete && (
                            <>
                                <hr />
                                <Button color={'error'} onClick={onDelete}>
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
                        <LoadingButton
                            disabled={loading || submitting}
                            loading={submitting}
                            type={formId ? 'submit' : 'button'}
                            form={formId}
                        >
                            {t('dialog.save', 'Save')}
                        </LoadingButton>
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

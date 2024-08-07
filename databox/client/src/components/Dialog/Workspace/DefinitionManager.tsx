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
import {ApiHydraObjectResponse} from '../../../api/hydra';
import DialogActions from '@mui/material/DialogActions';
import {useTranslation} from 'react-i18next';
import AddBoxIcon from '@mui/icons-material/AddBox';
import {useFormSubmit, UseFormSubmitReturn} from '@alchemy/api';
import {LoadingButton} from '@mui/lab';
import {toast} from 'react-toastify';
import RemoteErrors from '../../Form/RemoteErrors';
import SortableList, {
    OrderChangeHandler,
    SortableItem,
    SortableItemProps,
} from '../../Ui/Sortable/SortableList';
import {useDirtyFormPrompt} from '../Tabbed/FormTab';
import {DefaultValues} from 'react-hook-form';
import {Workspace} from "../../../types.ts";

type DefinitionBase = ApiHydraObjectResponse & {id: string};

export type DefinitionItemProps<D extends DefinitionBase> = {
    data: D;
};

export type DefinitionItemFormProps<D extends DefinitionBase> = {
    usedFormSubmit: UseFormSubmitReturn<D>;
    workspace: Workspace;
} & DefinitionItemProps<D>;

type ListState<D extends DefinitionBase> = {
    list: D[] | undefined;
    loading: boolean;
};

type ItemState<D extends DefinitionBase> = {
    item: D | 'new' | undefined;
    loading: boolean;
};

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

type Props<D extends DefinitionBase> = {
    load: () => Promise<D[]>;
    loadItem?: (id: string) => Promise<D>;
    listComponent: FunctionComponent<DefinitionItemProps<D>>;
    itemComponent: FunctionComponent<DefinitionItemFormProps<D>>;
    createNewItem: () => Partial<D>;
    onClose: () => void;
    minHeight?: number | undefined;
    newLabel: string;
    handleSave: (data: D) => Promise<D>;
    handleDelete?: (id: string) => Promise<void>;
    workspace: Workspace;
    onSort?: OnSort;
    normalizeData?: (data: D) => D;
};

export default function DefinitionManager<D extends DefinitionBase>({
    load,
    handleDelete,
    itemComponent,
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
}: Props<D>) {
    const [listState, setListState] = useState<ListState<D>>({
        list: undefined,
        loading: false,
    });
    const [itemState, setItemState] = React.useState<ItemState<D>>({
        item: undefined,
        loading: false,
    });

    const {loading, list} = listState;
    const {loading: loadingItem, item} = itemState;
    const {t} = useTranslation();

    const newItem = React.useMemo(() => createNewItem(), [item, createNewItem]);

    const handleItemClick = useCallback(
        (data: D) => () => {
            if (loadItem) {
                if (item && item !== 'new' && item.id === data.id) {
                    return;
                }

                setItemState({
                    item: undefined,
                    loading: true,
                });
                loadItem(data.id)
                    .then(d => {
                        setItemState({
                            item: d,
                            loading: false,
                        });
                    })
                    .catch(() => {
                        setItemState(p => ({
                            ...p,
                            loading: false,
                        }));
                    });
            } else {
                setItemState({
                    item: data,
                    loading: false,
                });
            }
        },
        [setItemState, loadItem, item]
    );

    const createAttribute = () => {
        setItemState({
            item: 'new',
            loading: false,
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

    const usedFormSubmit = useFormSubmit({
        defaultValues: newItem as DefaultValues<D>,
        onSubmit: async (data: D) => {
            const newData = await handleSave(data);
            const newNormData = normalizeData
                ? normalizeData(newData)
                : newData;

            setItemState({
                item: newNormData,
                loading: false,
            });

            setListState(p => {
                let newList = p.list!;
                if (newList.find(i => i.id === newData.id)) {
                    newList = newList.map(i => {
                        if (i.id === data.id) {
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

            return newData;
        },
        onSuccess: () => {
            toast.success(
                t('definition_manager.saved', 'Definition saved!') as string
            );
        },
    });

    const {submitting, remoteErrors, forbidNavigation, reset} = usedFormSubmit;

    React.useEffect(() => {
        if (item && 'new' !== item) {
            reset({
                ...createNewItem(),
                ...item,
            });
        } else if ('new' === item) {
            reset(newItem as D);
        }
    }, [item, newItem]);

    const onDelete = useCallback(() => {
        if (handleDelete && typeof item === 'object') {
            if (
                window.confirm(
                    t(
                        'definition_manager.confirm_delete',
                        'Are you sure you want to delete this item?'
                    )
                )
            ) {
                setListState(p => ({
                    ...p,
                    item: undefined,
                    list: (p.list || []).filter(i => i.id !== item.id),
                }));
                setItemState({
                    item: undefined,
                    loading: false,
                });
                handleDelete(item.id);
            }
        }
    }, [item, t]);

    const formId = 'definitionForm';

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
            selectedItem:
                'new' !== item ? (item as D & SortableItem) : undefined,
            listComponent,
            onClick: handleItemClick,
        };
    }, [onSort, handleItemClick, item, listComponent]);

    useDirtyFormPrompt(Boolean(item) && forbidNavigation);

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
                                selected={item === 'new'}
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
                                list={
                                    list as (D &
                                        SortableItem &
                                        DefinitionBase)[]
                                }
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
                                            selected={
                                                item !== 'new' &&
                                                i.id === item?.id
                                            }
                                            onClick={handleItemClick(i)}
                                        >
                                            {React.createElement(
                                                listComponent,
                                                {
                                                    data: i,
                                                    key: i.id,
                                                }
                                            )}
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
                        p: 3,
                        overflowY: 'auto',
                        flexGrow: 1,
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
                    {item && (
                        <form
                            id={formId}
                            onSubmit={usedFormSubmit.handleSubmit}
                        >
                            {React.createElement(itemComponent, {
                                data: item === 'new' ? (newItem as D) : item!,
                                key: item === 'new' ? 'new' : item!.id,
                                usedFormSubmit,
                                workspace,
                            })}
                        </form>
                    )}
                    <RemoteErrors errors={remoteErrors} />
                    {item && item !== 'new' && handleDelete && (
                        <>
                            <hr />
                            <Button color={'error'} onClick={onDelete}>
                                {t('common.delete', 'Delete')}
                            </Button>
                        </>
                    )}
                </Box>
            </DialogContent>
            <DialogActions>
                {item && (
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
                {!item && (
                    <Button onClick={onClose}>
                        {t('dialog.close', 'Close')}
                    </Button>
                )}
            </DialogActions>
        </>
    );
}

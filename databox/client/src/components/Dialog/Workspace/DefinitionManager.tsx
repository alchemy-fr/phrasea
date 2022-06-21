import React, {FunctionComponent, useEffect, useState} from 'react';
import {
    Box,
    Button,
    DialogContent,
    Divider,
    List,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText
} from "@mui/material";
import {ApiHydraObjectResponse} from "../../../api/hydra";
import DialogActions from "@mui/material/DialogActions";
import {useTranslation} from 'react-i18next';
import AddBoxIcon from '@mui/icons-material/AddBox';
import useFormSubmit, {UseFormHandleSubmit} from "../../../hooks/useFormSubmit";
import {LoadingButton} from "@mui/lab";
import {toast} from "react-toastify";
import RemoteErrors from "../../Form/RemoteErrors";

type DefinitionBase = ApiHydraObjectResponse & { id: string };

export type DefinitionItemProps<D extends DefinitionBase> = {
    data: Partial<D>;
};

export type DefinitionItemFormProps<D extends DefinitionBase> = {
    formId: string;
    handleSubmit: UseFormHandleSubmit<D>;
    submitting: boolean;
    workspaceId: string;
} & DefinitionItemProps<D>;

type Props<D extends DefinitionBase> = {
    load: () => Promise<D[]>;
    listComponent: FunctionComponent<DefinitionItemProps<D>>;
    itemComponent: FunctionComponent<DefinitionItemFormProps<D>>;
    createNewItem: () => Partial<D>;
    onClose: () => void;
    minHeight?: number | undefined;
    newLabel: string;
    handleSave: (data: D) => Promise<D>;
    workspaceId: string;
};

type State<D extends DefinitionBase> = {
    item: D | "new" | undefined;
    list: D[] | undefined;
    loading: boolean;
}

export default function DefinitionManager<D extends DefinitionBase>({
                                                                        load,
                                                                        itemComponent,
                                                                        listComponent,
                                                                        onClose,
                                                                        createNewItem,
                                                                        minHeight,
                                                                        newLabel,
                                                                        handleSave,
                                                                        workspaceId,
                                                                    }: Props<D>) {
    const [state, setState] = useState<State<D>>({
        list: undefined,
        item: undefined,
        loading: false,
    });
    const {
        loading,
        list,
        item,
    } = state;
    const {t} = useTranslation();

    const handleItemClick = (data: D) => () => {
        setState(p => ({
            ...p,
            item: data,
        }));
    }

    const createAttribute = () => {
        setState(p => ({
            ...p,
            item: 'new',
        }));
    };

    useEffect(() => {
        setState({
            item: undefined,
            list: undefined,
            loading: true,
        });

        load().then(r => {
            setState({
                item: undefined,
                list: r,
                loading: false,
            });
        });
    }, []);

    const {
        submitting,
        handleSubmit,
        errors,
    } = useFormSubmit({
        onSubmit: async (data: D) => {
            const newData = await handleSave(data);

            setState(p => {
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
                    item: newData,
                }
            });

            return newData;
        },
        onSuccess: () => {
            toast.success(t('definition_manager.saved', 'Definition saved!'));
        }
    });

    const formId = 'definitionForm';

    return <>
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
            <Box sx={theme => ({
                display: 'flex',
                overflowY: 'auto',
                borderRight: `1px solid ${theme.palette.divider}`
            })}>
                <List
                    sx={{
                        p: 0,
                        width: 250,
                        bgcolor: 'background.paper',
                    }}
                    component="div"
                    role="list"
                >
                    <ListItem
                        disablePadding
                    >
                        <ListItemButton
                            selected={item === "new"}
                            onClick={createAttribute}
                        >
                            <ListItemIcon>
                                <AddBoxIcon/>
                            </ListItemIcon>
                            <ListItemText primary={newLabel}/>
                        </ListItemButton>
                    </ListItem>
                    <Divider/>
                    {list && list.map(i => {
                        return <ListItem
                            disablePadding
                            key={i.id}
                        >
                            <ListItemButton
                                selected={i === item}
                                onClick={handleItemClick(i)}
                            >
                                {React.createElement(listComponent, {
                                    data: i,
                                    key: i.id,
                                })}
                            </ListItemButton>
                        </ListItem>
                    })}
                </List>
            </Box>
            <Box
                sx={{
                    p: 3,
                    overflowY: 'auto',
                    flexGrow: 1,
                }}
            >
                {item && React.createElement(itemComponent, {
                    data: item === "new" ? createNewItem() : item!,
                    key: item === "new" ? 'new' : item!.id,
                    formId,
                    handleSubmit,
                    submitting,
                    workspaceId,
                })}
            </Box>
            <RemoteErrors errors={errors}/>
        </DialogContent>
        <DialogActions>
            {item && <>
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
            </>}
            {!item && <Button
                onClick={onClose}
            >
                {t('dialog.close', 'Close')}
            </Button>}
        </DialogActions>
    </>
}

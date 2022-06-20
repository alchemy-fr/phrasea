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
import useFormSubmit from "../../../hooks/useFormSubmit";
import {Collection} from "../../../types";
import {clearWorkspaceCache, postCollection} from "../../../api/collection";
import {toast} from "react-toastify";

type DefinitionBase = ApiHydraObjectResponse & { id: string };

export type DefinitionItemProps<D extends DefinitionBase> = {
    data: Partial<D>;
};

type Props<D extends DefinitionBase> = {
    load: () => Promise<D[]>;
    listComponent: FunctionComponent<DefinitionItemProps<D>>;
    itemComponent: FunctionComponent<DefinitionItemProps<D>>;
    createNewItem: () => Partial<D>;
    onClose: () => void;
    minHeight?: number | undefined;
    newLabel: string;
    handleSave: (data: D) => Promise<void>;
};

export default function DefinitionManager<D extends DefinitionBase>({
                                                                        load,
                                                                        itemComponent,
                                                                        listComponent,
                                                                        onClose,
                                                                        createNewItem,
                                                                        minHeight,
                                                                        newLabel,
    handleSave,
                                                                    }: Props<D>) {
    const [item, setItem] = useState<D | "new">();
    const [list, setList] = useState<D[]>();
    const [loading, setLoading] = useState(false);
    const {t} = useTranslation();

    const handleItemClick = (data: D) => () => {
        setItem(data);
    }

    const createAttribute = () => {
        setItem('new');
    };

    useEffect(() => {
        load().then(r => setList(r));
    }, []);

    const {
        submitting,
        handleSubmit,
        errors,
    } = useFormSubmit({
        onSubmit: async (data: Collection) => {
            return await postCollection({
                ...data,
                parent,
                workspace: workspaceId ? `/workspaces/${workspaceId}` : undefined,
            });
        },
        onSuccess: (coll) => {
            clearWorkspaceCache();
            toast.success(t('form.collection_create.success', 'Collection created!'));
            closeModal();
            onCreate(coll);
        }
    });

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
                        return <ListItem disablePadding
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
                })}
            </Box>
        </DialogContent>
        <DialogActions>
            <Button
                onClick={onClose}
                disabled={loading}
            >
                {t('dialog.close', 'Close')}
            </Button>
        </DialogActions>
    </>
}

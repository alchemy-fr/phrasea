import React, {useCallback, useState} from 'react';
import TreeItem from "@mui/lab/TreeItem";
import {IconButton, Stack, TextField} from "@mui/material";
import EditIcon from '@mui/icons-material/Edit';
import DoneIcon from '@mui/icons-material/Done';
import CloseIcon from '@mui/icons-material/Close';
import DeleteIcon from '@mui/icons-material/Delete';
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';
import {KeyboardCode} from "@dnd-kit/core";
import {alpha} from "@mui/material/styles";

type Props = {
    offset: number;
    path: string[];
    editing?: boolean;
    onEdit: (index: number, value: string | null) => void;
};

export default function EditableCollectionTree({
                                                   offset,
                                                   path,
                                                   editing: initEditing,
                                                   onEdit,
                                               }: Props) {
    const pathValue = path[0];
    const remainingPath = path.slice(1);
    const [value, setValue] = useState<string | undefined>(initEditing ? pathValue : undefined);

    const removeNode = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        onEdit(offset, null);
    }, [onEdit, offset]);

    const editing = value !== undefined;

    const handleSave = useCallback(() => {
        onEdit(offset, value!);
        setValue(undefined);
    }, [setValue, value, onEdit, offset]);

    const dismiss = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        setValue(undefined);
    }, [setValue]);

    const doneClickHandler = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        handleSave();
    }, [handleSave]);

    const onKeyDown = useCallback((e: React.KeyboardEvent<HTMLDivElement>) => {
        if (e.key === KeyboardCode.Enter) {
            e.stopPropagation();
            handleSave();
        }
    }, [handleSave]);

    const onChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        setValue(e.target.value);
    }, [setValue]);

    const createSubCollection = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        onEdit(offset + 1, 'Collection');

        if (path.length < offset) {
            const target = e.currentTarget.parentNode!.parentNode!.parentNode!.querySelector('.MuiTreeItem-iconContainer') as HTMLElement;
            console.log('target', target);
            setTimeout(() => {
                target.click();
            }, 50);
        }
    }, [setValue]);

    const onEditHandler = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        setValue(pathValue);
    }, [setValue, pathValue]);

    return <TreeItem
        sx={t => ({
            '.MuiTreeItem-content': {
                backgroundColor: alpha(t.palette.info.main, remainingPath.length > 0 ? .4 : .2),
            }
        })}
        nodeId={offset.toString()}
        label={!editing ? <Stack
            direction={'row'}
            alignItems={'center'}
        >
            {pathValue}
            <IconButton
                sx={{ml: 1}}
                onClick={onEditHandler}
            >
                <EditIcon/>
            </IconButton>
            <IconButton
                sx={{ml: 1}}
                onClick={createSubCollection}
            >
                <CreateNewFolderIcon/>
            </IconButton>
            <IconButton
                sx={{ml: 1}}
                color={'error'}
                onClick={removeNode}
            >
                <DeleteIcon/>
            </IconButton>
        </Stack> : <Stack
            onClick={e => e.stopPropagation()}
            direction={'row'}
            alignItems={'center'}
        >
            <TextField
                variant={'standard'}
                autoFocus={true}
                value={value}
                onChange={onChange}
                onKeyDown={onKeyDown}
            />
            <IconButton
                color={'error'}
                sx={{ml: 1}}
                onClick={dismiss}
            >
                <CloseIcon/>
            </IconButton>
            <IconButton
                color={'success'}
                sx={{ml: 1}}
                onClick={doneClickHandler}
            >
                <DoneIcon/>
            </IconButton>
        </Stack>}
    >
        {remainingPath.length > 0 && <EditableCollectionTree
            offset={offset + 1}
            path={remainingPath}
            onEdit={onEdit}
        />}
    </TreeItem>
}

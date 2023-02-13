import React, {useCallback, useEffect, useRef, useState} from 'react';
import TreeItem from "@mui/lab/TreeItem";
import {IconButton, Stack, TextField} from "@mui/material";
import EditIcon from '@mui/icons-material/Edit';
import DoneIcon from '@mui/icons-material/Done';
import CloseIcon from '@mui/icons-material/Close';
import DeleteIcon from '@mui/icons-material/Delete';
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';
import {KeyboardCode} from "@dnd-kit/core";
import {alpha} from "@mui/material/styles";
import {NewCollectionNode, SetExpanded, UpdateCollectionPath} from "./CollectionsTreeView";

type Props = {
    offset: number;
    nodes: NewCollectionNode[];
    onEdit: UpdateCollectionPath;
    setExpanded: SetExpanded;
};

export const nodeNewPrefix = 'new:';
export const defaultNewCollectionName = 'Collection';

export default function EditableCollectionTree({
    offset,
    nodes,
    onEdit,
    setExpanded,
}: Props) {
    const node = nodes[0];
    const id = node.id;
    const remainingPath = nodes.slice(1);
    const [value, setValue] = useState<string | undefined>(node.value);
    const ref = useRef<HTMLDivElement>();

    useEffect(() => {
        setValue(node.value);
    }, [node.value]);

    const removeNode = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        onEdit(offset, null);
    }, [onEdit, offset]);

    const editing = node.editing ?? false;

    const handleSave = useCallback(() => {
        onEdit(offset, id, value!, false);
        setTimeout(() => {
            (ref.current?.querySelector('.MuiTreeItem-content .MuiTreeItem-label') as HTMLDivElement).click();
        }, 100);
    }, [setValue, value, onEdit, offset]);

    const dismiss = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        onEdit(offset, id, node.value, false);
        setValue(node.value);
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

    const nodeId = nodeNewPrefix + offset.toString();
    const createSubCollection = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        onEdit(offset + 1, (offset + 1).toString(), defaultNewCollectionName, true);
        setExpanded(prev => !prev.includes(nodeId) ? prev.concat(nodeId) : prev);
    }, [setValue, setExpanded, nodeId]);

    const onEditHandler = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        e.stopPropagation();
        onEdit(offset, id, node.value, true);
    }, [setValue, node.value]);

    return <TreeItem
        sx={t => ({
            '.MuiTreeItem-content': {
                backgroundColor: alpha(t.palette.info.main, remainingPath.length > 0 ? .4 : .2),
            }
        })}
        ref={ref}
        nodeId={nodeId}
        label={!editing ? <Stack
            direction={'row'}
            alignItems={'center'}
        >
            {node.value}
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
            nodes={remainingPath}
            onEdit={onEdit}
            setExpanded={setExpanded}
        />}
    </TreeItem>
}

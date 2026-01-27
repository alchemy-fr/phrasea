import {TreeNodeEditComponentProps} from '@alchemy/phrasea-framework';
import {WorkspaceOrCollectionTreeItem} from './types.ts';
import {InputBase} from '@mui/material';
import {useEffect, useRef, useState} from 'react';
import CheckIcon from '@mui/icons-material/Check';
import IconButton from '@mui/material/IconButton';

export default function CollectionEdit({
    node,
    onFinishEdit,
    onCancelEdit,
    onToggleSelect,
}: TreeNodeEditComponentProps<WorkspaceOrCollectionTreeItem>) {
    const [title, setTitle] = useState(node.data.label || '');
    const inputRef = useRef<HTMLInputElement | null>(null);

    const onSubmit = () => {
        onToggleSelect(node, true);
        onFinishEdit({
            ...node.data,
            label: title,
        });
    };

    // Select all range
    useEffect(() => {
        if (inputRef.current) {
            inputRef.current.select();
        }
    }, [inputRef]);

    return (
        <div
            onMouseDown={e => e.stopPropagation()}
            onClick={e => e.stopPropagation()}
            onKeyDown={e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    onSubmit();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    onCancelEdit();
                }
            }}
            style={{
                display: 'flex',
                alignItems: 'center',
            }}
        >
            <div
                style={{
                    flexGrow: 1,
                }}
            >
                <InputBase
                    inputRef={inputRef}
                    autoFocus={true}
                    value={title}
                    onChange={e => setTitle(e.target.value)}
                    fullWidth
                />
            </div>
            <div>
                <IconButton onClick={() => onSubmit()}>
                    <CheckIcon />
                </IconButton>
            </div>
        </div>
    );
}

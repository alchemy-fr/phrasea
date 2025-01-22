import React from 'react';
import {annotationZIndex} from './common.ts';
import {Box, IconButton, Paper, TextField} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import {useTranslation} from 'react-i18next';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import {stopPropagation} from "../../../../lib/stdFuncs.ts";

type Props = {
    elementRef: React.RefObject<HTMLDivElement>;
    onDelete: () => void;
    onDuplicate: () => void;
    onRename: (newName: string) => void;
};

export default function ShapeControl({
    elementRef,
    onDelete,
    onRename,
    onDuplicate,
}: Props) {
    const {t} = useTranslation();
    const [name, setName] = React.useState<string | undefined>();

    return (
        <div
            ref={elementRef}
            style={{
                position: 'absolute',
                top: 0,
                left: 0,
                zIndex: annotationZIndex + 2,
                userSelect: 'none',
                display: 'none',
                transformOrigin: 'top left',
            }}
        >
            <Paper
                style={{
                    whiteSpace: 'nowrap',
                }}
            >
                <div
                    style={{
                        display: 'inline-block'
                    }}
                    className={'edit-controls'}>
                    <IconButton onClick={onDuplicate}>
                        <ContentCopyIcon />
                    </IconButton>
                    <IconButton
                        onClick={() => {
                            if (
                                window.confirm(
                                    t(
                                        'annotations.delete_shape.confirm',
                                        'Are you sure you want to delete this shape?'
                                    )
                                )
                            ) {
                                onDelete();
                            }
                        }}
                        color={'error'}
                    >
                        <DeleteIcon />
                    </IconButton>
                </div>

                <Box
                    sx={{
                        p: 1,
                        display: 'inline-block',
                    }}
                >
                    <Box
                        className="shape-name"
                        onClick={e => {
                            setName(
                                (e.target as HTMLDivElement).textContent ?? ''
                            );
                        }}
                        sx={{
                            display: name === undefined ? 'block' : 'none',
                            cursor: 'text',
                        }}
                    />
                    {name !== undefined && (
                        <Box
                            sx={{
                                display: 'inline-block',
                                my: -1,
                            }}
                        >
                            <TextField
                                size={'small'}
                                required={true}
                                variant={'standard'}
                                error={name.trim().length === 0}
                                value={name}
                                onMouseDown={stopPropagation}
                                onChange={e => setName(e.target.value)}
                                onKeyDown={e => {
                                    if (
                                        e.key === 'Enter' &&
                                        name.trim().length > 0
                                    ) {
                                        e.preventDefault();
                                        onRename(
                                            (e.target as HTMLInputElement).value
                                        );
                                        setName(undefined);
                                    } else if (e.key === 'Escape') {
                                        e.stopPropagation();
                                        setName(undefined);
                                    }
                                }}
                                onBlur={() => {
                                    setName(undefined);
                                }}
                                autoFocus
                            />
                        </Box>
                    )}
                </Box>
            </Paper>
        </div>
    );
}

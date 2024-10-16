import React from 'react';
import {
    IconButton,
    InputAdornment,
    TextField,
    TextFieldProps,
} from '@mui/material';
import CopyToClipboard from '../../lib/CopyToClipboard.tsx';
import {copyToClipBoardClass} from '../Media/Asset/Attribute/CopyAttribute.tsx';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';

type Props = {
    startAdornment?: React.ReactNode;
    actions?: React.ReactNode;
    value: string;
} & Omit<TextFieldProps, 'value'>;

export default function CopiableTextField({value, actions, startAdornment, ...props}: Props) {
    const handleFocus = (event: React.FocusEvent<HTMLInputElement>) =>
        event.currentTarget.select();

    return (
        <TextField
            fullWidth
            value={value}
            onFocus={handleFocus}
            InputProps={{
                readOnly: true,
                startAdornment: startAdornment ? <InputAdornment position="start">
                    {startAdornment}
                    </InputAdornment> : undefined,
                endAdornment: (
                    <InputAdornment position="end">
                        <CopyToClipboard>
                            {({copy}) => (
                                <IconButton
                                    className={copyToClipBoardClass}
                                    onClick={() => copy(value)}
                                >
                                    <ContentCopyIcon />
                                </IconButton>
                            )}
                        </CopyToClipboard>
                        {actions}
                    </InputAdornment>
                ),
            }}
            {...props}
        />
    );
}

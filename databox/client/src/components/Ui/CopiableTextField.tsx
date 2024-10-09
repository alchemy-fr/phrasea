import React from "react";
import {IconButton, InputAdornment, TextField, TextFieldProps} from "@mui/material";
import CopyToClipboard from "../../lib/CopyToClipboard.tsx";
import {copyToClipBoardClass} from "../Media/Asset/Attribute/CopyAttribute.tsx";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";

type Props = TextFieldProps;

export default function CopiableTextField({
    value,
    ...props
}: Props) {
    const handleFocus = (event: React.FocusEvent<HTMLInputElement>) => event.currentTarget.select();

    return <TextField
        fullWidth
        value={value}
        onFocus={handleFocus}
        InputProps={{
            readOnly: true,
            endAdornment: <InputAdornment position="end">
                <CopyToClipboard>
                    {({copy}) => (
                        <IconButton
                            className={copyToClipBoardClass}
                            onClick={() => copy(value as string)}
                        >
                            <ContentCopyIcon/>
                        </IconButton>
                    )}
                </CopyToClipboard>
            </InputAdornment>
        }}
        {...props}
    />
}

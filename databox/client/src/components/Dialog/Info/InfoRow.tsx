import React, {ReactNode} from 'react';
import {Box, IconButton, ListItemIcon, ListItemText, MenuItem, Typography} from "@mui/material";
import CopyToClipboard from "../../../lib/CopyToClipboard";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";

type Props = {
    icon: ReactNode;
    label: ReactNode;
    value: ReactNode;
    copyValue?: string | undefined;
};

export default function InfoRow({
                                    icon,
                                    label,
                                    value,
                                    copyValue
                                }: Props) {

    return <MenuItem disableRipple={true}>
        {icon && <ListItemIcon>
            {icon}
        </ListItemIcon>}
        <ListItemText>{label}</ListItemText>
        <Typography component="div" variant="body2" color="text.secondary">
            {copyValue && <Box component={'span'} sx={{
                mr: 1
            }}>
                <CopyToClipboard>
                    {({copy}) => <IconButton
                        onMouseDown={e => e.stopPropagation()}
                        onClick={(e) => {
                            e.stopPropagation();
                            copy(copyValue)
                        }}
                    >
                        <ContentCopyIcon/>
                    </IconButton>}
                </CopyToClipboard>
            </Box>}
            {value}
        </Typography>
    </MenuItem>
}

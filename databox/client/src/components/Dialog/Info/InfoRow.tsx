import {ReactNode} from 'react';
import {
    Box,
    IconButton,
    ListItem,
    ListItemIcon,
    ListItemText,
} from '@mui/material';
import CopyToClipboard from '../../../lib/CopyToClipboard';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import FastTooltip from '../../Ui/FastTooltip.tsx';
import InfoIcon from '@mui/icons-material/Info';

type Props = {
    icon?: ReactNode;
    label: ReactNode;
    description?: ReactNode;
    value: ReactNode;
    copyValue?: string | undefined;
    onClick?: () => void;
};

export default function InfoRow({
    icon,
    label,
    value,
    copyValue,
    onClick,
    description,
}: Props) {
    return (
        <ListItem disableGutters={true}>
            {icon && <ListItemIcon>{icon}</ListItemIcon>}
            <ListItemText
                primary={
                    description ? (
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                flexDirection: 'row',
                                gap: 2,
                            }}
                        >
                            {label}
                            {description ? (
                                <FastTooltip title={description}>
                                    <InfoIcon
                                        fontSize={'small'}
                                        color={'inherit'}
                                    />
                                </FastTooltip>
                            ) : null}
                        </div>
                    ) : (
                        label
                    )
                }
                secondaryTypographyProps={{
                    component: 'div',
                    variant: 'body2',
                }}
                secondary={
                    <>
                        {onClick ? <a onClick={onClick}>{value}</a> : value}
                        {copyValue && (
                            <Box
                                component={'span'}
                                sx={{
                                    ml: 1,
                                }}
                            >
                                <CopyToClipboard>
                                    {({copy}) => (
                                        <IconButton
                                            size={'small'}
                                            onMouseDown={e =>
                                                e.stopPropagation()
                                            }
                                            onClick={e => {
                                                e.stopPropagation();
                                                copy(copyValue);
                                            }}
                                        >
                                            <ContentCopyIcon
                                                fontSize={'small'}
                                            />
                                        </IconButton>
                                    )}
                                </CopyToClipboard>
                            </Box>
                        )}
                    </>
                }
            />
        </ListItem>
    );
}

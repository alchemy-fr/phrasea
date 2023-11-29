import {PropsWithChildren} from 'react';
import {Box} from '@mui/material';
import {SxProps} from '@mui/system';
import {Theme} from '@mui/material/styles';

type Props = PropsWithChildren<
    {} & {
        sx?: SxProps<Theme>;
    }
>;

export default function FormRow({children, sx}: Props) {
    return (
        <Box
            sx={{
                mb: 3,
                ...sx,
            }}
        >
            {children}
        </Box>
    );
}

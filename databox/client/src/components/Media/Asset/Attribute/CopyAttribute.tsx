import {Box, IconButton, SxProps} from '@mui/material';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import CopyToClipboard from '../../../../lib/CopyToClipboard';

type Props = {
    value: string | undefined;
    sx?: SxProps;
};

export const copyToClipBoardClass = 'ctcb';

export default function CopyAttribute({value, sx}: Props) {
    return (
        <Box
            style={{
                display: 'inline-block',
            }}
            sx={sx}
            className={copyToClipBoardClass}
        >
            <CopyToClipboard>
                {({copy}) => (
                    <IconButton
                        size={'small'}
                        onMouseDown={e => e.stopPropagation()}
                        onClick={e => {
                            e.stopPropagation();
                            if (value) {
                                copy(value);
                            }
                        }}
                    >
                        <ContentCopyIcon fontSize={'small'} />
                    </IconButton>
                )}
            </CopyToClipboard>
        </Box>
    );
}

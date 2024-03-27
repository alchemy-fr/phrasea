import {IconButton} from '@mui/material';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import CopyToClipboard from '../../../../lib/CopyToClipboard';

type Props = {
    value: string | undefined;
};

export const copyToClipBoardClass = 'ctcb';

export default function CopyAttribute({value}: Props) {
    return (
        <CopyToClipboard>
            {({copy}) => (
                <IconButton
                    className={copyToClipBoardClass}
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
    );
}

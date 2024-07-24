import {IconButton} from '@mui/material';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import CopyToClipboard from '../../../../lib/CopyToClipboard';

export const copyToClipBoardClass = 'ctcb';
export const copyToClipBoardContainerClass = 'ctcb-wr';

type Props = {
    value: string | undefined;
};

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

import {Asset} from '../../../types.ts';
import {Box, Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import DeleteIcon from '@mui/icons-material/Delete';
import ApprovalIcon from '@mui/icons-material/Approval';

type Props = {
    asset: Asset;
};

export default function QuarantineActions({asset}: Props) {
    const {t} = useTranslation();
    return (
        <Box>
            <pre
                style={{
                    whiteSpace: 'pre-wrap',
                }}
            >
                {JSON.stringify(asset.source?.analysis ?? '', null, 4)}
            </pre>

            <Box gap={1}>
                <Button
                    variant={'contained'}
                    color={'error'}
                    startIcon={<ApprovalIcon />}
                >
                    {t('quarantine.actions.bypass', 'By pass (accept)')}
                </Button>
                <Button variant={'outlined'} startIcon={<DeleteIcon />}>
                    {t('quarantine.actions.move_to_trash', 'Move to trash')}
                </Button>
            </Box>
        </Box>
    );
}

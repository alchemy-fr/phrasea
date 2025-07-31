import Button from '@mui/material/Button';
import {Chip} from '@mui/material';

import {useTranslation} from 'react-i18next';
import FileCard, {FileCardProps} from './FileCard.tsx';
import CheckIcon from '@mui/icons-material/Check';

type Props = {onCancel: () => void; progress: number} & Omit<
    FileCardProps,
    'actions' | 'progress'
>;

export default function FileProgressCard({
    onCancel,
    progress,
    ...props
}: Props) {
    const {t} = useTranslation();

    return (
        <FileCard
            {...props}
            progress={progress}
            actions={
                progress < 1 ? (
                    <Button size="small" color="error" onClick={onCancel}>
                        {t('upload.pending.cancel_upload', `Cancel`)}
                    </Button>
                ) : (
                    <Chip
                        label={t('upload.pending.uploaded', 'Uploaded')}
                        color="success"
                        icon={<CheckIcon />}
                    />
                )
            }
        />
    );
}

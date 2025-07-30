import FileCard, {FileCardProps} from './FileCard.tsx';
import Button from '@mui/material/Button';
import {useTranslation} from 'react-i18next';

type Props = {onRemove: () => void} & Omit<FileCardProps, 'actions'>;

export default function FileToUploadCard({onRemove, ...props}: Props) {
    const {t} = useTranslation();

    return (
        <FileCard
            {...props}
            actions={
                <Button size="small" color="error" onClick={onRemove}>
                    {t('common.remove', `Remove`)}
                </Button>
            }
        />
    );
}

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Asset} from '../../../../types';
import {Typography} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import UploadDropzone from '../../../Upload/UploadDropzone.tsx';
import CloudUploadIcon from '@mui/icons-material/CloudUpload';

type Props = {
    asset: Asset;
} & StackedModalProps;

export default function SubstituteFileDialog({open, modalIndex}: Props) {
    const {t} = useTranslation();
    const [loading, setLoading] = useState(false);
    const {closeModal} = useModals();

    const onDrop = (_files: File[]) => {
        setLoading(true);
        try {
            // TODO
            closeModal();
        } finally {
            setLoading(false);
        }
    };

    return (
        <FormDialog
            modalIndex={modalIndex}
            open={open}
            title={t('substitute_file.dialog.title', 'Substitute File')}
            loading={loading}
            formId={'sub'}
            submitIcon={<CloudUploadIcon />}
            submitLabel={t('substitute_file.dialog.submit', 'Substitute')}
        >
            <Typography sx={{mb: 3}}>
                {t('substitute_file.dialog.intro', 'Drop Here')}
            </Typography>
            <UploadDropzone onDrop={onDrop} multiple={false} />
        </FormDialog>
    );
}

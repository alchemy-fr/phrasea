import {Alert, AlertTitle, Typography, Button} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {
    onRestart: () => void;
};

export default function UploadDone({onRestart}: Props) {
    const {t} = useTranslation();
    return (
        <>
            <Alert
                severity="success"
                sx={{
                    p: 3,
                }}
                action={
                    <Button
                        color={'success'}
                        variant={'contained'}
                        onClick={onRestart}
                    >
                        {t('upload_done.go_back_home', `Go back Home`)}
                    </Button>
                }
            >
                <AlertTitle>
                    {t('upload_done.you_re_done', `You're done!`)}
                </AlertTitle>
                <Typography variant={'body1'}>
                    {t(
                        'upload_done.all_files_have_been_uploaded',
                        `All files have been uploaded.`
                    )}
                </Typography>
            </Alert>
        </>
    );
}

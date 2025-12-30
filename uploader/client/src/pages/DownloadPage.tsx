import React from 'react';
import {getPath, Link, useNavigate, useParams} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import {useTranslation} from 'react-i18next';
import {Alert, Box, Button, Container, Typography} from '@mui/material';
import AssetForm from '../components/Upload/AssetForm.tsx';
import {LiFormSchema} from '../types.ts';
import LeftArrowIcon from '@mui/icons-material/ArrowBackIosNewOutlined';

type Props = {};

export default function DownloadPage({}: Props) {
    const {t} = useTranslation();
    const [done, setDone] = React.useState(false);
    const navigate = useNavigate();
    const {id} = useParams();

    const baseSchema: LiFormSchema = {
        required: ['url'],
        properties: {
            url: {
                title: t('download.file_url', 'File URL'),
                type: 'string',
                widget: 'url',
            },
        },
    };

    return (
        <Container>
            <Box
                sx={{
                    py: 2,
                    mb: 3,
                    display: 'flex',
                    flexDirection: 'row',
                    alignItems: 'center',
                    gap: 3,
                }}
            >
                <Button
                    startIcon={<LeftArrowIcon />}
                    component={Link}
                    to={getPath(routes.index)}
                >
                    {t('download.back', `Back`)}
                </Button>
                <div
                    style={{
                        flexGrow: 1,
                    }}
                >
                    <Typography variant={'h1'}>
                        {t('download.download_asset', 'Download Asset')}
                    </Typography>
                    <Typography variant={'subtitle1'}>
                        {t(
                            'download.fill_the_form_to_download_your_asset',
                            'Fill the form to download your asset.'
                        )}
                    </Typography>
                </div>
            </Box>

            {done ? (
                <Alert
                    severity={'success'}
                    action={
                        <Button
                            color={'success'}
                            variant={'contained'}
                            onClick={() => setDone(false)}
                        >
                            {t('download.done.restart', `Download Another`)}
                        </Button>
                    }
                >
                    {t(
                        'download.done.your_file_will_be_downloaded',
                        `Your file will be downloaded!`
                    )}
                </Alert>
            ) : (
                <AssetForm
                    targetId={id!}
                    submitPath={'/downloads'}
                    baseSchema={baseSchema}
                    onComplete={() => setDone(true)}
                    onCancel={() => navigate(getPath(routes.index))}
                />
            )}
        </Container>
    );
}

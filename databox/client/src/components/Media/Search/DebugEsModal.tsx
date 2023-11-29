import {ESDebug} from '../../../api/asset';
import {Box, Button, Chip} from '@mui/material';
import AppDialog from '../../Layout/AppDialog';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '../../../hooks/useModalStack';

type Props = {
    debug: ESDebug;
} & StackedModalProps;

function Metric({n}: {n: number}) {
    return (
        <Chip
            label={
                <>
                    <Box
                        sx={{
                            display: 'inline-block',
                            fontWeight: 700,
                        }}
                        component={'code'}
                    >
                        {n}
                    </Box>
                    ms
                </>
            }
        />
    );
}

export default function DebugEsModal({debug, open}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    return (
        <AppDialog
            open={open}
            title={
                <>
                    Search Debug |{' '}
                    <small>
                        Elasticsearch response time:{' '}
                        <Metric n={Math.round(debug.esQueryTime * 1000)} /> |
                        Total response time:{' '}
                        <Metric
                            n={
                                Math.round(debug.totalResponseTime * 1000) /
                                1000
                            }
                        />
                    </small>
                </>
            }
            onClose={closeModal}
            actions={({onClose}) => (
                <>
                    <Button autoFocus onClick={onClose} color="primary">
                        {t('modal.close', 'Close')}
                    </Button>
                </>
            )}
        >
            <Box
                component={'pre'}
                sx={{
                    margin: 0,
                    fontSize: 13,
                }}
            >
                {JSON.stringify(debug.query, undefined, 2)}
            </Box>
        </AppDialog>
    );
}

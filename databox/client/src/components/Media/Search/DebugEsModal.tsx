import {ESDebug} from '../../../api/asset';
import {Box, Button, Chip} from '@mui/material';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import CopyToClipboard from '../../../lib/CopyToClipboard';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import CloseIcon from '@mui/icons-material/Close';

type Props = {
    debug: ESDebug;
} & StackedModalProps;

function Metric({n}: {n: number}) {
    const {t} = useTranslation();
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
                    {t('metric.ms', `ms`)}</>
            }
        />
    );
}

export default function DebugEsModal({debug, open, modalIndex}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    return (
        <AppDialog
            modalIndex={modalIndex}
            open={open}
            title={
                <>
                    {t('debug_es_modal.search_debug', `Search Debug |`)}{' '}
                    <small>
                        {t('debug_es_modal.elasticsearch_response_time', `Elasticsearch response time:`)}{' '}
                        <Metric n={Math.round(debug.esQueryTime * 1000)} /> |
                        {t('debug_es_modal.total_response_time', `Total response time:`)}{' '}
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
                    <CopyToClipboard>
                        {({copy}) => (
                            <Button
                                startIcon={<ContentCopyIcon />}
                                onMouseDown={e => e.stopPropagation()}
                                onClick={e => {
                                    e.stopPropagation();
                                    copy(
                                        JSON.stringify(
                                            debug.query,
                                            undefined,
                                            2
                                        )
                                    );
                                }}
                            >
                                {t('common.copy', `Copy`)}</Button>
                        )}
                    </CopyToClipboard>
                    <Button
                        startIcon={<CloseIcon />}
                        autoFocus
                        onClick={onClose}
                        color="primary"
                    >
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

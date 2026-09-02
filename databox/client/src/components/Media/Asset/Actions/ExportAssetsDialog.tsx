import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {exportAssets} from '../../../../api/export';
import {Asset, RenditionDefinition, Workspace} from '../../../../types';
import {FormRow} from '@alchemy/react-form';
import {
    Box,
    Button,
    Checkbox,
    FormControlLabel,
    Typography,
} from '@mui/material';
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import {FormFieldErrors} from '@alchemy/react-form';
import {getRenditionDefinitions} from '../../../../api/rendition';
import FormDialog from '../../../Dialog/FormDialog';
import {useFormSubmit} from '@alchemy/api';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {RemoteErrors} from '@alchemy/react-form';
import {downloadUrl} from '@alchemy/core';
import {useAssetExportStore} from '../../../../store/assetExportStore.ts';
import {getWorkspace, signWorkspaceTerms} from '../../../../api/workspace.ts';

type Props = {
    assets: Asset[];
} & StackedModalProps;

type FormData = {
    renditions: string[];
};

type IndexedDefinition = {
    [workspaceId: string]: {
        name: string;
        defs: RenditionDefinition[];
    };
};

type UnsignedTerms = {
    workspaceId: string;
    workspaceName: string;
    text: string | null;
    pdfUrl: string | null;
    version: number;
};

export default function ExportAssetsDialog({assets, ...modalProps}: Props) {
    const {t} = useTranslation();
    const [definitions, setDefinitions] = useState<IndexedDefinition>();
    const [unsignedTerms, setUnsignedTerms] = useState<UnsignedTerms[]>();
    const [acceptedTerms, setAcceptedTerms] = useState<
        Record<string, boolean>
    >({});
    const [loading, setLoading] = useState(false);
    const {closeModal} = useModals();
    const addExport = useAssetExportStore(state => state.addExport);

    const count = assets.length;

    useEffect(() => {
        const workspaceIds = assets
            .map(a => a.workspace.id)
            .filter((value, index, self) => self.indexOf(value) === index);

        getRenditionDefinitions({
            workspaceIds,
        }).then(defs => {
            const index: IndexedDefinition = {};

            defs.result.forEach(rd => {
                const ws = rd.workspace as Workspace;
                // eslint-disable-next-line no-prototype-builtins
                if (!index.hasOwnProperty(ws.id)) {
                    index[ws.id] = {
                        name: ws.name,
                        defs: [],
                    };
                }

                index[ws.id].defs.push(rd);
            });
            setDefinitions(index);
        });

        Promise.all(workspaceIds.map(id => getWorkspace(id))).then(
            workspaces => {
                setUnsignedTerms(
                    workspaces
                        .filter(
                            w =>
                                (w.terms?.text || w.terms?.pdfUrl) &&
                                w.terms.signed === false
                        )
                        .map(w => ({
                            workspaceId: w.id,
                            workspaceName: w.name,
                            text: w.terms!.text ?? null,
                            pdfUrl: w.terms!.pdfUrl ?? null,
                            version: w.terms!.version!,
                        }))
                );
            }
        );
    }, []);

    const {
        register,
        handleSubmit,
        remoteErrors,
        submitting,
        formState: {errors},
        forbidNavigation,
    } = useFormSubmit<any>({
        defaultValues: {
            renditions: [],
        },
        onSubmit: async (data: FormData) => {
            setLoading(true);
            try {
                await Promise.all(
                    (unsignedTerms ?? []).map(ut =>
                        signWorkspaceTerms(ut.workspaceId)
                    )
                );

                const assetExport = await exportAssets({
                    assets: assets.map(a => a.id),
                    renditions: data.renditions,
                });

                addExport(assetExport);
                if (assetExport.downloadUrl) {
                    downloadUrl(assetExport.downloadUrl);
                }
            } finally {
                setLoading(false);
            }
        },
        onSuccess: () => {
            closeModal();
        },
    });
    useDirtyFormPrompt(forbidNavigation, modalProps.modalIndex);

    if (!definitions || !unsignedTerms) {
        return <FullPageLoader />;
    }

    const allTermsAccepted = unsignedTerms.every(
        ut => acceptedTerms[ut.workspaceId]
    );

    const formId = 'export-assets';

    return (
        <FormDialog
            {...modalProps}
            title={t('export.dialog.title', 'Export {{count}} assets', {
                count,
            })}
            loading={loading}
            formId={formId}
            submitIcon={<FileDownloadIcon />}
            submitLabel={t('export.dialog.submit', 'Export')}
            submittable={allTermsAccepted}
        >
            <Typography sx={{mb: 3}}>
                {t(
                    'export.dialog.intro',
                    'Select the renditions you want to export:'
                )}
            </Typography>
            <form id={formId} onSubmit={handleSubmit}>
                {Object.keys(definitions).map(wId => {
                    const workspace = definitions![wId];

                    return (
                        <FormRow key={wId}>
                            <b>{workspace.name}</b>
                            {workspace.defs.map(rd => {
                                return (
                                    <div key={rd.id}>
                                        <FormControlLabel
                                            control={
                                                <Checkbox
                                                    disabled={submitting}
                                                    {...register(
                                                        'renditions[]',
                                                        {
                                                            required: true,
                                                        }
                                                    )}
                                                    defaultChecked={false}
                                                    value={rd.id}
                                                />
                                            }
                                            label={rd.displayName}
                                        />
                                        <FormFieldErrors
                                            field={'renditions[]'}
                                            errors={errors}
                                        />
                                    </div>
                                );
                            })}
                        </FormRow>
                    );
                })}

                {unsignedTerms.map(ut => (
                    <FormRow key={ut.workspaceId}>
                        <Typography variant={'h6'}>
                            {t(
                                'export.dialog.terms.title',
                                'Terms & Conditions — {{workspace}}',
                                {
                                    workspace: ut.workspaceName,
                                }
                            )}
                        </Typography>
                        {ut.pdfUrl ? (
                            <Box sx={{my: 1}}>
                                <Button
                                    variant={'outlined'}
                                    href={ut.pdfUrl}
                                    target={'_blank'}
                                    rel={'noreferrer'}
                                    startIcon={<PictureAsPdfIcon />}
                                >
                                    {t(
                                        'export.dialog.terms.view_pdf',
                                        'Read the Terms & Conditions (PDF)'
                                    )}
                                </Button>
                            </Box>
                        ) : (
                            <Box
                                sx={theme => ({
                                    whiteSpace: 'pre-wrap',
                                    maxHeight: 200,
                                    overflow: 'auto',
                                    border: `1px solid ${theme.palette.divider}`,
                                    borderRadius: 1,
                                    p: 2,
                                    my: 1,
                                })}
                            >
                                {ut.text}
                            </Box>
                        )}
                        <FormControlLabel
                            control={
                                <Checkbox
                                    disabled={submitting}
                                    checked={
                                        acceptedTerms[ut.workspaceId] ?? false
                                    }
                                    onChange={(_e, checked) =>
                                        setAcceptedTerms(p => ({
                                            ...p,
                                            [ut.workspaceId]: checked,
                                        }))
                                    }
                                />
                            }
                            label={t(
                                'export.dialog.terms.accept',
                                'I have read and accept the Terms & Conditions (version {{version}})',
                                {
                                    version: ut.version,
                                }
                            )}
                        />
                    </FormRow>
                ))}

                <RemoteErrors errors={remoteErrors} />
            </form>
        </FormDialog>
    );
}

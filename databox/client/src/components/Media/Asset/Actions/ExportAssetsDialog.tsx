import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {exportAssets} from '../../../../api/export';
import {Asset, RenditionDefinition, Workspace} from '../../../../types';
import {FormRow} from '@alchemy/react-form';
import {Checkbox, FormControlLabel, Typography} from '@mui/material';
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

export default function ExportAssetsDialog({assets, ...modalProps}: Props) {
    const {t} = useTranslation();
    const [definitions, setDefinitions] = useState<IndexedDefinition>();
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

    if (!definitions) {
        return <FullPageLoader />;
    }

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
                <RemoteErrors errors={remoteErrors} />
            </form>
        </FormDialog>
    );
}

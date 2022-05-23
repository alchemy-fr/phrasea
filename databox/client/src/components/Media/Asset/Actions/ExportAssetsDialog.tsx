import React, {useEffect, useState} from 'react';
import {StackedModalProps, useModals} from "@mattjennings/react-modal-stack";
import {useTranslation} from "react-i18next";
import {exportAssets} from "../../../../api/export";
import {Asset, RenditionDefinition} from "../../../../types";
import {useForm} from "react-hook-form";
import FormRow from "../../../Form/FormRow";
import {Checkbox, FormControlLabel, Typography} from "@mui/material";
import FormFieldErrors from "../../../Form/FormFieldErrors";
import {getRenditionDefinitions} from "../../../../api/rendition";
import FormDialog from "../../../Dialog/FormDialog";
import useFormSubmit from "../../../../hooks/useFormSubmit";
import FileDownloadIcon from "@mui/icons-material/FileDownload";
import FullPageLoader from "../../../Ui/FullPageLoader";

type Props = {
    assets: Asset[];
} & StackedModalProps;

type FormData = {
    renditions: string[];
}

type IndexedDefinition = {
    [workspaceId: string]: {
        name: string;
        defs: RenditionDefinition[];
    };
}

export default function ExportAssetsDialog({
                                               assets,
                                           }: Props) {
    const {t} = useTranslation();
    const [definitions, setDefinitions] = useState<IndexedDefinition>();
    const [loading, setLoading] = useState(false);
    const {closeModal} = useModals();

    const count = assets.length;

    useEffect(() => {
        const workspaceIds = assets.map(a => a.workspace.id).filter((value, index, self) => self.indexOf(value) === index);

        getRenditionDefinitions({
            workspaceIds,
        }).then((defs) => {
            const index: IndexedDefinition = {};

            defs.result.forEach(rd => {
                if (!index.hasOwnProperty(rd.workspace.id)) {
                    index[rd.workspace.id] = {
                        name: rd.workspace.name,
                        defs: [],
                    }
                }

                index[rd.workspace.id].defs.push(rd);
            });
            setDefinitions(index);
        });
    }, []);

    const {
        register,
        handleSubmit,
        setError,
        formState: {errors}
    } = useForm<any>({
        defaultValues: {
            renditions: [],
        },
    });

    const {
        handleSubmit: onSubmit,
        errors: remoteErrors,
        submitting,
    } = useFormSubmit({
        onSubmit: async (data: FormData) => {
            setLoading(true);
            const downloadUrl = await exportAssets({
                assets: assets.map(a => a.id),
                renditions: data.renditions,
            });

            const a = document.createElement('a');
            a.href = downloadUrl;
            a.target = '_blank';
            a.style.display = 'none';
            document.body.append(a);
            a.click();

            setTimeout(() => {
                a.remove();
            }, 100);
            setLoading(false);
        }, onSuccess: () => {
            closeModal();
        },
    });

    const formId = 'export';

    if (!definitions) {
        return <FullPageLoader/>;
    }

    return <FormDialog
        title={t('export.dialog.title', 'Export {{count}} assets', {
            count,
        })}
        loading={loading}
        formId={formId}
        submitIcon={<FileDownloadIcon/>}
        submitLabel={t('export.dialog.submit', 'Export')}
    >
        <Typography sx={{mb: 3}}>
            {t('export.dialog.intro', 'Select the renditions you want to export:')}
        </Typography>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
            {Object.keys(definitions).map((wId) => {
                const workspace = definitions![wId];

                return <FormRow key={wId}>
                    <b>{workspace.name}</b>
                    {workspace.defs.map(rd => {
                        return <div key={rd.id}>
                            <FormControlLabel control={
                                <Checkbox
                                    disabled={submitting}
                                    {...register('renditions[]', {
                                        required: true,
                                    })}
                                    value={rd.id}
                                />
                            } label={rd.name}/>
                            <FormFieldErrors
                                field={'renditions[]'}
                                errors={remoteErrors || errors}
                            />
                        </div>
                    })}
                </FormRow>
            })}
        </form>
    </FormDialog>
}

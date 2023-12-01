import React, {useState} from 'react';
import {Box, Grid} from '@mui/material';
import FileCard from './FileCard';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import UploadIcon from '@mui/icons-material/Upload';
import useFormSubmit from '../../hooks/useFormSubmit';
import FormDialog from '../Dialog/FormDialog';
import {UploadData, UploadForm} from './UploadForm';
import {StackedModalProps, useModals} from '../../hooks/useModalStack';
import {useNavigationPrompt} from '../../hooks/useNavigationPrompt';
import {createCollection, submitFiles} from '../../lib/upload/uploader';
import moment from 'moment';
import {v4 as uuidv4} from 'uuid';
import UploadDropzone from './UploadDropzone';
import {CollectionChip, WorkspaceChip} from '../Ui/Chips';
import {CollectionId} from '../Media/Collection/CollectionsTreeView';
import {useAttributeEditor} from '../Media/Asset/Attribute/useAttributeEditor';
import {useAssetDataTemplateOptions} from '../Media/Asset/Attribute/useAssetDataTemplateOptions';
import {
    AssetDataTemplate,
    postAssetDataTemplate,
    putAssetDataTemplate,
} from '../../api/templates';
import {getBatchActions} from '../Media/Asset/Attribute/BatchActions';

type FileWrapper = {
    id: string;
    file: File;
};

type Props = {
    files: File[];
    userId: string;
    workspaceId?: string;
    collectionId?: string;
    titlePath?: string[];
    workspaceTitle?: string;
} & StackedModalProps;

export default function UploadModal({
    files: initFiles,
    userId,
    workspaceId: initWsId,
    open,
    workspaceTitle,
    collectionId: initCollectionId,
    titlePath,
}: Props) {
    const {t} = useTranslation();
    const [workspaceId, setWorkspaceId] = React.useState<string | undefined>(
        initWsId
    );
    const [collectionId, setCollectionId] = React.useState<string | undefined>(
        initCollectionId
    );
    const [files, setFiles] = useState<FileWrapper[]>(
        initFiles.map(f => ({
            file: f,
            id: uuidv4().toString(),
        }))
    );
    const {closeModal} = useModals();
    useNavigationPrompt(
        'Are you sure you want to dismiss upload?',
        files.length > 0
    );

    const usedAttributeEditor = useAttributeEditor({
        workspaceId,
    });

    const usedAssetDataTemplateOptions = useAssetDataTemplateOptions();

    const {submitting, submitted, handleSubmit, errors} = useFormSubmit({
        onSubmit: async (data: UploadData) => {
            if (typeof data.destination === 'object') {
                data.destination = await createCollection(data.destination);
            }

            const attributes = usedAttributeEditor.attributes
                ? getBatchActions(
                      usedAttributeEditor.attributes,
                      usedAttributeEditor.definitionIndex!
                  )
                : undefined;

            const {saveAsTemplate, usedForm} = usedAssetDataTemplateOptions;
            if (saveAsTemplate) {
                const options = usedForm.getValues();
                const tplData: Partial<AssetDataTemplate> = {
                    name: options.name,
                    attributes,
                    privacy: options.rememberPrivacy ? data.privacy : undefined,
                    collection: options.rememberCollection
                        ? data.destination
                        : undefined,
                    includeCollectionChildren:
                        options.includeCollectionChildren,
                    tags: options.rememberTags ? data.tags : undefined,
                    workspace: `/workspaces/${workspaceId}`,
                    public: options.public,
                };

                if (
                    await usedForm.trigger(undefined, {
                        shouldFocus: true,
                    })
                ) {
                    if (options.id && options.override) {
                        await putAssetDataTemplate(options.id, tplData);
                    } else {
                        await postAssetDataTemplate(tplData);
                    }
                } else {
                    throw new Error('Form contains errors');
                }
            }

            return await submitFiles(userId, {
                files: files.map(f => ({
                    file: f.file,
                    tags: data.tags,
                    title:
                        f.file.name === 'image.png'
                            ? createPastedImageTitle()
                            : f.file.name,
                    destination: collectionId
                        ? `/collections/${collectionId}`
                        : (data.destination as CollectionId),
                    privacy: data.privacy,
                    attributes,
                })),
            });
        },
        onSuccess: () => {
            toast.success(
                t('form.upload.success', 'Files uploaded!') as string
            );
            closeModal(true);
        },
    });

    const onDrop = (acceptedFiles: File[]) => {
        setFiles(p =>
            acceptedFiles
                .map(file => ({
                    id: uuidv4().toString(),
                    file,
                }))
                .concat(p)
        );
    };

    const onFileRemove = (id: string) => {
        setFiles(p => p.filter(f => f.id !== id));
    };

    const formId = 'upload';

    const title = workspaceTitle ? (
        titlePath ? (
            <>
                {t('form.asset_create.title_with_parent', 'Create asset under')}{' '}
                <WorkspaceChip label={workspaceTitle} />
                {titlePath.map((t, i) => (
                    <React.Fragment key={i}>
                        {' / '}
                        <CollectionChip label={t} />
                    </React.Fragment>
                ))}
            </>
        ) : (
            <>
                {t('form.asset_create.title', 'Create asset in')}{' '}
                <WorkspaceChip label={workspaceTitle} />
            </>
        )
    ) : undefined;

    return (
        <FormDialog
            title={title ?? t('form.upload.title', 'Upload')}
            formId={formId}
            open={open}
            loading={submitting}
            errors={errors}
            submitIcon={<UploadIcon />}
            submitLabel={t('form.upload.submit.title', 'Upload')}
            submittable={files.length > 0}
        >
            <UploadDropzone onDrop={onDrop} />
            {files.length > 0 && (
                <Box
                    sx={theme => ({
                        bgcolor: theme.palette.grey[100],
                        maxHeight: 400,
                        overflow: 'auto',
                        p: 1,
                    })}
                >
                    <Grid
                        container
                        rowSpacing={1}
                        columnSpacing={{xs: 1, sm: 2, md: 3}}
                    >
                        {files.map(f => (
                            <Grid item xs={12} md={6} key={f.id}>
                                <FileCard
                                    file={f.file}
                                    onRemove={() => onFileRemove(f.id)}
                                />
                            </Grid>
                        ))}
                    </Grid>
                </Box>
            )}
            <UploadForm
                formId={formId}
                workspaceId={workspaceId}
                collectionId={collectionId}
                onSubmit={handleSubmit}
                onChangeWorkspace={setWorkspaceId}
                onChangeCollection={setCollectionId}
                submitting={submitting}
                submitted={submitted}
                noDestination={Boolean(workspaceTitle)}
                usedAttributeEditor={usedAttributeEditor}
                usedAssetDataTemplateOptions={usedAssetDataTemplateOptions}
            />
        </FormDialog>
    );
}

function createPastedImageTitle(): string {
    const m = moment();

    return `Pasted-image-${m.format('YYYY-MM-DD_HH-mm-ss')}`;
}

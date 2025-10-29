import React, {useState} from 'react';
import {Box, Button, Checkbox, InputLabel, TextField} from '@mui/material';
import {Trans, useTranslation} from 'react-i18next';
import UploadIcon from '@mui/icons-material/Upload';
import {useFormSubmit} from '@alchemy/api';
import FormDialog from '../Dialog/FormDialog';
import {FormUploadData, UploadData, UploadForm} from './UploadForm';
import moment from 'moment';
import {v4 as uuidv4} from 'uuid';
import UploadDropzone from './UploadDropzone';
import {useAttributeEditor} from '../Media/Asset/Attribute/useAttributeEditor';
import {useAssetDataTemplateOptions} from '../Media/Asset/Attribute/useAssetDataTemplateOptions';
import {
    AssetDataTemplate,
    postAssetDataTemplate,
    putAssetDataTemplate,
} from '../../api/templates';
import {StackedModalProps, useFormPrompt, useModals} from '@alchemy/navigation';
import {Privacy} from '../../api/privacy';
import {Asset, AssetTypeFilter} from '../../types';
import {getAttributeList} from '../Media/Asset/Attribute/AttributeListData.ts';
import type {TFunction} from '@alchemy/i18n';
import {CollectionId} from '../Media/Collection/CollectionTree/collectionTree.ts';
import {WorkspaceChip} from '../Ui/WorkspaceChip.tsx';
import {CollectionChip} from '../Ui/CollectionChip.tsx';
import FileToUploadCard from './FileToUploadCard.tsx';
import DeleteIcon from '@mui/icons-material/Delete';
import {createCollection} from '../../api/collection.ts';
import {importAssets, NewAssetInput, uploadAssets} from '../../api/asset.ts';
import LinkIcon from '@mui/icons-material/Link';
import {validateUrl} from '../../lib/file.ts';
import {CreateAssetsOptions} from '../../api/file.ts';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import {toast} from 'react-toastify';

type FileWrapper = {
    id: string;
    file: File;
};

type Props = {
    files: File[];
    workspaceId?: string;
    collectionId?: string;
    titlePath?: string[];
    workspaceTitle?: string;
} & StackedModalProps;

export default function UploadModal({
    files: initFiles,
    workspaceId: initWsId,
    open,
    workspaceTitle,
    collectionId: initCollectionId,
    titlePath,
    modalIndex,
}: Props) {
    const [urlMode, setUrlMode] = React.useState(false);
    const [url, setUrl] = React.useState('');
    const [importFiles, setImportFiles] = React.useState(false);
    const {t} = useTranslation();
    const [workspaceId, setWorkspaceId] = React.useState<string | undefined>(
        initWsId
    );
    const [collectionId, setCollectionId] = React.useState<string | undefined>(
        initCollectionId
    );
    const [files, setFiles] = useState<FileWrapper[]>(
        initFiles.map((f: File) => ({
            file: f,
            id: uuidv4().toString(),
        }))
    );
    const {closeModal} = useModals();
    useFormPrompt(t, files.length > 0, modalIndex);

    const usedAttributeEditor = useAttributeEditor({
        workspaceId,
        target: AssetTypeFilter.Asset,
    });

    const usedStoryAttributeEditor = useAttributeEditor({
        workspaceId,
        target: AssetTypeFilter.Story,
    });

    const usedAssetDataTemplateOptions = useAssetDataTemplateOptions();

    const defaultValues: FormUploadData = {
        destination: '',
        privacy: Privacy.Secret,
        tags: [],
        quiet: false,
        isStory: false,
        story: {
            tags: [],
        },
    };

    const usedFormSubmit = useFormSubmit<UploadData, Asset[], FormUploadData>({
        defaultValues,
        normalize: data => {
            return {
                ...data,
                tags: data.tags.map(t => t['@id']),
                story: data.isStory
                    ? {
                          ...data.story,
                          tags: data.story.tags.map(t => t['@id']) ?? [],
                      }
                    : undefined,
            };
        },
        onSubmit: async (data: UploadData) => {
            const {quiet, isStory, story} = data;

            if (typeof data.destination === 'object') {
                data.destination = await createCollection(data.destination);
            }

            const attributes = usedAttributeEditor.attributes
                ? getAttributeList(
                      usedAttributeEditor.attributes,
                      usedAttributeEditor.definitionIndex!
                  )
                : undefined;

            const storyAttributes =
                isStory && usedStoryAttributeEditor.attributes
                    ? getAttributeList(
                          usedStoryAttributeEditor.attributes,
                          usedStoryAttributeEditor.definitionIndex!
                      )
                    : undefined;

            const {saveAsTemplate, usedForm} = usedAssetDataTemplateOptions;
            if (saveAsTemplate) {
                const options = usedForm.getValues();
                const tplData: Partial<AssetDataTemplate> = {
                    name: options.name,
                    attributes,
                    privacy: options.rememberPrivacy ? data.privacy : undefined,
                    collection:
                        options.rememberCollection &&
                        data.destination?.startsWith('/collections/')
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

            const destination = collectionId
                ? `/collections/${collectionId}`
                : (data.destination as CollectionId);

            const assetOptions: CreateAssetsOptions = {
                quiet,
                isStory,
                story: isStory
                    ? {
                          ...story,
                          tags: story?.tags as unknown as string[],
                          attributes: storyAttributes,
                      }
                    : undefined,
            };

            const assetBase: Partial<NewAssetInput> = {
                tags: data.tags as unknown as string[],
                privacy: data.privacy,
                attributes,
            };

            if (urlMode) {
                const urls = parseUrls(url);
                const assets = await importAssets(
                    urls.map(u => ({
                        url: u,
                        importFile: importFiles,
                        asset: {
                            ...assetBase,
                            title: extractTitleFromUrl(u),
                        },
                    })),
                    destination,
                    assetOptions
                );

                toast.success(
                    t('file_upload.asset_created', {
                        defaultValue: 'Asset created successfully.',
                        defaultValue_other:
                            '{{count}} assets created successfully.',
                        count: urls.length,
                    })
                );

                return assets;
            } else {
                return await uploadAssets(
                    files.map(f => ({
                        file: f.file,
                        asset: {
                            ...assetBase,
                            title:
                                f.file.name === 'image.png'
                                    ? createPastedImageTitle(t)
                                    : f.file.name.replace(/\.[^/.]+$/, ''),
                        },
                    })),
                    destination,
                    assetOptions
                );
            }
        },
        onSuccess: () => {
            closeModal(true);
        },
    });

    const {reset, getValues, remoteErrors, submitting} = usedFormSubmit;

    const resetForms = React.useCallback(() => {
        reset({
            ...defaultValues,
            destination: getValues().destination,
        });
        usedAttributeEditor.reset();
    }, [usedAttributeEditor]);

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
                <div>
                    {t(
                        'form.asset_create.title_with_parent',
                        'Create Asset under'
                    )}{' '}
                    <WorkspaceChip label={workspaceTitle} />
                    {titlePath.map((t: string, i: number) => (
                        <React.Fragment key={i}>
                            {' / '}
                            <CollectionChip label={t} />
                        </React.Fragment>
                    ))}
                </div>
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
            modalIndex={modalIndex}
            loading={submitting}
            errors={remoteErrors}
            submitIcon={<UploadIcon />}
            submitLabel={t('form.upload.submit.title', 'Upload')}
            submittable={
                !urlMode
                    ? files.length > 0
                    : url.trim() !== '' &&
                      url
                          .split('\n')
                          .filter(u => u.trim() !== '')
                          .every(u => validateUrl(u))
            }
        >
            {!urlMode ? (
                <>
                    <UploadDropzone onDrop={onDrop} />
                    {files.length > 0 && (
                        <>
                            <Box
                                sx={{
                                    mb: 2,
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                }}
                            >
                                <div style={{flexGrow: 1}}>
                                    <Trans
                                        i18nKey={'form.upload.files_to_upload'}
                                        defaults="<strong>{{count}}</strong> file to upload"
                                        values={{count: files.length}}
                                        count={files.length}
                                        tOptions={{
                                            defaultValue_other:
                                                '<strong>{{count}}</strong> files to upload',
                                        }}
                                    />
                                </div>
                                <Button
                                    startIcon={<DeleteIcon />}
                                    variant="outlined"
                                    color="error"
                                    onClick={() => setFiles([])}
                                >
                                    {t('form.upload.reset', 'Reset')}
                                </Button>
                            </Box>
                            <Box
                                sx={theme => ({
                                    'bgcolor': theme.palette.grey[100],
                                    'maxHeight': 400,
                                    'overflow': 'auto',
                                    'p': 1,
                                    'display': 'grid',
                                    'alignItems': 'stretch',
                                    'gridTemplateColumns': {
                                        xs: `repeat(1, 1fr)`,
                                        md: `repeat(2, 1fr)`,
                                    },
                                    'gridColumnGap': theme.spacing(2),
                                    'gridRowGap': theme.spacing(2),
                                    '> div': {
                                        width: '100%',
                                        height: '100%',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        justifyContent: 'space-between',
                                        alignItems: 'stretch',
                                    },
                                })}
                            >
                                {files.map(f => (
                                    <FileToUploadCard
                                        key={f.id}
                                        file={f.file}
                                        onRemove={() => onFileRemove(f.id)}
                                    />
                                ))}
                            </Box>
                        </>
                    )}

                    <Button
                        sx={{
                            mb: 2,
                        }}
                        variant={'text'}
                        onClick={() => setUrlMode(true)}
                        startIcon={<LinkIcon />}
                    >
                        {t('file_upload.upload_from_url', `Upload from URL`)}
                    </Button>
                </>
            ) : (
                <>
                    <Button
                        variant={'text'}
                        onClick={() => setUrlMode(false)}
                        startIcon={<KeyboardArrowLeftIcon />}
                        sx={{
                            mb: 2,
                        }}
                    >
                        {t('file_upload.back_to_files_upload', `Upload files`)}
                    </Button>

                    <TextField
                        fullWidth
                        type="url"
                        multiline={true}
                        placeholder={t(
                            'file_upload.from_urls.placeholder',
                            `Enter file URL(s), one per line`
                        )}
                        value={url}
                        onChange={e => setUrl(e.target.value)}
                        error={Boolean(
                            url && parseUrls(url).some(u => !validateUrl(u))
                        )}
                    />

                    <InputLabel>
                        <Checkbox
                            checked={importFiles}
                            onChange={(_e, checked) => setImportFiles(checked)}
                        />
                        {t('file_upload.import_file', `Import file(s)`)}
                    </InputLabel>
                </>
            )}

            <UploadForm
                resetForms={resetForms}
                usedFormSubmit={usedFormSubmit}
                formId={formId}
                workspaceId={workspaceId}
                collectionId={collectionId}
                onChangeWorkspace={setWorkspaceId}
                onChangeCollection={setCollectionId}
                noDestination={Boolean(workspaceTitle)}
                usedAttributeEditor={usedAttributeEditor}
                usedStoryAttributeEditor={usedStoryAttributeEditor}
                usedAssetDataTemplateOptions={usedAssetDataTemplateOptions}
                modalIndex={modalIndex}
            />
        </FormDialog>
    );
}

function createPastedImageTitle(t: TFunction): string {
    return t('pasted_image.filename', {
        defaultValue: `Pasted-image-{{date}}`,
        date: moment().format('YYYY-MM-DD_HH-mm-ss'),
    });
}

function extractTitleFromUrl(url: string): string {
    const s = url.split('/').filter(Boolean);
    return s[s.length - 1];
}

function parseUrls(urls: string): string[] {
    return urls
        .split('\n')
        .map(u => u.trim())
        .filter(u => u !== '');
}

import React from 'react';
import Cropper, {Area} from 'react-easy-crop';
import {
    Box,
    FormGroup,
    FormLabel,
    MenuItem,
    Slider,
    TextField,
} from '@mui/material';
import AddPhotoAlternateIcon from '@mui/icons-material/AddPhotoAlternate';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {useModals, StackedModalProps, useFormPrompt} from '@alchemy/navigation';
import {useFormSubmit} from '@alchemy/api';
import {
    CheckboxWidget,
    FormFieldErrors,
    FormRow,
    RemoteErrors,
} from '@alchemy/react-form';
import FormDialog from '../../FormDialog';
import {Asset, AssetRendition} from '../../../../types';
import {postRendition} from '../../../../api/rendition';

type FormData = {
    name: string;
    source: string; // '' => asset source file, else a rendition ID
    crop: boolean;
    maxWidth: string;
    maxHeight: string;
    bw: boolean;
    format: string;
    writeMetadata: boolean;
};

type Props = {
    asset: Asset;
    renditions: AssetRendition[];
    onCreated?: (rendition: AssetRendition) => void;
} & StackedModalProps;

const sourceFileKey = '';
const keepFormat = 'keep';
const originalAspect = 0;
const customAspect = -1;

export default function CreateDynamicRenditionDialog({
    asset,
    renditions,
    onCreated,
    ...modalProps
}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    const [cropPos, setCropPos] = React.useState({x: 0, y: 0});
    const [zoom, setZoom] = React.useState(1);
    const [aspect, setAspect] = React.useState<number>(originalAspect);
    const [customRatio, setCustomRatio] = React.useState({w: 16, h: 9});
    const [mediaAspect, setMediaAspect] = React.useState<number>(1);
    const [croppedAreaPixels, setCroppedAreaPixels] =
        React.useState<Area | null>(null);

    const isImageFile = (file: Asset['source']) =>
        Boolean(file?.url && file.type?.startsWith('image/'));

    // The generated build specification only covers the "image" family
    const readyRenditions = renditions.filter(
        r => r.ready && isImageFile(r.file)
    );
    const sourceFileAvailable = isImageFile(asset.source);
    const defaultSource = sourceFileAvailable
        ? sourceFileKey
        : (readyRenditions[0]?.id ?? sourceFileKey);

    const {
        register,
        control,
        watch,
        formState: {errors},
        handleSubmit,
        remoteErrors,
        submitting,
        forbidNavigation,
    } = useFormSubmit({
        defaultValues: {
            name: '',
            source: defaultSource,
            crop: false,
            maxWidth: '',
            maxHeight: '',
            bw: false,
            format: keepFormat,
            writeMetadata: false,
        },
        onSubmit: async (data: FormData) => {
            return await postRendition({
                assetId: asset.id,
                name: data.name,
                buildDefinition: createBuildDefinition(data, croppedAreaPixels),
                writeMetadata: data.writeMetadata,
                sourceRenditionId: data.source || undefined,
            });
        },
        onSuccess: rendition => {
            toast.success(
                t(
                    'create_rendition_dialog.rendition_is_being_generated',
                    `Rendition is being generated…`
                )
            );
            onCreated?.(rendition);
            closeModal();
        },
    });
    useFormPrompt(t, forbidNavigation, modalProps.modalIndex);

    const sourceId = watch('source');
    const cropEnabled = watch('crop');
    const bw = watch('bw');

    const sourceFile = sourceId
        ? readyRenditions.find(r => r.id === sourceId)?.file
        : asset.source;
    const croppable = Boolean(
        sourceFile?.url && sourceFile.type?.startsWith('image/')
    );
    const previewFilter = bw ? 'grayscale(100%)' : undefined;
    const resolvedAspect =
        aspect === originalAspect
            ? mediaAspect
            : aspect === customAspect
              ? customRatio.w > 0 && customRatio.h > 0
                  ? customRatio.w / customRatio.h
                  : mediaAspect
              : aspect;

    const formId = 'create-dynamic-rendition';

    return (
        <FormDialog
            {...modalProps}
            title={t(
                'create_rendition_dialog.create_custom_rendition',
                `Create custom rendition`
            )}
            loading={submitting}
            formId={formId}
            submitIcon={<AddPhotoAlternateIcon />}
            submitLabel={t('create_rendition_dialog.generate', `Generate`)}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        label={t('create_rendition_dialog.name.label', 'Name')}
                        disabled={submitting}
                        fullWidth={true}
                        {...register('name', {
                            required: true,
                        })}
                    />
                    <FormFieldErrors field={'name'} errors={errors} />
                </FormRow>
                <FormRow>
                    <TextField
                        select={true}
                        label={t(
                            'create_rendition_dialog.source.label',
                            'Source'
                        )}
                        disabled={submitting}
                        fullWidth={true}
                        defaultValue={defaultSource}
                        {...register('source')}
                    >
                        {sourceFileAvailable && (
                            <MenuItem value={sourceFileKey}>
                                {t(
                                    'create_rendition_dialog.source.source_file',
                                    'Source file'
                                )}
                            </MenuItem>
                        )}
                        {readyRenditions.map(r => (
                            <MenuItem key={r.id} value={r.id}>
                                {r.displayName}
                            </MenuItem>
                        ))}
                    </TextField>
                    <FormFieldErrors field={'source'} errors={errors} />
                </FormRow>
                {croppable && (
                    <FormRow>
                        <Box
                            sx={{
                                position: 'relative',
                                width: '100%',
                                height: 300,
                                bgcolor: 'grey.900',
                            }}
                        >
                            {cropEnabled ? (
                                <Cropper
                                    image={sourceFile!.url}
                                    crop={cropPos}
                                    zoom={zoom}
                                    aspect={resolvedAspect}
                                    style={{
                                        mediaStyle: {filter: previewFilter},
                                    }}
                                    onCropChange={setCropPos}
                                    onZoomChange={setZoom}
                                    onCropComplete={(_area, areaPixels) =>
                                        setCroppedAreaPixels(areaPixels)
                                    }
                                    onMediaLoaded={mediaSize =>
                                        setMediaAspect(
                                            mediaSize.naturalWidth /
                                                mediaSize.naturalHeight
                                        )
                                    }
                                />
                            ) : (
                                <Box
                                    component={'img'}
                                    src={sourceFile!.url}
                                    alt={''}
                                    sx={{
                                        width: '100%',
                                        height: '100%',
                                        objectFit: 'contain',
                                        filter: previewFilter,
                                    }}
                                />
                            )}
                        </Box>
                    </FormRow>
                )}
                {croppable && (
                    <FormRow>
                        <CheckboxWidget
                            label={t(
                                'create_rendition_dialog.crop.label',
                                'Crop'
                            )}
                            control={control}
                            name={'crop'}
                            disabled={submitting}
                        />
                    </FormRow>
                )}
                {croppable && cropEnabled && (
                    <FormRow>
                        <Box
                            sx={{
                                display: 'flex',
                                gap: 2,
                                alignItems: 'center',
                            }}
                        >
                            <TextField
                                select={true}
                                size={'small'}
                                label={t(
                                    'create_rendition_dialog.crop.aspect',
                                    'Aspect ratio'
                                )}
                                value={aspect}
                                onChange={e =>
                                    setAspect(Number(e.target.value))
                                }
                                sx={{minWidth: 140}}
                            >
                                <MenuItem value={originalAspect}>
                                    {t(
                                        'create_rendition_dialog.crop.aspect_original',
                                        'Original'
                                    )}
                                </MenuItem>
                                <MenuItem value={1}>1:1</MenuItem>
                                <MenuItem value={4 / 3}>4:3</MenuItem>
                                <MenuItem value={3 / 4}>3:4</MenuItem>
                                <MenuItem value={16 / 9}>16:9</MenuItem>
                                <MenuItem value={customAspect}>
                                    {t(
                                        'create_rendition_dialog.crop.aspect_custom',
                                        'Custom'
                                    )}
                                </MenuItem>
                            </TextField>
                            <FormLabel sx={{whiteSpace: 'nowrap'}}>
                                {t('create_rendition_dialog.crop.zoom', 'Zoom')}
                            </FormLabel>
                            <Slider
                                min={1}
                                max={5}
                                step={0.05}
                                value={zoom}
                                onChange={(_e, value) =>
                                    setZoom(value as number)
                                }
                            />
                        </Box>
                        {aspect === customAspect && (
                            <Box
                                sx={{
                                    display: 'flex',
                                    gap: 2,
                                    mt: 2,
                                }}
                            >
                                <TextField
                                    size={'small'}
                                    type={'number'}
                                    fullWidth={true}
                                    label={t(
                                        'create_rendition_dialog.crop.aspect_width',
                                        'Ratio width'
                                    )}
                                    value={customRatio.w}
                                    onChange={e =>
                                        setCustomRatio(r => ({
                                            ...r,
                                            w: Number(e.target.value),
                                        }))
                                    }
                                />
                                <TextField
                                    size={'small'}
                                    type={'number'}
                                    fullWidth={true}
                                    label={t(
                                        'create_rendition_dialog.crop.aspect_height',
                                        'Ratio height'
                                    )}
                                    value={customRatio.h}
                                    onChange={e =>
                                        setCustomRatio(r => ({
                                            ...r,
                                            h: Number(e.target.value),
                                        }))
                                    }
                                />
                            </Box>
                        )}
                    </FormRow>
                )}
                <FormRow>
                    <FormGroup>
                        <FormLabel>
                            {t(
                                'create_rendition_dialog.dimensions.label',
                                'Maximum dimensions (px)'
                            )}
                        </FormLabel>
                        <Box sx={{display: 'flex', gap: 2, mt: 1}}>
                            <TextField
                                label={t(
                                    'create_rendition_dialog.dimensions.width',
                                    'Width'
                                )}
                                type={'number'}
                                disabled={submitting}
                                {...register('maxWidth')}
                            />
                            <TextField
                                label={t(
                                    'create_rendition_dialog.dimensions.height',
                                    'Height'
                                )}
                                type={'number'}
                                disabled={submitting}
                                {...register('maxHeight')}
                            />
                        </Box>
                    </FormGroup>
                </FormRow>
                <FormRow>
                    <TextField
                        select={true}
                        label={t(
                            'create_rendition_dialog.format.label',
                            'Output format'
                        )}
                        disabled={submitting}
                        fullWidth={true}
                        defaultValue={keepFormat}
                        {...register('format')}
                    >
                        <MenuItem value={keepFormat}>
                            {t(
                                'create_rendition_dialog.format.keep',
                                'Same as source'
                            )}
                        </MenuItem>
                        <MenuItem value={'jpeg'}>JPEG</MenuItem>
                        <MenuItem value={'png'}>PNG</MenuItem>
                        <MenuItem value={'webp'}>WebP</MenuItem>
                    </TextField>
                </FormRow>
                <FormRow>
                    <CheckboxWidget
                        label={t(
                            'create_rendition_dialog.bw.label',
                            'Black & white'
                        )}
                        control={control}
                        name={'bw'}
                        disabled={submitting}
                    />
                </FormRow>
                <FormRow>
                    <CheckboxWidget
                        label={t(
                            'create_rendition_dialog.write_metadata.label',
                            'Write attributes into file metadata'
                        )}
                        control={control}
                        name={'writeMetadata'}
                        disabled={submitting}
                    />
                </FormRow>
            </form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}

function createBuildDefinition(
    data: FormData,
    croppedAreaPixels: Area | null
): string {
    const filters: Record<string, unknown> = {};

    if (data.crop && croppedAreaPixels) {
        filters.crop = {
            start: [
                Math.round(croppedAreaPixels.x),
                Math.round(croppedAreaPixels.y),
            ],
            size: [
                Math.round(croppedAreaPixels.width),
                Math.round(croppedAreaPixels.height),
            ],
        };
    }

    const maxWidth = parseInt(data.maxWidth) || 0;
    const maxHeight = parseInt(data.maxHeight) || 0;
    if (maxWidth > 0 && maxHeight > 0) {
        filters.thumbnail = {
            size: [maxWidth, maxHeight],
            mode: 'inset',
        };
    } else if (maxWidth > 0) {
        filters.relative_resize = {widen: maxWidth};
    } else if (maxHeight > 0) {
        filters.relative_resize = {heighten: maxHeight};
    }

    if (data.bw) {
        filters.grayscale = null;
    }

    const options: Record<string, unknown> = {filters};
    if (data.format !== keepFormat) {
        options.format = data.format;
    }

    // JSON is a subset of YAML: the API parses this as a rendition definition
    return JSON.stringify({
        image: {
            transformations: [
                {
                    module: 'imagine',
                    options,
                },
            ],
        },
    });
}

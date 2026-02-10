import {Asset, AssetTypeFilter, Tag} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {
    AssetApiInput,
    attributeBatchUpdate,
    putAsset,
} from '../../../api/asset';
import {Privacy} from '../../../api/privacy';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {
    FormGroup,
    FormLabel,
    InputLabel,
    Skeleton,
    TextField,
} from '@mui/material';
import TagSelect from '../../Form/TagSelect';
import PrivacyField from '../../Ui/PrivacyField';
import {useAssetStore} from '../../../store/assetStore.ts';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {useAttributeEditor} from '../../Media/Asset/Attribute/useAttributeEditor.ts';
import React from 'react';
import AttributesEditor from '../../Media/Asset/Attribute/AttributesEditor.tsx';

type Props = {
    id: string;
    data: Asset;
} & DialogTabProps;

export default function EditAsset({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const assetTypeFilter = data.storyCollection
        ? AssetTypeFilter.Story
        : AssetTypeFilter.Asset;

    const formId = 'edit-asset';
    const updateAsset = useAssetStore(s => s.update);

    const [error, setError] = React.useState<string | undefined>();

    const {
        getActions,
        onChangeHandler,
        attributes,
        definitionIndex,
        reloadAssetAttributes,
        dirty: attributesDirty,
    } = useAttributeEditor({
        workspaceId: data.workspace.id,
        assetId: data.id,
        target: assetTypeFilter,
    });

    const saveAttributes = React.useCallback(async () => {
        const actions = getActions();
        try {
            if (actions.length > 0) {
                await attributeBatchUpdate(data.id, actions);
            }
            await reloadAssetAttributes(data.id);

            if (error) {
                setError(undefined);
            }
        } catch (e: any) {
            console.error('e', e);
            if (e.response && typeof e.response.data === 'object') {
                const data = e.response.data;
                setError(
                    `${data['hydra:title']}: ${data['hydra:description']}`
                );
            } else {
                setError(e.toString());
            }
        }
    }, [getActions]);

    const {
        register,
        control,
        formState: {errors, isDirty: editDirty},
        submitting,
        handleSubmit,
        remoteErrors,
        forbidNavigation,
    } = useFormSubmit<Asset>({
        defaultValues: data
            ? {
                  title: data.title,
                  privacy: data.privacy,
                  tags: (data?.tags?.map(t => t['@id']) ??
                      []) as unknown as Tag[],
              }
            : {
                  title: '',
                  privacy: Privacy.Secret,
                  tags: [] as Tag[],
              },
        onSubmit: async d => {
            if (attributesDirty) {
                await saveAttributes();
            }

            if (editDirty) {
                const asset = await putAsset(
                    data.id,
                    d as unknown as AssetApiInput
                );
                updateAsset(asset);
                return asset;
            }

            return data;
        },
        onSuccess: () => {
            toast.success(
                t('form.asset_edit.success', 'Asset edited!') as string
            );
            onClose();
        },
    });

    useDirtyFormPrompt(!submitting && (attributesDirty || forbidNavigation));

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            minHeight={minHeight}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        autoFocus
                        required={true}
                        label={t('form.asset.title.label', 'Title')}
                        disabled={submitting}
                        {...register('title', {
                            required: true,
                        })}
                    />
                    <FormFieldErrors field={'title'} errors={errors} />
                </FormRow>
                <FormRow>
                    <FormGroup>
                        <InputLabel>
                            {t('form.asset.tags.label', 'Tags')}
                        </InputLabel>
                        <TagSelect
                            multiple={true}
                            workspaceId={data.workspace.id}
                            control={control}
                            name={'tags'}
                        />
                        <FormFieldErrors<Asset>
                            field={'tags'}
                            errors={errors}
                        />
                    </FormGroup>
                </FormRow>
                <FormRow>
                    <PrivacyField control={control} name={'privacy'} />
                </FormRow>
            </form>
            {data.capabilities.canEditAttributes ? (
                attributes && definitionIndex ? (
                    <AttributesEditor
                        attributes={attributes}
                        definitions={definitionIndex}
                        disabled={submitting}
                        onChangeHandler={onChangeHandler}
                        assetTypeFilter={assetTypeFilter}
                    />
                ) : (
                    <>
                        {[0, 1, 2].map(x => (
                            <React.Fragment key={x}>
                                <FormRow>
                                    <FormLabel>
                                        <Skeleton
                                            width={'200'}
                                            variant={'text'}
                                            style={{
                                                display: 'inline-block',
                                                width: '200px',
                                            }}
                                        />
                                    </FormLabel>
                                    <Skeleton
                                        width={'100%'}
                                        height={56}
                                        variant={'rectangular'}
                                        sx={{
                                            mb: 2,
                                        }}
                                    />
                                </FormRow>
                            </React.Fragment>
                        ))}
                    </>
                )
            ) : null}
        </FormTab>
    );
}

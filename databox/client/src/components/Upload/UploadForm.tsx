import React, {FC} from 'react';
import {useTranslation} from 'react-i18next';
import {FormRow, SelectOption} from '@alchemy/react-form';
import {FormFieldErrors} from '@alchemy/react-form';
import CollectionTreeWidget from '../Form/CollectionTreeWidget';
import PrivacyField from '../Ui/PrivacyField';
import {Privacy} from '../../api/privacy';
import {FormGroup, InputLabel} from '@mui/material';
import TagSelect from '../Form/TagSelect';
import UploadAttributes from './UploadAttributes';
import {
    buildAttributeIndex,
    useAttributeEditor,
} from '../Media/Asset/Attribute/useAttributeEditor';
import {Collection} from '../Media/Collection/CollectionsTreeView';
import SaveAsTemplateForm from './SaveAsTemplateForm';
import {useAssetDataTemplateOptions} from '../Media/Asset/Attribute/useAssetDataTemplateOptions';
import {AssetDataTemplate, getAssetDataTemplate} from '../../api/templates';
import AssetDataTemplateSelect from '../Form/AssetDataTemplateSelect';
import {OnChangeValue} from 'react-select';
import {Asset, Attribute, Tag} from '../../types';
import {AttributeIndex} from '../Media/Asset/Attribute/AttributesEditor';
import FullPageLoader from '../Ui/FullPageLoader';
import {useOutsideRouterDirtyFormPrompt} from '@alchemy/navigation';
import {UseFormSubmitReturn} from '@alchemy/api';
import {WorkspaceContext} from "../../context/WorkspaceContext.tsx";

export type UploadData = {
    destination: Collection;
    privacy: Privacy;
    tags: Tag[];
};

export type FormUploadData = {
    tags: string[];
} & Omit<UploadData, 'tags'>;

export const UploadForm: FC<{
    workspaceId?: string | undefined;
    collectionId?: string | undefined;
    noDestination?: boolean | undefined;
    usedAttributeEditor: ReturnType<typeof useAttributeEditor>;
    usedAssetDataTemplateOptions: ReturnType<
        typeof useAssetDataTemplateOptions
    >;
    onChangeWorkspace: (wsId: string | undefined) => void;
    onChangeCollection: (colId: string | undefined) => void;
    usedFormSubmit: UseFormSubmitReturn<UploadData, Asset[], FormUploadData>;
    resetForms: () => void;
    formId: string;
}> = function ({
    formId,
    usedFormSubmit,
    workspaceId,
    collectionId,
    noDestination,
    usedAttributeEditor,
    usedAssetDataTemplateOptions,
    onChangeWorkspace,
    onChangeCollection,
    resetForms,
}) {
    const {t} = useTranslation();
    const [selectedTemplates, setSelectedTemplates] = React.useState<string[]>(
        []
    );
    const [appliedTemplates, setAppliedTemplates] = React.useState<
        AssetDataTemplate[]
    >([]);
    const [loading, setLoading] = React.useState(false);
    const [templateId, setTemplateId] = React.useState<string | undefined>();

    const {
        handleSubmit,
        control,
        setValue,
        formState: {errors, isDirty},
        forbidNavigation,
        submitting,
    } = usedFormSubmit;

    useOutsideRouterDirtyFormPrompt(t, forbidNavigation);

    const onTemplateSelect = React.useCallback(
        (values: OnChangeValue<SelectOption, true>) => {
            setSelectedTemplates(values?.map(v => v.value) ?? []);
        },
        []
    );

    const applyTemplates = React.useCallback(async () => {
        if (isSameTemplatesAndIds(selectedTemplates, appliedTemplates)) {
            return;
        }

        if (selectedTemplates.length === 0) {
            setAppliedTemplates([]);
            if (window.confirm(`Do you want to reset form?`)) {
                resetForms();
            }

            return;
        }

        if (window.confirm(`Do you want to reset before applying templates?`)) {
            resetForms();
        }

        setLoading(true);
        try {
            const templates = await Promise.all(
                selectedTemplates.map(v => getAssetDataTemplate(v))
            );

            templates.forEach(t => {
                if (t.tags && t.tags.length > 0) {
                    setValue(
                        'tags',
                        t.tags.map(t => t['@id'])
                    );
                }
                if (undefined !== t.privacy && null !== t.privacy) {
                    setValue('privacy', t.privacy);
                }

                if (t.attributes) {
                    const definitionIndex = usedAttributeEditor.definitionIndex;
                    if (definitionIndex) {
                        const attrIndex: AttributeIndex = buildAttributeIndex(
                            definitionIndex,
                            t.attributes as Attribute[]
                        );
                        const setAttr = usedAttributeEditor.onChangeHandler;

                        Object.keys(attrIndex).map(defId => {
                            Object.keys(attrIndex[defId]).map(locale => {
                                setAttr(
                                    defId,
                                    locale,
                                    attrIndex[defId][locale]
                                );
                            });
                        });
                    }
                }
            });
            setAppliedTemplates(templates);
            setLoading(false);
        } catch (e) {
            setLoading(false);
            console.error(e);
        }
    }, [isDirty, selectedTemplates, resetForms, appliedTemplates]);

    React.useEffect(() => {
        if (appliedTemplates && appliedTemplates.length === 1) {
            const template = appliedTemplates[0] as AssetDataTemplate;
            setTemplateId(template.id);
            const {setValue} = usedAssetDataTemplateOptions.usedForm;
            setValue('name', template.name);
            setValue('rememberCollection', Boolean(template.collection));
            setValue(
                'includeCollectionChildren',
                template.includeCollectionChildren
            );
            setValue(
                'rememberPrivacy',
                null !== template.privacy && undefined !== template.privacy
            );
            setValue('public', template.public);
        } else {
            setTemplateId(undefined);
        }
    }, [appliedTemplates]);

    return (
        <>
            {loading && <FullPageLoader />}
            <form id={formId} onSubmit={handleSubmit}>
                {!noDestination && (
                    <FormRow>
                        <CollectionTreeWidget
                            isSelectable={coll => coll.capabilities.canEdit}
                            control={control}
                            rules={{
                                required: true,
                            }}
                            name={'destination'}
                            onChange={(s: string | undefined, wsId) => {
                                if (
                                    typeof s === 'string' &&
                                    s.startsWith('/collections/')
                                ) {
                                    onChangeCollection(
                                        s.replace('/collections/', '')
                                    );
                                } else {
                                    onChangeCollection(undefined);
                                }
                                onChangeWorkspace(wsId);
                            }}
                            label={t(
                                'form.upload.destination.label',
                                'Destination'
                            )}
                            required={true}
                            allowNew={true}
                            disabled={submitting}
                        />
                        <FormFieldErrors
                            field={'destination'}
                            errors={errors}
                        />
                    </FormRow>
                )}
                {workspaceId && (
                    <FormRow>
                        <FormGroup>
                            <InputLabel>
                                {t(
                                    'form.asset.templates.label',
                                    'Fill with template'
                                )}
                            </InputLabel>
                            <AssetDataTemplateSelect
                                workspaceId={workspaceId}
                                collectionId={collectionId}
                                onMenuClose={applyTemplates}
                                onChange={onTemplateSelect}
                            />
                        </FormGroup>
                    </FormRow>
                )}
                {workspaceId && (
                    <FormRow>
                        <FormGroup>
                            <InputLabel>
                                {t('form.asset.tags.label', 'Tags')}
                            </InputLabel>
                            <TagSelect
                                multiple={true}
                                workspaceId={workspaceId}
                                control={control}
                                name={'tags'}
                            />
                            <FormFieldErrors<FormUploadData>
                                field={'tags'}
                                errors={errors}
                            />
                        </FormGroup>
                    </FormRow>
                )}
                <FormRow>
                    <PrivacyField control={control} name={'privacy'} />
                </FormRow>
            </form>

            {workspaceId && (
                <WorkspaceContext.Provider
                    value={{
                        workspaceId,
                    }}
                >
                    <UploadAttributes usedAttributeEditor={usedAttributeEditor} />
                </WorkspaceContext.Provider>
            )}

            <SaveAsTemplateForm
                templateId={templateId}
                usedAssetDataTemplateOptions={usedAssetDataTemplateOptions}
            />
        </>
    );
};

function isSameTemplatesAndIds(
    ids: string[],
    templates: AssetDataTemplate[]
): boolean {
    if (ids.length !== templates.length) {
        return false;
    }

    for (const k in templates) {
        if (!ids.includes(templates[k].id)) {
            return false;
        }
    }

    return true;
}

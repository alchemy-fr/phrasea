import {useForm} from 'react-hook-form';
import {FC} from 'react';
import {useTranslation} from 'react-i18next';
import FormRow from '../Form/FormRow';
import FormFieldErrors from '../Form/FormFieldErrors';
import {FormProps} from '../Form/types';
import CollectionTreeWidget from '../Form/CollectionTreeWidget';
import PrivacyField from '../Ui/PrivacyField';
import {Privacy} from '../../api/privacy';
import {FormGroup, InputLabel} from '@mui/material';
import TagSelect from '../Form/TagSelect';
import {useNavigationPrompt} from '../../hooks/useNavigationPrompt';
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
import {OnChangeValue} from 'react-select/dist/declarations/src/types';
import {SelectOption} from '../Form/RSelect';
import {Attribute, Tag} from '../../types';
import {AttributeIndex} from '../Media/Asset/Attribute/AttributesEditor';
import FullPageLoader from '../Ui/FullPageLoader';

export type UploadData = {
    destination: Collection;
    privacy: Privacy;
    tags: string[];
};

export const UploadForm: FC<
    {
        workspaceId?: string | undefined;
        collectionId?: string | undefined;
        noDestination?: boolean | undefined;
        usedAttributeEditor: ReturnType<typeof useAttributeEditor>;
        usedAssetDataTemplateOptions: ReturnType<
            typeof useAssetDataTemplateOptions
        >;
        onChangeWorkspace: (wsId: string | undefined) => void;
        onChangeCollection: (colId: string | undefined) => void;
    } & FormProps<UploadData>
> = function ({
    formId,
    onSubmit,
    submitting,
    submitted,
    workspaceId,
    collectionId,
    noDestination,
    usedAttributeEditor,
    usedAssetDataTemplateOptions,
    onChangeWorkspace,
    onChangeCollection,
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

    const defaultValues = {
        destination: '',
        privacy: Privacy.Secret,
        tags: [],
    };

    const {
        handleSubmit,
        control,
        setError,
        setValue,
        reset,
        getValues,
        formState: {errors, isDirty},
    } = useForm<UploadData>({
        defaultValues: defaultValues,
    });
    useNavigationPrompt(
        'Are you sure you want to dismiss upload?',
        !submitting && !submitted && isDirty
    );

    const resetForms = React.useCallback(() => {
        reset({
            ...defaultValues,
            destination: getValues().destination,
        });
        usedAttributeEditor.reset();
    }, [usedAttributeEditor]);

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
                        (t.tags as Tag[])!.map(t => t['@id']) as any
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
            <form id={formId} onSubmit={handleSubmit(onSubmit(setError))}>
                {!noDestination && (
                    <FormRow>
                        <CollectionTreeWidget
                            control={control}
                            rules={{
                                required: true,
                            }}
                            name={'destination'}
                            onChange={(s: string | undefined, wsId) => {
                                if (s && s.startsWith('/collections/')) {
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
                                workspaceId={workspaceId}
                                control={control}
                                name={'tags'}
                            />
                            <FormFieldErrors field={'tags'} errors={errors} />
                        </FormGroup>
                    </FormRow>
                )}
                <FormRow>
                    <PrivacyField control={control} name={'privacy'} />
                </FormRow>
            </form>

            {workspaceId && (
                <UploadAttributes usedAttributeEditor={usedAttributeEditor} />
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

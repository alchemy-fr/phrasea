import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Checkbox,
    TextField,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {FormRow} from '@alchemy/react-form';
import {SwitchWidget} from '@alchemy/react-form';
import {useAssetDataTemplateOptions} from '../Media/Asset/Attribute/useAssetDataTemplateOptions';
import {FormFieldErrors} from '@alchemy/react-form';
import React from 'react';
import {useTranslation} from 'react-i18next';

type Props = {
    usedAssetDataTemplateOptions: ReturnType<
        typeof useAssetDataTemplateOptions
    >;
    templateId?: string | undefined;
};

export default function SaveAsTemplateForm({
    usedAssetDataTemplateOptions,
    templateId,
}: Props) {
    const {t} = useTranslation();
    const {saveAsTemplate, setSaveAsTemplate, usedForm} =
        usedAssetDataTemplateOptions;

    const {
        control,
        register,
        formState: {errors},
        setValue,
    } = usedForm;

    React.useEffect(() => {
        setValue('id', templateId);
    }, [templateId]);

    return (
        <>
            <FormRow>
                <Accordion
                    expanded={saveAsTemplate}
                    onChange={(_e, expanded) => setSaveAsTemplate(expanded)}
                >
                    <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                        <Typography>
                            <Checkbox checked={saveAsTemplate} />
                            {t(
                                'save_as_template_form.save_values_as_template_for_reuse',
                                `Save values as template for reuse`
                            )}
                        </Typography>
                    </AccordionSummary>
                    <AccordionDetails>
                        <FormRow>
                            <TextField
                                error={Boolean(errors.name)}
                                label={t(
                                    'save_as_template_form.template_name',
                                    `Template name`
                                )}
                                InputLabelProps={{shrink: true}}
                                placeholder={t('save_as_template_form.my_template', `My template...`)}
                                required={true}
                                {...register('name', {
                                    required: true,
                                })}
                            />
                            <FormFieldErrors field={'name'} errors={errors} />
                        </FormRow>
                        {templateId && (
                            <FormRow>
                                <SwitchWidget
                                    control={control}
                                    name={'override'}
                                    label={`Replace applied template`}
                                />
                            </FormRow>
                        )}
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'rememberCollection'}
                                label={t('save_as_template_form.apply_to_collection', `Apply to collection`)}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'includeCollectionChildren'}
                                label={t('save_as_template_form.include_collection_children', `Include collection children`)}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'rememberAttributes'}
                                label={t('save_as_template_form.remember_attributes', `Remember Attributes`)}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'rememberPrivacy'}
                                label={t('save_as_template_form.remember_privacy', `Remember Privacy`)}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'rememberTags'}
                                label={t('save_as_template_form.remember_tags', `Remember Tags`)}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'public'}
                                label={t('save_as_template_form.public', `Public`)}
                            />
                        </FormRow>
                    </AccordionDetails>
                </Accordion>
            </FormRow>
        </>
    );
}

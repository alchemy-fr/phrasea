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
import SwitchWidget from '../Form/SwitchWidget';
import {useAssetDataTemplateOptions} from '../Media/Asset/Attribute/useAssetDataTemplateOptions';
import {FormFieldErrors} from '@alchemy/react-form';
import React from 'react';

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
                            Save values as template for reuse
                        </Typography>
                    </AccordionSummary>
                    <AccordionDetails>
                        <FormRow>
                            <TextField
                                error={Boolean(errors.name)}
                                label={'Template name'}
                                InputLabelProps={{shrink: true}}
                                placeholder={`My template...`}
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
                                label={`Apply to collection`}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'includeCollectionChildren'}
                                label={`Include collection children`}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'rememberAttributes'}
                                label={`Remember Attributes`}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'rememberPrivacy'}
                                label={`Remember Privacy`}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'rememberTags'}
                                label={`Remember Tags`}
                            />
                        </FormRow>
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'public'}
                                label={`Public`}
                            />
                        </FormRow>
                    </AccordionDetails>
                </Accordion>
            </FormRow>
        </>
    );
}

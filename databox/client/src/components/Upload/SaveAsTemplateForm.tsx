import React from 'react';
import {Accordion, AccordionDetails, AccordionSummary, Checkbox, TextField, Typography} from "@mui/material";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import FormRow from "../Form/FormRow";
import SwitchWidget from "../Form/SwitchWidget";
import {useAssetDataTemplateOptions} from "../Media/Asset/Attribute/useAssetDataTemplateOptions";


type Props = {
    usedAssetDataTemplateOptions: ReturnType<typeof useAssetDataTemplateOptions>;
    formRef: React.MutableRefObject<HTMLFormElement | null>;
};

export default function SaveAsTemplateForm({
    usedAssetDataTemplateOptions,
}: Props) {
    const {saveAsTemplate, setSaveAsTemplate, usedForm} = usedAssetDataTemplateOptions;

    const {
        control,
        register,
    } = usedForm;

    return <>
        <FormRow>
            <Accordion
                expanded={saveAsTemplate}
                onChange={(e, expanded) => setSaveAsTemplate(expanded)}
            >
                <AccordionSummary
                    expandIcon={<ExpandMoreIcon/>}
                >
                    <Typography>
                        <Checkbox
                            checked={saveAsTemplate}
                        />
                        Save values as template for reuse
                    </Typography>
                </AccordionSummary>
                <AccordionDetails>
                    <FormRow>
                        <TextField
                            label={'Template name'}
                            placeholder={`My template...`}
                            {...register('name', {
                                required: true,
                            })}
                            required={true}
                        />
                    </FormRow>
                    <FormRow>
                        <SwitchWidget
                            control={control}
                            name={'public'}
                            label={`Public`}
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
                </AccordionDetails>
            </Accordion>
        </FormRow>
    </>
}

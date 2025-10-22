import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Checkbox,
    FormGroup,
    InputLabel,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {UseFormSubmitReturn} from '@alchemy/api';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {Asset, AssetTypeFilter} from '../../types.ts';
import {FormUploadData, UploadData} from './UploadForm.tsx';
import TagSelect from '../Form/TagSelect.tsx';
import UploadAttributes from './UploadAttributes.tsx';
import {useAttributeEditor} from '../Media/Asset/Attribute/useAttributeEditor.ts';

type Props = {
    usedFormSubmit: UseFormSubmitReturn<UploadData, Asset[], FormUploadData>;
    usedStoryAttributeEditor: ReturnType<typeof useAttributeEditor>;
    workspaceId: string;
};

export default function StoryForm({
    usedFormSubmit,
    usedStoryAttributeEditor,
    workspaceId,
}: Props) {
    const {t} = useTranslation();

    const {
        control,
        formState: {errors},
        setValue,
        watch,
    } = usedFormSubmit;

    const isStory = watch('isStory');

    return (
        <>
            <FormRow>
                <Accordion
                    expanded={isStory}
                    onChange={(_e, expanded) => setValue('isStory', expanded)}
                >
                    <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                        <Typography>
                            <Checkbox checked={isStory} />
                            {t('form.upload.isStory.label', 'Story')}
                        </Typography>
                    </AccordionSummary>
                    <AccordionDetails>
                        <FormRow>
                            <FormGroup>
                                <InputLabel>
                                    {t('form.asset.tags.label', 'Tags')}
                                </InputLabel>
                                <TagSelect
                                    multiple={true}
                                    workspaceId={workspaceId}
                                    control={control}
                                    name={'story.tags'}
                                />
                                <FormFieldErrors<FormUploadData>
                                    field={'story.tags' as any}
                                    errors={errors}
                                />
                            </FormGroup>
                        </FormRow>

                        <UploadAttributes
                            usedAttributeEditor={usedStoryAttributeEditor}
                            assetTypeFilter={AssetTypeFilter.Story}
                        />
                    </AccordionDetails>
                </Accordion>
            </FormRow>
        </>
    );
}

import React from 'react';
import {LiFormSchema, UploadFormData} from '../../types.ts';
import {Box, Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {UseFormSubmitReturn} from '@alchemy/api';
import {renderField} from './Form/LiForm/renderField.tsx';
import {RemoteErrors} from '@alchemy/react-form';

type Props = {
    usedFormSubmit: UseFormSubmitReturn<UploadFormData>;
    schema: LiFormSchema;
    handleSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    error?: string;
    submitting?: boolean;
    onCancel?: () => void;
};

export default function BaseForm({
    usedFormSubmit,
    schema,
    handleSubmit,
    error,
    submitting,
    onCancel,
}: Props) {
    const disabled = Boolean(submitting || error);
    const {t} = useTranslation();

    return (
        <form onSubmit={handleSubmit}>
            {renderField({usedFormSubmit, fieldSchema: schema})}

            <RemoteErrors errors={error ? [error] : []} />

            <Box
                sx={{
                    display: 'flex',
                    gap: 2,
                }}
            >
                <div
                    style={{
                        flexGrow: 1,
                    }}
                >
                    {onCancel ? (
                        <Button
                            variant={'outlined'}
                            type="button"
                            onClick={onCancel}
                        >
                            {t('common.cancel', 'Cancel')}
                        </Button>
                    ) : null}
                </div>
                <Button
                    variant={'contained'}
                    color={'primary'}
                    type="submit"
                    disabled={disabled}
                >
                    {t('common.next', 'Next')}
                </Button>
            </Box>
        </form>
    );
}

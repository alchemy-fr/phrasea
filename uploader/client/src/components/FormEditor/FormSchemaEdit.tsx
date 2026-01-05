import {FormSchema} from '../../types.ts';
import {useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import FormSchemaForm from './FormSchemaForm.tsx';
import {Container, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {getFormSchema} from '../../api/formSchemaApi.ts';

type Props = {
    formId?: string;
};

export default function FormSchemaEdit({formId}: Props) {
    const [form, setForm] = useState<FormSchema>();
    const {t} = useTranslation();

    useEffect(() => {
        if (formId) {
            getFormSchema(formId).then(schema => {
                setForm(schema);
            });
        }
    }, [formId]);

    if (!form && formId) {
        return <FullPageLoader backdrop={false} />;
    }

    return (
        <>
            <Container maxWidth={'xl'}>
                <Typography
                    variant={'h1'}
                    sx={{
                        my: 2,
                    }}
                >
                    {form
                        ? t(
                              'form_editor.editing_form_schema',
                              'Editing Form Schema: {{name}}',
                              {name: form.target.name}
                          )
                        : t(
                              'form_editor.creating_form_schema',
                              'Creating New Form Schema'
                          )}
                </Typography>
                <FormSchemaForm
                    formSchema={
                        form || {
                            data: {
                                required: [],
                                properties: {
                                    my_field: {
                                        title: 'My Field',
                                        type: 'string',
                                    },
                                },
                            },
                            locale: '',
                        }
                    }
                />
            </Container>
        </>
    );
}

import {FormSchema} from '../../types.ts';
import {useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import FormSchemaForm from './FormSchemaForm.tsx';
import {Container, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {getFormSchema} from '../../api/formSchemaApi.ts';
import {MenuClasses} from '@alchemy/phrasea-framework';

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
            <div className={MenuClasses.PageHeader}>
                <Container maxWidth={'xl'}>
                    <Typography
                        variant={'h1'}
                        sx={{
                            my: 2,
                        }}
                    >
                        {form
                            ? t(
                                  'form_editor.edit.title',
                                  'Editing Form Schema: {{name}}',
                                  {name: form.target.name}
                              )
                            : t(
                                  'form_editor.create.title',
                                  'Creating New Form Schema'
                              )}
                    </Typography>
                </Container>
            </div>
            <Container maxWidth={'xl'}>
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

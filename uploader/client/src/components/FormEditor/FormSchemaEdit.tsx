import {FormSchema} from '../../types.ts';
import {useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import FormSchemaForm from './FormSchemaForm.tsx';
import {Container, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {getFormSchema} from '../../api/formSchemaApi.ts';

type Props = {
    formId: string;
};

export default function FormSchemaEdit({formId}: Props) {
    const [form, setForm] = useState<FormSchema>();
    const {t} = useTranslation();

    useEffect(() => {
        getFormSchema(formId).then(schema => {
            setForm(schema);
        });
    }, [formId]);

    if (!form) {
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
                    {t(
                        'form_editor.editing_form_schema',
                        'Editing Form Schema: {{name}}',
                        {name: form.target.name}
                    )}
                </Typography>
                <FormSchemaForm formSchema={form} />
            </Container>
        </>
    );
}

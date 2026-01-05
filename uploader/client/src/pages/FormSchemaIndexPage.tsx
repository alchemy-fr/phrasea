import {useEffect, useState} from 'react';
import {FormSchema} from '../types.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {
    Container,
    IconButton,
    List,
    ListItem,
    ListItemText,
    Paper,
    Typography,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import {useTranslation} from 'react-i18next';
import {getPath, Link} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import {listFormSchemas} from '../api/formSchemaApi.ts';

type Props = {};

export default function FormSchemaIndexPage({}: Props) {
    const [list, setList] = useState<FormSchema[]>();
    const {t} = useTranslation();

    useEffect(() => {
        listFormSchemas().then(res => setList(res.result));
    }, []);

    if (!list) {
        return <FullPageLoader />;
    }

    return (
        <Container maxWidth={'sm'}>
            <Typography
                variant={'h1'}
                sx={{
                    my: 2,
                }}
            >
                {t('form_editor.title', 'Form Editor')}
            </Typography>
            <Paper
                sx={{
                    margin: '0 auto',
                }}
            >
                <List>
                    {list.map(form => (
                        <ListItem
                            key={form.id}
                            secondaryAction={
                                <>
                                    <IconButton
                                        component={Link}
                                        to={getPath(
                                            routes.admin.routes.formSchema
                                                .routes.edit,
                                            {
                                                id: form.id,
                                            }
                                        )}
                                        title={t('form_editor.edit', 'Edit')}
                                    >
                                        <EditIcon />
                                    </IconButton>
                                    <IconButton
                                        title={t(
                                            'form_editor.delete',
                                            'Delete'
                                        )}
                                    >
                                        <DeleteIcon />
                                    </IconButton>
                                </>
                            }
                        >
                            <ListItemText
                                primary={form.target.name}
                                secondary={form.locale || 'All locales'}
                            />
                        </ListItem>
                    ))}
                </List>
            </Paper>
        </Container>
    );
}

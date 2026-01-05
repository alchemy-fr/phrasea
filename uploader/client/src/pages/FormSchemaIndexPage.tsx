import {useEffect, useState} from 'react';
import {FormSchema} from '../types.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {
    Box,
    Button,
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
import {getPath, Link, useModals} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import {deleteFormSchema, listFormSchemas} from '../api/formSchemaApi.ts';
import {ConfirmDialog} from '@alchemy/phrasea-framework';

type Props = {};

export default function FormSchemaIndexPage({}: Props) {
    const [list, setList] = useState<FormSchema[]>();
    const {t} = useTranslation();
    const {openModal} = useModals();

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
                {t('form_schema.list.title', 'Form Schemas')}
            </Typography>
            <Paper
                sx={{
                    margin: '0 auto',
                }}
            >
                {list.length === 0 ? (
                    <Typography
                        sx={{
                            p: 2,
                            textAlign: 'center',
                        }}
                    >
                        {t(
                            'form_schema.list.no_form_schemas',
                            'No form schemas found. Click the button below to create a new one.'
                        )}
                    </Typography>
                ) : (
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
                                            title={t(
                                                'form_schema.list.edit',
                                                'Edit'
                                            )}
                                        >
                                            <EditIcon />
                                        </IconButton>
                                        <IconButton
                                            onClick={() => {
                                                openModal(ConfirmDialog, {
                                                    title: t(
                                                        'form_schema.list.delete_confirm_title',
                                                        'Delete Form Schema'
                                                    ),
                                                    children: t(
                                                        'form_schema.list.delete_confirm_message',
                                                        'Are you sure you want to delete the form schema for {{name}}?',
                                                        {
                                                            name: form.target
                                                                .name,
                                                        }
                                                    ),
                                                    onConfirm: async () => {
                                                        await deleteFormSchema(
                                                            form.id
                                                        );
                                                        setList(prevList =>
                                                            prevList?.filter(
                                                                f =>
                                                                    f.id !==
                                                                    form.id
                                                            )
                                                        );
                                                    },
                                                });
                                            }}
                                            title={t(
                                                'form_schema.list.delete',
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
                )}

                <Box p={2}>
                    <Button
                        component={Link}
                        to={getPath(
                            routes.admin.routes.formSchema.routes.create
                        )}
                        fullWidth
                        variant={'contained'}
                    >
                        {t(
                            'form_schema.list.create_button',
                            'Create New Form Schema'
                        )}
                    </Button>
                </Box>
            </Paper>
        </Container>
    );
}

import {useEffect, useState} from 'react';
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
import {deleteTargetParam} from '../api/targetParamApi.ts';
import {ConfirmDialog} from '@alchemy/phrasea-framework';
import {TargetParam} from '../types.ts';
import {listTargetParams} from '../api/targetParamApi.ts';

type Props = {};

export default function TargetParamIndexPage({}: Props) {
    const [list, setList] = useState<TargetParam[]>();
    const {t} = useTranslation();
    const {openModal} = useModals();

    useEffect(() => {
        listTargetParams().then(res => setList(res.result));
    }, []);

    if (!list) {
        return <FullPageLoader backdrop={false} />;
    }

    return (
        <Container maxWidth={'sm'}>
            <Typography
                variant={'h1'}
                sx={{
                    my: 2,
                }}
            >
                {t('target_param.list.title', 'Target Params')}
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
                        {t('target_param.list.empty', 'None')}
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
                                                routes.admin.routes.targetParam
                                                    .routes.edit,
                                                {
                                                    id: form.id,
                                                }
                                            )}
                                            title={t(
                                                'target_param.list.edit',
                                                'Edit'
                                            )}
                                        >
                                            <EditIcon />
                                        </IconButton>
                                        <IconButton
                                            onClick={() => {
                                                openModal(ConfirmDialog, {
                                                    title: t(
                                                        'target_param.list.delete_confirm_title',
                                                        'Delete Form Schema'
                                                    ),
                                                    children: t(
                                                        'target_param.list.delete_confirm_message',
                                                        'Are you sure you want to delete the params of target {{name}}?',
                                                        {
                                                            name: form.target
                                                                .name,
                                                        }
                                                    ),
                                                    onConfirm: async () => {
                                                        await deleteTargetParam(
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
                                                'target_param.list.delete',
                                                'Delete'
                                            )}
                                        >
                                            <DeleteIcon />
                                        </IconButton>
                                    </>
                                }
                            >
                                <ListItemText primary={form.target.name} />
                            </ListItem>
                        ))}
                    </List>
                )}

                <Box p={2}>
                    <Button
                        component={Link}
                        to={getPath(
                            routes.admin.routes.targetParam.routes.create
                        )}
                        fullWidth
                        variant={'contained'}
                    >
                        {t(
                            'target_param.list.create_button',
                            'Create New Target Params'
                        )}
                    </Button>
                </Box>
            </Paper>
        </Container>
    );
}

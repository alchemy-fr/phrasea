import {getPath, Link, useModals} from '@alchemy/navigation';
import {Page} from '../types.ts';
import React, {useCallback, useEffect, useState} from 'react';
import {deletePage, getPages} from '../api/page.ts';
import {FlexRow, FullPageLoader} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {
    Box,
    Button,
    Container,
    IconButton,
    Paper,
    Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import {NormalizedCollectionResponse} from '@alchemy/api';
import {ConfirmDialog, LoadMoreButton} from '@alchemy/phrasea-framework';
import {routes} from '../routes.ts';
import AppBar from '../components/Layout/AppBar.tsx';
import PageCreateDialog from '../components/Landing/Editor/form/PageCreateDialog.tsx';

type Props = {};

export default function PageIndexPage({}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [loading, setLoading] = React.useState(false);
    const [data, setData] = useState<NormalizedCollectionResponse<Page>>();

    const onCreate = useCallback(() => {
        openModal(PageCreateDialog);
    }, [openModal]);

    const loadPages = useCallback(
        async (nextUrl?: string) => {
            setLoading(true);
            try {
                const res = await getPages(nextUrl);

                setData(p =>
                    p && nextUrl
                        ? {
                              ...res,
                              result: p.result.concat(res.result),
                          }
                        : res
                );
            } finally {
                setLoading(false);
            }
        },
        [setData]
    );

    useEffect(() => {
        loadPages();
    }, [loadPages]);

    const onDeletePage = React.useCallback(
        async (page: Page) => {
            openModal(ConfirmDialog, {
                textToType: page.title,
                title: t('page.delete_title', {
                    defaultValue: 'Delete Page "{{title}}"',
                    title: page.title,
                }),
                onConfirm: async () => {
                    await deletePage(page.id);
                    loadPages();
                },
                confirmLabel: t('page.delete_confirm', 'Delete Page'),
            });
        },
        [loadPages, openModal]
    );

    return (
        <Container>
            <AppBar />
            {data ? (
                <div>
                    <FlexRow
                        sx={{
                            my: 2,
                        }}
                    >
                        <Typography
                            variant={'h1'}
                            sx={{
                                mb: 2,
                                flexGrow: 1,
                            }}
                        >
                            {t('page.list.title', 'Pages')}
                        </Typography>
                        <Button
                            startIcon={<AddIcon />}
                            variant={'contained'}
                            onClick={onCreate}
                        >
                            {t('page.list.create_button', 'Create Page')}
                        </Button>
                    </FlexRow>
                    <Box gap={1} display={'flex'} flexDirection={'column'}>
                        {data.result.map(page => (
                            <Paper
                                key={page.id}
                                sx={{
                                    p: 2,
                                    display: 'flex',
                                }}
                            >
                                <div
                                    style={{
                                        flexGrow: 1,
                                    }}
                                >
                                    <Typography variant={'h5'}>
                                        {page.title}
                                    </Typography>
                                </div>
                                <div>
                                    <IconButton
                                        component={Link}
                                        to={getPath(
                                            routes.pageAdmin.routes.edit,
                                            {
                                                id: page.id,
                                            }
                                        )}
                                    >
                                        <EditIcon />
                                    </IconButton>
                                    <IconButton
                                        color={'error'}
                                        onClick={() => onDeletePage(page)}
                                    >
                                        <DeleteIcon />
                                    </IconButton>
                                </div>
                            </Paper>
                        ))}
                    </Box>
                    <LoadMoreButton
                        loading={loading}
                        data={data}
                        load={loadPages}
                    />
                </div>
            ) : (
                <FullPageLoader backdrop={false} />
            )}

            {data && data.result.length === 0 && !loading && (
                <Typography variant={'h5'} align={'center'}>
                    {t('page.list.no_result', 'No page')}
                </Typography>
            )}
        </Container>
    );
}

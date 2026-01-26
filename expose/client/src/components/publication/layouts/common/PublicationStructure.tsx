import Description from './Description.tsx';
import moment from 'moment';
import {Publication} from '../../../../types.ts';
import {
    appLocales,
    getTranslatedDescription,
    getTranslatedTitle,
} from '../../../../i18n.ts';
import {
    Alert,
    Box,
    Breadcrumbs,
    Button,
    Container,
    Typography,
} from '@mui/material';
import {config, keycloakClient} from '../../../../init.ts';
import {getPath, Link, useNavigate} from '@alchemy/navigation';
import {routes} from '../../../../routes.ts';
import {useTranslation} from 'react-i18next';
import HomeIcon from '@mui/icons-material/Home';
import DownloadArchiveButton from './DownloadArchiveButton.tsx';
import {
    MenuClasses,
    MenuOrientation,
    VerticalMenuLayout,
} from '@alchemy/phrasea-framework';
import React, {PropsWithChildren} from 'react';
import PublicationsTree from './PublicationsTree.tsx';
import {getPublicationPath} from '../../../../hooks/useNavigateToPublication.ts';
import EditIcon from '@mui/icons-material/Edit';
import AppNav from '../../../AppNav.tsx';

type Props = PropsWithChildren<{
    publication: Publication;
}>;

export default function PublicationStructure({publication, children}: Props) {
    const {assets, description, layoutOptions, date, enabled} = publication;
    const {t} = useTranslation();
    const navigate = useNavigate();

    const downloadArchiveEnabled =
        publication.downloadEnabled && assets!.length > 0;

    const parents: Publication[] = [];
    let currentParent = publication.parent;
    let lastKnownParentId = publication.parentId;
    const rootPublication = publication.rootPublication;
    while (currentParent && currentParent.id !== rootPublication!.id) {
        lastKnownParentId = currentParent.parentId;
        parents.unshift(currentParent);
        currentParent = currentParent.parent;
    }

    return (
        <VerticalMenuLayout
            config={config}
            defaultOpen={false}
            childrenSx={{
                display: 'flex',
            }}
            menuChildren={
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        flexGrow: 1,
                    }}
                >
                    <div
                        style={{
                            flexGrow: 1,
                        }}
                    >
                        <PublicationsTree publication={publication} />
                    </div>
                    <div>
                        <AppNav orientation={MenuOrientation.Vertical} />
                    </div>
                </div>
            }
            logoProps={{
                appTitle: t('common.expose', 'Expose'),
                onLogoClick: !config.disableIndexPage
                    ? () => {
                          navigate(getPath(routes.index));
                      }
                    : undefined,
            }}
            commonMenuProps={{
                keycloakClient,
                appLocales,
            }}
        >
            <div className={MenuClasses.PageHeader}>
                <Container
                    sx={{
                        py: 2,
                    }}
                >
                    <Breadcrumbs aria-label="breadcrumb">
                        {!config.disableIndexPage && (
                            <Link
                                style={{
                                    display: 'flex',
                                }}
                                to={getPath(routes.index)}
                                title={t('publicationHeader.homeLink', 'Home')}
                            >
                                <HomeIcon fontSize="small" color={'primary'} />
                            </Link>
                        )}

                        {rootPublication &&
                            rootPublication.id !== publication.id && (
                                <Link to={getPublicationPath(rootPublication)}>
                                    {getTranslatedTitle(rootPublication)}
                                </Link>
                            )}

                        {lastKnownParentId &&
                            lastKnownParentId != rootPublication!.id && (
                                <div>
                                    <>...</>
                                </div>
                            )}

                        {parents.map(p => (
                            <Link key={p.id} to={getPublicationPath(p)}>
                                {getTranslatedTitle(p)}
                            </Link>
                        ))}

                        <div>
                            {layoutOptions.logoUrl && (
                                <div className={'logo'}>
                                    <img src={layoutOptions.logoUrl} alt={''} />
                                </div>
                            )}
                            <Typography variant={'h1'}>
                                {getTranslatedTitle(publication)}

                                {publication.capabilities.edit && (
                                    <Button
                                        sx={{ml: 2}}
                                        variant={'outlined'}
                                        component={Link}
                                        to={getPath(
                                            routes.publication.routes.edit,
                                            {
                                                id: publication.id,
                                            }
                                        )}
                                        startIcon={<EditIcon />}
                                    >
                                        {t(
                                            'publicationHeader.editButton',
                                            'Edit'
                                        )}
                                    </Button>
                                )}
                            </Typography>
                        </div>
                    </Breadcrumbs>
                    <Typography variant={'caption'}>
                        {moment(date).format('LLLL')}
                    </Typography>
                </Container>
            </div>
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 2,
                    mt: 2,
                    mb: 4,
                }}
            >
                {!enabled && (
                    <Alert severity={'warning'}>
                        {t(
                            'publication.disabled',
                            'This publication is currently disabled. Only administrators can see it.'
                        )}
                    </Alert>
                )}
                <Container>
                    {(description || downloadArchiveEnabled) && (
                        <Box
                            sx={{
                                display: 'flex',
                                flexDirection: {
                                    xs: 'column',
                                    sm: 'row',
                                },
                                gap: 2,
                            }}
                        >
                            <div
                                style={{
                                    flexGrow: 1,
                                }}
                            >
                                {Boolean(description) && (
                                    <Description
                                        descriptionHtml={getTranslatedDescription(
                                            publication
                                        )}
                                    />
                                )}
                            </div>

                            {assets.length > 1 &&
                                downloadArchiveEnabled &&
                                publication.archiveDownloadUrl && (
                                    <div>
                                        <DownloadArchiveButton
                                            publication={publication}
                                        />
                                    </div>
                                )}
                        </Box>
                    )}
                </Container>
            </Box>
            {children}
        </VerticalMenuLayout>
    );
}

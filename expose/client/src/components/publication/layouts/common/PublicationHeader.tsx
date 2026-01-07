import Description from './Description.tsx';
import moment from 'moment';
import {Publication} from '../../../../types.ts';
import {
    appLocales,
    getTranslatedDescription,
    getTranslatedTitle,
} from '../../../../i18n.ts';
import {Alert, Box, Breadcrumbs, Container, Typography} from '@mui/material';
import {config, keycloakClient} from '../../../../init.ts';
import {getPath, Link, useNavigate} from '@alchemy/navigation';
import {routes} from '../../../../routes.ts';
import {useTranslation} from 'react-i18next';
import HomeIcon from '@mui/icons-material/Home';
import DownloadArchiveButton from './DownloadArchiveButton.tsx';
import {VerticalMenuLayout} from '@alchemy/phrasea-framework';
import React, {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{
    publication: Publication;
}>;

export default function PublicationHeader({publication, children}: Props) {
    const {assets, description, layoutOptions, date, enabled} = publication;
    const {t} = useTranslation();
    const navigate = useNavigate();

    const downloadArchiveEnabled =
        publication.downloadEnabled && assets.length > 0;

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
            header={
                <>
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
                                    title={t(
                                        'publicationHeader.homeLink',
                                        'Home'
                                    )}
                                >
                                    <HomeIcon
                                        fontSize="small"
                                        color={'primary'}
                                    />
                                </Link>
                            )}

                            {rootPublication &&
                                rootPublication.id !== publication.id && (
                                    <Link
                                        to={getPath(routes.publication, {
                                            id: rootPublication.id,
                                        })}
                                    >
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
                                <Link
                                    key={p.id}
                                    to={getPath(routes.publication, {id: p.id})}
                                >
                                    {getTranslatedTitle(p)}
                                </Link>
                            ))}

                            <div>
                                {layoutOptions.logoUrl && (
                                    <div className={'logo'}>
                                        <img
                                            src={layoutOptions.logoUrl}
                                            alt={''}
                                        />
                                    </div>
                                )}
                                <Typography variant={'h1'}>
                                    {getTranslatedTitle(publication)}
                                </Typography>
                            </div>
                        </Breadcrumbs>
                        <Typography variant={'caption'}>
                            {moment(date).format('LLLL')}
                        </Typography>
                    </Container>
                </>
            }
        >
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
                                flexDirection: 'row',
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

                            {downloadArchiveEnabled && (
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

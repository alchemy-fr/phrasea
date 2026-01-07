import Description from '../../Publication/Description.tsx';
import moment from 'moment';
import {Publication} from '../../../types.ts';
import {
    appLocales,
    getTranslatedDescription,
    getTranslatedTitle,
} from '../../../i18n.ts';
import {Box, Breadcrumbs, Container, Typography} from '@mui/material';
import {config, keycloakClient} from '../../../init.ts';
import {getPath, Link, useNavigate} from '@alchemy/navigation';
import {routes} from '../../../routes.ts';
import {useTranslation} from 'react-i18next';
import HomeIcon from '@mui/icons-material/Home';
import DownloadArchiveButton from './DownloadArchiveButton.tsx';
import {VerticalMenuLayout} from '@alchemy/phrasea-framework';

type Props = {
    publication: Publication;
};

export default function PublicationHeader({publication}: Props) {
    const {assets, description, layoutOptions, date} = publication;
    const {t} = useTranslation();
    const navigate = useNavigate();

    const downloadArchiveEnabled =
        publication.downloadEnabled && assets.length > 0;

    const parents: Publication[] = [];
    let currentParent = publication.parent;
    let hasParent = publication.hasParent;
    while (currentParent) {
        hasParent = currentParent.hasParent;
        parents.unshift(currentParent);
        currentParent = currentParent.parent;
    }

    return (
        <VerticalMenuLayout
            config={config}
            logoProps={{
                appTitle: t('common.expose', 'Expose'),
                onLogoClick: () => {
                    navigate(getPath(routes.index));
                },
            }}
            commonMenuProps={{
                keycloakClient,
                appLocales,
            }}
            header={
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

                        {hasParent && (
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
                                    <img src={layoutOptions.logoUrl} alt={''} />
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
            }
        >
            <Container
                sx={{
                    mt: 1,
                    mb: 4,
                }}
            >
                {(description || downloadArchiveEnabled) && (
                    <Box
                        sx={{
                            display: 'flex',
                            flexDirection: 'row',
                            gap: 2,
                            mt: 2,
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
        </VerticalMenuLayout>
    );
}

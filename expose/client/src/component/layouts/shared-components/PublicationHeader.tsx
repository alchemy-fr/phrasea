import Description from '../../Publication/Description.tsx';
import moment from 'moment';
import {Publication} from '../../../types.ts';
import {getTranslatedDescription, getTranslatedTitle} from '../../../i18n.ts';
import {Box, Breadcrumbs, Container, Typography} from '@mui/material';
import {config} from '../../../init.ts';
import AppBar from '../../UI/AppBar.tsx';
import {getPath, Link} from '@alchemy/navigation';
import {routes} from '../../../routes.ts';
import {useTranslation} from 'react-i18next';
import HomeIcon from '@mui/icons-material/Home';
import DownloadArchiveButton from './DownloadArchiveButton.tsx';

type Props = {
    publication: Publication;
};

export default function PublicationHeader({publication}: Props) {
    const {assets, description, layoutOptions, date} = publication;
    const {t} = useTranslation();

    return (
        <Container
            sx={{
                mt: 1,
                mb: 4,
            }}
        >
            <AppBar>
                <div
                    style={{
                        position: 'relative',
                        flexGrow: 1,
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
                </div>

                {publication.downloadEnabled &&
                    config.zippyEnabled &&
                    assets.length > 0 && (
                        <div>
                            <DownloadArchiveButton publication={publication} />
                        </div>
                    )}
            </AppBar>

            {description && (
                <Box
                    sx={{
                        mt: 2,
                    }}
                >
                    <Description
                        descriptionHtml={getTranslatedDescription(publication)}
                    />
                </Box>
            )}
        </Container>
    );
}

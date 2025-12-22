import Description from './Description';
import ZippyDownloadButton from './ZippyDownloadButton';
import moment from 'moment';
import {Publication} from '../../../types.ts';
import {getTranslatedDescription, getTranslatedTitle} from '../../../i18n.ts';
import {Container, Typography} from '@mui/material';
import {config} from '../../../init.ts';
import AppBar from '../../UI/AppBar.tsx';

type Props = {
    publication: Publication;
};

export default function PublicationHeader({publication}: Props) {
    const {assets, description, layoutOptions, date} = publication;

    return (
        <Container
            sx={{
                pt: 1,
                pb: 6,
            }}
        >
            <AppBar>
                <div
                    style={{
                        position: 'relative',
                        flexGrow: 1,
                    }}
                >
                    {layoutOptions.logoUrl && (
                        <div className={'logo'}>
                            <img src={layoutOptions.logoUrl} alt={''} />
                        </div>
                    )}
                    <Typography variant={'h1'}>
                        {getTranslatedTitle(publication)}
                    </Typography>
                    <Typography variant={'caption'}>
                        {moment(date).format('LLLL')}
                    </Typography>
                </div>

                {description && (
                    <Description
                        descriptionHtml={getTranslatedDescription(publication)}
                    />
                )}
                {publication.downloadEnabled &&
                    config.zippyEnabled &&
                    assets.length > 0 && (
                        <div className={'download-archive'}>
                            <ZippyDownloadButton
                                id={publication.id}
                                data={publication}
                            />
                        </div>
                    )}
            </AppBar>
        </Container>
    );
}

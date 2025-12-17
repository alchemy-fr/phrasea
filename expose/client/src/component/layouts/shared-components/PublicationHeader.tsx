import Description from './Description';
import ZippyDownloadButton from './ZippyDownloadButton';
import moment from 'moment';
import {Publication} from '../../../types.ts';
import {getTranslatedDescription, getTranslatedTitle} from '../../../i18n.ts';
import {Typography} from '@mui/material';
import {config} from '../../../init.ts';

type Props = {
    data: Publication;
};

export default function PublicationHeader({data}: Props) {
    const {assets, description, layoutOptions, date} = data;

    return (
        <div className={'pub-header'}>
            <div
                style={{
                    position: 'relative',
                }}
            >
                {layoutOptions.logoUrl && (
                    <div className={'logo'}>
                        <img src={layoutOptions.logoUrl} alt={''} />
                    </div>
                )}
                <Typography variant={'h1'}>
                    {getTranslatedTitle(data)}
                </Typography>
                <Typography variant={'caption'}>
                    {moment(date).format('LLLL')}
                </Typography>
            </div>

            {description && (
                <Description descriptionHtml={getTranslatedDescription(data)} />
            )}
            {data.downloadEnabled &&
                config.zippyEnabled &&
                assets.length > 0 && (
                    <div className={'download-archive'}>
                        <ZippyDownloadButton id={data.id} data={data} />
                    </div>
                )}
        </div>
    );
}

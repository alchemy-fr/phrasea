import config from '../../../config';
import Description from './Description';
import ZippyDownloadButton from './ZippyDownloadButton';
import moment from 'moment';
import {Publication} from '../../../types.ts';
import {getTranslatedDescription, getTranslatedTitle} from '../../../i18n.ts';

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
                <h1>{getTranslatedTitle(data)}</h1>
                {date ? <time>{moment(date).format('LLLL')}</time> : ''}
                {assets.length > 0 && config.zippyEnabled && (
                    <div
                        style={{
                            position: 'absolute',
                            top: 0,
                            right: 0,
                        }}
                    ></div>
                )}
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

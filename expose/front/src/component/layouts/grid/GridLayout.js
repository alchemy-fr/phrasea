import React from 'react';
import {PropTypes} from 'prop-types';
import Description from "../shared-components/Description";
import {dataShape} from "../../props/dataShape";
import Gallery from 'react-grid-gallery';
import {FullPageLoader} from '@alchemy-fr/phraseanet-react-components';
import Carousel, {Modal, ModalGateway} from "react-images";
import moment from "moment";
import {
    Magnifier,
    MOUSE_ACTIVATION,
    TOUCH_ACTIVATION
} from "react-image-magnifiers";
import squareImg from '../../../images/square.svg';
import VideoPlayer from "../shared-components/VideoPlayer";

const CustomView = (props) => {
    return <div className={'lb-asset-wrapper'}>
        <div className="asset">
            {0 === props.data.mimeType.indexOf('video/') ? <VideoPlayer
                url={props.data.url}
                thumbUrl={props.data.thumbUrl}
                title={props.data.title}
            /> : <div className="flex-magnifier">
                <Magnifier
                    imageSrc={props.data.url}
                    imageAlt={props.data.title}
                    mouseActivation={MOUSE_ACTIVATION.CLICK} // Optional
                    touchActivation={TOUCH_ACTIVATION.DOUBLE_TAP} // Optional
                />
            </div>}
        </div>
        <div className="desc">
            <Description
                descriptionHtml={props.data.description}
            />
        </div>
    </div>
};

class GridLayout extends React.Component {
    static propTypes = {
        data: dataShape,
        assetId: PropTypes.string,
        options: PropTypes.object,
        mapOptions: PropTypes.object,
    };

    state = {
        thumbsLoaded: false,
        currentAsset: null,
        showVideo: {},
    };

    componentDidMount() {
        this.loadThumbs();
    }

    openAsset = (offset) => {
        this.setState({currentAsset: offset});
    }

    closeModal = () => {
        this.setState({currentAsset: null});
    }

    render() {
        const {data} = this.props;
        const {
            title,
            assets,
            date,
            layoutOptions,
        } = data;

        return <div className={`layout-grid`}>
            <header>
                {date ? <time>{moment(date).format('LLLL')}</time> : ''}
                {layoutOptions.logoUrl ? <div>
                    <img src={layoutOptions.logoUrl} alt={''}/>
                </div> : ''}
            </header>
            <h1>{title}</h1>
            <Description
                descriptionHtml={data.description}
            />
            {assets.length > 0 ? this.renderGallery() : 'Gallery is empty'}
        </div>
    }

    renderGallery() {
        if (!this.state.thumbsLoaded) {
            return <FullPageLoader/>
        }

        const {currentAsset} = this.state;

        const images = this.props.data.assets.map(a => a.asset);

        return <>
            <Gallery
                enableLightbox={false}
                enableImageSelection={false}
                onClickThumbnail={this.openAsset}
                images={this.props.data.assets.map(a => {
                    const {asset} = a;

                    return {
                        src: asset.url,
                        thumbnail: asset.thumbUrl,
                        thumbnailWidth: asset.thumbWidth,
                        thumbnailHeight: asset.thumbHeight,
                        caption: asset.title,
                    };
                })}/>
            <ModalGateway>
                {null !== currentAsset ? (
                    <Modal
                        allowFullscreen={false}
                        closeOnBackdropClick={false}
                        onClose={this.closeModal}
                    >
                        <Carousel
                            currentIndex={currentAsset}
                            components={{
                                View: CustomView,
                            }}
                            views={images}
                            styles={{
                                container: base => ({
                                    ...base,
                                    height: '100vh',
                                }),
                                view: base => ({
                                    ...base,
                                    alignItems: 'center',
                                    display: 'flex ',
                                    height: 'calc(100vh - 54px)',
                                    justifyContent: 'center',

                                    '& > img': {
                                        maxHeight: 'calc(100vh - 94px)',
                                    },
                                }),
                            }}
                        />
                    </Modal>
                ) : null}
            </ModalGateway>
        </>
    }

    async loadThumbs() {
        await Promise.all(this.props.data.assets.map(a => {
            const {asset} = a;

            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    asset.thumbWidth = img.width;
                    asset.thumbHeight = img.height;
                    resolve();
                };
                img.onerror = e => {
                    console.error(e);
                    asset.thumbUrl = squareImg;
                    asset.thumbWidth = 100;
                    asset.thumbHeight = 100;
                    resolve();
                };
                img.src = asset.thumbUrl;
            });
        }));

        this.setState({thumbsLoaded: true});
    }
}

export default GridLayout;

import React from 'react';
// import { PropTypes } from 'prop-types'
import Description from '../shared-components/Description';
import Gallery from 'react-grid-gallery';
import Carousel, {Modal, ModalGateway} from 'react-images';
import squareImg from '../../../images/square.svg';
import DownloadButton from '../shared-components/DownloadButton';
import {
    onDownload,
    renderDownloadTermsModal,
    renderDownloadViaEmail,
} from '../shared-components/DownloadViaEmailProxy';
import AssetProxy from '../shared-components/AssetProxy';
import PublicationHeader from '../shared-components/PublicationHeader';
import {Trans} from 'react-i18next';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {logAssetView} from '../../../lib/log';
import {getThumbPlaceholder} from '../shared-components/placeholders';

const CustomView = ({data, carouselProps, currentView}) => {
    const isCurrent = currentView === data;

    React.useEffect(() => {
        if (isCurrent) {
            logAssetView(data.id);
        }
    }, [isCurrent]);

    return (
        <div className={'lb-asset-wrapper'}>
            <div className="asset">
                <AssetProxy
                    isCurrent={isCurrent}
                    magnifier={true}
                    asset={data}
                />
            </div>
            <div className="desc">
                <Description descriptionHtml={data.description} />
                {data.downloadEnabled && data.downloadUrl ? (
                    <div className="download-btn">
                        <DownloadButton
                            downloadUrl={data.downloadUrl}
                            onDownload={carouselProps.onDownload}
                        />
                    </div>
                ) : (
                    ''
                )}
            </div>
        </div>
    );
};

class GridLayout extends React.Component {
    // static propTypes = {
    //     data: dataShape,
    //     assetId: PropTypes.string,
    //     options: PropTypes.object,
    //     mapOptions: PropTypes.object,
    // }

    state = {
        thumbsLoaded: false,
        currentAsset: null,
        showVideo: {},
    };

    static getDerivedStateFromProps(props, state = {}) {
        if (props.assetId && !state.currentAssetAutoSet) {
            return {
                ...state,
                currentAsset: props.data.assets.findIndex(
                    a => a.id === props.assetId
                ),
                currentAssetAutoSet: true,
            };
        }

        return state;
    }

    componentDidMount() {
        this.loadThumbs();
    }

    openAsset = offset => {
        this.setState({currentAsset: offset});
    };

    closeModal = () => {
        this.setState({currentAsset: null});
    };

    render() {
        const {data} = this.props;
        const {assets, downloadEnabled} = data;

        return (
            <div className={`layout-grid`}>
                {downloadEnabled && renderDownloadTermsModal.call(this)}
                {downloadEnabled && renderDownloadViaEmail.call(this)}
                <PublicationHeader data={data} />
                {assets.length > 0 ? (
                    this.renderGallery()
                ) : (
                    <Trans i18nKey={'gallery.empty'}>Gallery is empty</Trans>
                )}
            </div>
        );
    }

    onDownload = (url, e) => {
        onDownload.call(this, url, e);
        this.closeModal();
    };

    renderGallery() {
        if (!this.state.thumbsLoaded) {
            return <FullPageLoader
                backdrop={false}
            />;
        }

        const {downloadEnabled} = this.props.data;
        const {currentAsset} = this.state;

        const images = this.props.data.assets.map(a => ({
            ...a,
            downloadEnabled,
        }));

        return (
            <>
                <Gallery
                    enableLightbox={false}
                    enableImageSelection={false}
                    onClickThumbnail={this.openAsset}
                    images={this.props.data.assets.map(a => ({
                        src: a.previewUrl,
                        thumbnail:
                            a.thumbUrl || getThumbPlaceholder(a.mimeType),
                        thumbnailWidth: a.thumbWidth,
                        thumbnailHeight: a.thumbHeight,
                        caption: a.title,
                    }))}
                />
                <ModalGateway>
                    {null !== currentAsset ? (
                        <Modal
                            allowFullscreen={false}
                            closeOnBackdropClick={false}
                            onClose={this.closeModal}
                        >
                            <Carousel
                                onDownload={
                                    downloadEnabled
                                        ? this.onDownload
                                        : undefined
                                }
                                currentIndex={currentAsset}
                                components={{
                                    View: CustomView,
                                }}
                                views={images}
                                styles={{
                                    container: base => ({
                                        ...base,
                                        height: '100vh',
                                        width: '100vw',
                                        position: 'relative',
                                    }),
                                    view: base => ({
                                        ...base,
                                        'alignItems': 'center',
                                        'display': 'flex',
                                        'height': 'calc(100vh - 54px)',
                                        'justifyContent': 'center',
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
        );
    }

    async loadThumbs() {
        await Promise.all(
            this.props.data.assets.map(a => {
                return new Promise((resolve, reject) => {
                    const img = new Image();
                    img.onload = () => {
                        a.thumbWidth = img.width;
                        a.thumbHeight = img.height;
                        resolve();
                    };
                    img.onerror = e => {
                        console.error(e);
                        a.thumbUrl = squareImg;
                        a.thumbWidth = 100;
                        a.thumbHeight = 100;
                        resolve();
                    };
                    img.src = a.thumbUrl || getThumbPlaceholder(a.mimeType);
                });
            })
        );

        this.setState({thumbsLoaded: true});
    }
}

export default GridLayout;

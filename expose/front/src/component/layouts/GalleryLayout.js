import React from 'react';
import ImageGallery from 'react-image-gallery';
import {dataShape} from "../props/dataShape";
import {PropTypes} from 'prop-types';
import Description from "./shared-components/Description";

class GalleryLayout extends React.Component {
    static propTypes = {
        data: dataShape,
        assetId: PropTypes.string,
    };

    state = {
        showFullscreenButton: true,
        showPlayButton: true,
        showVideo: {},
    };

    onSlide = () => {
        this.resetVideo();
    };

    resetVideo() {
        this.setState({
            showVideo: {},
            showFullscreenButton: true,
            showPlayButton: true,
        });
    }

    toggleShowVideo(url) {
        this.setState(prevState => {
            const showVideo = {...prevState.showVideo};
            const wasShown = !!showVideo[url];
            showVideo[url] = !wasShown;

            return {
                showVideo,
                showPlayButton: wasShown,
                showFullscreenButton: wasShown,
            }
        });
    }

    renderVideo = (item) => {
        const {showVideo} = this.state;

        return <div className='image-gallery-image'>
            {
                showVideo[item.url] ?
                    <div className='video-wrapper'>
                        <video controls autoPlay={true}>
                            <source src={item.url} type={'video/mp4'}/>
                            Sorry, your browser doesn't support embedded videos.
                        </video>
                    </div>
                    : <div onClick={this.toggleShowVideo.bind(this, item.url)}>
                        <div className='play-button'/>
                        <img src={item.thumbUrl} alt={item.title}/>
                        {
                            item.description &&
                            <span
                                className='image-gallery-description'
                                style={{right: '0', left: 'initial'}}
                            >
                            {item.description}
                          </span>
                        }
                    </div>
            }
        </div>;
    };

    render() {
        const {assetId, data} = this.props;
        const {
            title,
            assets,
        } = data;

        const {
            showFullscreenButton,
            showPlayButton,
        } = this.state;

        let startIndex = 0;
        if (assetId) {
            startIndex = assets.findIndex(a => a.id === assetId);
            if (startIndex < 0) {
                startIndex = assets.findIndex(a => a.slug === assetId);
                if (startIndex < 0) {
                    startIndex = 0;
                }
            }
        }

        return <div className={`layout-gallery`}>
            <div className="container">
                <h1>{title}</h1>
                <Description
                    descriptionHtml={data.description}
                />
                {assets.length > 0 ?
                    <ImageGallery
                        startIndex={startIndex}
                        onSlide={this.onSlide}
                        showFullscreenButton={showFullscreenButton}
                        showPlayButton={showPlayButton}
                        items={assets.map(a => ({
                            original: a.asset.url,
                            thumbnail: a.asset.thumbUrl,
                            description: 'toto',
                            asset: a.asset,
                            renderItem: this.renderItem,
                        }))}
                    /> : 'Gallery is empty'}
            </div>
        </div>
    }

    renderItem({asset}) {
        console.log('asset', asset);
        if (-1 === asset.mimeType.indexOf('image/')) {
            return this.renderVideo(asset);
        }

        return <div className="image-gallery-image">
            <img
                alt={asset.title || 'Image'}
                src={asset.url} />
            {asset.description ? <span
            className="image-gallery-description">
                    <Description descriptionHtml={asset.description} />
                </span> : ''}
            </div>;
    }
}

export default GalleryLayout;

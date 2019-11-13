import React from 'react';
import ImageGallery from 'react-image-gallery';
import {dataShape} from "../props/dataShape";
import {PropTypes} from 'prop-types';

class GalleryLayout extends React.Component {
    static propTypes = {
        data: dataShape,
        assetSlug: PropTypes.string,
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
                            <source src={item.url} type={'video/mp4'} />
                            Sorry, your browser doesn't support embedded videos.
                        </video>
                    </div>
                    : <div onClick={this.toggleShowVideo.bind(this, item.url)}>
                        <div className='play-button' />
                        <img src={item.thumbUrl} alt={item.title} />
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
        const {assetSlug, data} = this.props;
        const {
            title,
            assets,
        } = data;

        const {
            showFullscreenButton,
            showPlayButton,
        } = this.state;

        let startIndex = 0;
        if (assetSlug) {
            startIndex = assets.findIndex(a => a.slug === assetSlug);
            if (startIndex < 0) {
                startIndex = 0;
            }
        }

        return <div className={`layout-gallery`}>
            <div className="container">

                <h1>{title}</h1>
                <ImageGallery
                    startIndex={startIndex}
                    onSlide={this.onSlide}
                    showFullscreenButton={showFullscreenButton}
                    showPlayButton={showPlayButton}
                    items={assets.map(a => ({
                        original: a.asset.url,
                        thumbnail: a.asset.thumbUrl,
                        renderItem: -1 === a.asset.mimeType.indexOf('image/') ? () => this.renderVideo(a.asset) : undefined,
                    }))}
                />
            </div>
        </div>
    }
}

export default GalleryLayout;

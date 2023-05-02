import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import Description from "./Description";
import videojs from 'video.js'

export default class VideoPlayer extends PureComponent {
    static propTypes = {
        title: PropTypes.string,
        description: PropTypes.string,
        url: PropTypes.string.isRequired,
        previewUrl: PropTypes.string.isRequired,
        alt: PropTypes.string,
        onPlay: PropTypes.func,
        webVTTLink: PropTypes.string,
        fluid: PropTypes.bool,
    };

    state = {
        showVideo: false,
    }

    player = false;

    constructor(props) {
        super(props);

        this.videoRef = React.createRef();
    }

    componentDidMount() {
        if (this.state.showVideo) {
            this.initPlayer();
        }
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        if (this.state.showVideo) {
            this.initPlayer();
        }
    }

    componentWillUnmount() {
        if (this.player) {
            this.player.dispose();
        }
    }

    initPlayer() {
        if (this.player) {
            return;
        }

        const {url, fluid} = this.props;

        this.player = videojs(this.videoRef.current, {
            autoplay: true,
            controls: true,
            fluid,
            sources: [{
                src: url,
                type: 'video/mp4'
            }]
        });
    }

    onPlay = () => {
        const {onPlay} = this.props;

        this.setState({showVideo: true}, () => {
            onPlay && onPlay();
        });
    }

    stop() {
        if (this.player) {
            this.player.pause();
        }
    }

    render() {
        const {
            previewUrl,
            title,
            description,
            webVTTLink,
        } = this.props;

        return <div
            className='video-container'
            onClick={this.onPlay}
        >
            {
                this.state.showVideo ?
                    <div data-vjs-player>
                        <video
                            ref={this.videoRef}
                            className="video-js"
                        >
                            {webVTTLink && <track
                                kind="captions" src={webVTTLink} srcLang="en" label="English"
                                                  default/>}
                        </video>
                    </div>
                    : <div className={'video-preview-wrapper'}>
                        <div className='play-button'/>
                        <img src={previewUrl} alt={title}/>
                        {
                            description &&
                            <span
                                className='image-gallery-description'
                            >
                            <Description descriptionHtml={description}/>
                          </span>
                        }
                    </div>
            }
        </div>
    }
}


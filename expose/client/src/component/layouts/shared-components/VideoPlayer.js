import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import Description from "./Description";
import videojs from 'video.js'

export default class VideoPlayer extends PureComponent {
    static propTypes = {
        title: PropTypes.string,
        description: PropTypes.string,
        url: PropTypes.string.isRequired,
        thumbUrl: PropTypes.string.isRequired,
        alt: PropTypes.string,
        onPlay: PropTypes.func,
        webVTTLink: PropTypes.string,
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

        const {url} = this.props;

        this.player = videojs(this.videoRef.current, {
            autoplay: true,
            controls: true,
            fluid: true,
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

    render() {
        const {
            thumbUrl,
            title,
            description,
        } = this.props;

        return <div className='video-container'>
            {
                this.state.showVideo ?
                    <div data-vjs-player>
                        <video
                            ref={this.videoRef}
                            className="video-js"
                            crossOrigin={'use-credentials'}
                        />
                    </div>
                    : <div onClick={this.onPlay}>
                        <div className='play-button'/>
                        <img src={thumbUrl} alt={title}/>
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


import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import Description from "./Description";
import {Player} from 'video-react';

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

    vttAdded = false;

    constructor(props) {
        super(props);

        this.videoRef = React.createRef();
    }


    onPlay = () => {
        const {onPlay} = this.props;

        this.setState({showVideo: true}, () => {
            onPlay && onPlay();
        });
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        const {webVTTLink} = this.props;
        if (!this.vttAdded && webVTTLink) {
            this.addTextTrack({
                kind: 'captions',
                src: webVTTLink,
                srclang: 'en',
                label: 'English'
            });
        }
    }

    addTextTrack({kind, label, srclang, src}) {
        const {document} = window;
        this.videoRef.addEventListener("loadedmetadata", function() {
            const track = document.createElement("track");
            track.kind = kind;
            track.label = label;
            track.srclang = srclang;
            track.src = src;
        });
    }

    render() {
        const {
            url,
            thumbUrl,
            title,
            description,
        } = this.props;

        return <div className='video-container'>
            {
                this.state.showVideo ?
                    <Player
                        ref={this.videoRef}
                        autoPlay={true}
                    >
                        <source src={url} type={'video/mp4'}/>
                    </Player>
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


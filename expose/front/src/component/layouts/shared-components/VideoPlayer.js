import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';

export default class VideoPlayer extends PureComponent {
    static propTypes = {
        title: PropTypes.string,
        description: PropTypes.string,
        url: PropTypes.string.isRequired,
        thumbUrl: PropTypes.string.isRequired,
        alt: PropTypes.string,
        onPlay: PropTypes.func,
    };

    state = {
        showVideo: false,
    }

    onPlay = () => {
        const {onPlay} = this.props;

        this.setState({showVideo: true}, () => {
            onPlay && onPlay();
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
                    <div className='video-wrapper'>
                        <video controls autoPlay={true}>
                            <source src={url} type={'video/mp4'}/>
                            Sorry, your browser doesn't support embedded videos.
                        </video>
                    </div>
                    : <div onClick={this.onPlay}>
                        <div className='play-button'/>
                        <img src={thumbUrl} alt={title}/>
                        {
                            description &&
                            <span
                                className='image-gallery-description'
                                style={{right: '0', left: 'initial'}}
                            >
                            {description}
                          </span>
                        }
                    </div>
            }
        </div>
    }
}


import React from 'react';
import ImageGallery from 'react-image-gallery';
import PropTypes from "prop-types";

const images = [
    {
        original: 'https://picsum.photos/id/1018/1000/600/',
        thumbnail: 'https://picsum.photos/id/1018/250/150/',
    },
    {
        original: 'https://picsum.photos/id/1015/1000/600/',
        thumbnail: 'https://picsum.photos/id/1015/250/150/',
    },
    {
        original: 'https://picsum.photos/id/1019/1000/600/',
        thumbnail: 'https://picsum.photos/id/1019/250/150/',
    },
];

class Gallery extends React.Component {
    static propTypes = {
        theme: PropTypes.string,
        assets: PropTypes.array.isRequired,
    };

    render() {
        const {assets} = this.props;

        return <ImageGallery
            items={assets.map(a => ({
                original: a.
            }))}
        />;
    }
}

export default Gallery;

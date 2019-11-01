import React from 'react';
import ImageGallery from 'react-image-gallery';
import {dataShape} from "../props/dataShape";

class GalleryLayout extends React.Component {
    static propTypes = {
        data: dataShape,
    };

    render() {
        const {
            title,
            assets,
        } = this.props.data;

        return <div className={`layout-gallery`}>
            <div className="container">

                <h1>{title}</h1>
                <ImageGallery
                    items={assets.map(a => ({
                        original: a.asset.url,
                        thumbnail: a.asset.thumbUrl,
                    }))}
                />
            </div>
        </div>
    }
}

export default GalleryLayout;

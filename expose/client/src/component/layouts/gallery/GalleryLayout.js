import React from 'react';
import ImageGallery from 'react-image-gallery';
import {dataShape} from "../../props/dataShape";
import {PropTypes} from 'prop-types';
import Description from "../shared-components/Description";
import {defaultMapProps, initMapbox} from "../mapbox/MapboxLayout";
import mapboxgl from 'mapbox-gl';
import DownloadButton from "../shared-components/DownloadButton";
import {
    downloadContainerDefaultState, onDownload,
    renderDownloadTermsModal,
    renderDownloadViaEmail
} from "../shared-components/DownloadViaEmailProxy";
import AssetProxy from "../shared-components/AssetProxy";
import PublicationHeader from "../shared-components/PublicationHeader";
import {Trans} from "react-i18next";

class GalleryLayout extends React.Component {
    static propTypes = {
        data: dataShape,
        assetId: PropTypes.string,
        options: PropTypes.object,
        mapOptions: PropTypes.object,
    };

    state = {
        showFullscreenButton: true,
        showPlayButton: true,
        currentIndex: null,
        ...downloadContainerDefaultState,
    };

    map;

    constructor(props) {
        super(props);

        this.mapContainer = React.createRef();
        this.sliderRef = React.createRef();
    }

    componentDidMount() {
        if (this.props.options.displayMap) {
            this.initMap();
        }
    }

    static getDerivedStateFromProps(props, state = {}) {
        const displayControls = shouldDisplayControl(props, state.currentIndex || 0);

        return {
            showFullscreenButton: displayControls,
            showPlayButton: displayControls,
        };
    }

    initMap() {
        if (!this.mapContainer.current) {
            return;
        }

        const {data, options, mapOptions} = this.props;

        let locationAsset = data.assets.filter(a => a.lat)[0];
        locationAsset = locationAsset || mapOptions;

        switch (options.mapProvider) {
            default:
            case 'mapbox':
                this.map = initMapbox(this.mapContainer.current, {
                    ...defaultMapProps,
                    ...(locationAsset ? {
                        lat: locationAsset.lat,
                        lng: locationAsset.lng,
                    } : {}),
                    zoom: locationAsset && locationAsset.zoom ? locationAsset.zoom : 5
                });
                data.assets.forEach((a, pos) => {
                    if (!(a.lat && a.lng)) {
                        return;
                    }
                    const marker = new mapboxgl.Marker()
                        .setLngLat([
                            a.lng,
                            a.lat,])
                        .addTo(this.map)
                    ;

                    marker.getElement().addEventListener('click', () => {
                        this.goto(pos);
                    });
                });
                break;
        }
    }

    goto(index) {
        if (!this.sliderRef.current) {
            return;
        }
        this.sliderRef.current.slideToIndex(index);
    }

    onSlide = (offset) => {
        const displayControls = shouldDisplayControl(this.props, offset);

        this.setState({
            currentIndex: offset,
            showFullscreenButton: displayControls,
            showPlayButton: displayControls,
        });

        if (this.map) {
            const asset = this.props.data.assets[offset].asset;
            if (asset.lat && asset.lng) {
                this.map.flyTo({
                    center: [
                        asset.lng,
                        asset.lat,
                    ],
                    essential: true
                });
            }
        }
    };

    render() {
        const {assetId, data, options} = this.props;
        const {currentIndex} = this.state;
        const {
            assets,
            downloadEnabled,
        } = data;

        const {
            showFullscreenButton,
            showPlayButton,
        } = this.state;

        let startIndex = 0;
        if (currentIndex) {
            startIndex = currentIndex;
        } else if (assetId) {
            startIndex = assets.findIndex(a => a.id === assetId);
            if (startIndex < 0) {
                startIndex = assets.findIndex(a => a.slug === assetId);
                if (startIndex < 0) {
                    startIndex = 0;
                }
            }
        }

        return <div className={`layout-gallery`}>
            {renderDownloadTermsModal.call(this)}
            {renderDownloadViaEmail.call(this)}
            <PublicationHeader
                data={data}
            />
            {assets.length > 0 ?
                <ImageGallery
                    ref={this.sliderRef}
                    startIndex={startIndex}
                    onSlide={this.onSlide}
                    showFullscreenButton={showFullscreenButton}
                    showPlayButton={showPlayButton}
                    items={assets.map(a => ({
                        original: a.previewUrl,
                        thumbnail: a.thumbUrl,
                        description: a.description,
                        asset: a,
                        downloadEnabled,
                        renderItem: this.renderItem,
                    }))}
                /> : <Trans i18nKey={'gallery.empty'}>
                    Gallery is empty
                </Trans>}
            {options.displayMap ? this.renderMap() : ''}
        </div>
    }

    renderMap() {
        return <div className={'gallery-map'}>
            <div
                className={'map-container'}
                ref={this.mapContainer}
            />
        </div>
    }

    renderItem = ({asset, downloadEnabled}) => {
        return <div className="image-gallery-image layout-asset-container">
            {downloadEnabled && asset.downloadUrl ? <div
                className="download-btn">
                <DownloadButton
                    downloadUrl={asset.downloadUrl}
                    onDownload={onDownload.bind(this)}
                />
            </div> : ''}
            <AssetProxy asset={asset}/>
            {asset.description ? <div
                className="image-gallery-description">
                <Description descriptionHtml={asset.description}/>
            </div> : ''}
        </div>;
    }
}

export default GalleryLayout;

function shouldDisplayControl(props, offset) {
    const asset = props.data.assets[offset];

    if (asset) {
        return !asset.mimeType.startsWith('video/');
    }

    return false;
}

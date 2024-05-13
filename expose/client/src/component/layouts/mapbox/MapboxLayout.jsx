import React from 'react';
// import { PropTypes } from 'prop-types'
import mapboxgl from 'mapbox-gl';
import config from '../../../config';
import Description from '../shared-components/Description';
import {getBrowserLanguage} from './browserLang';
import PublicationHeader from '../shared-components/PublicationHeader';
import AssetProxy from '../shared-components/AssetProxy';
import {Trans} from 'react-i18next';
import {logAssetView} from '../../../lib/log';
import {getThumbPlaceholder} from '../shared-components/placeholders';
import {getTranslatedDescription} from '../../../i18n';

export function initMapbox(mapContainer, {lng, lat, zoom}) {
    mapboxgl.accessToken = config.mapBoxToken;

    let map = new mapboxgl.Map({
        container: mapContainer,
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [lng, lat],
        zoom,
        attributionControl: false,
    });

    map.addControl(new mapboxgl.NavigationControl());
    map.addControl(
        new mapboxgl.AttributionControl({
            compact: true,
        })
    );

    map.on('load', () => {
        map.setLayoutProperty('country-label', 'text-field', [
            'get',
            'name_' + getBrowserLanguage(),
        ]);
    });

    return map;
}

export const defaultMapProps = {
    lng: 2.32,
    lat: 48.8,
    zoom: 2,
    mapLayout: 'light-v10',
};

function filterGeoAssets(assets) {
    return assets.filter(a => a.lat && a.lng);
}

const maxThumbSize = 100;

const mapLayouts = {
    'light-v10': 'Light',
    'dark-v10': 'Dark',
    'outdoors-v11': 'Outdoors',
    'satellite-v9': 'Satellite',
};

class MapboxLayout extends React.Component {
    // static propTypes = {
    //     data: dataShape,
    //     assetId: PropTypes.string,
    //     mapOptions: PropTypes.object,
    // }

    constructor(props) {
        super(props);

        this.state = {
            ...defaultMapProps,
            ...(props.mapOptions || {}),
            assets: filterGeoAssets(props.data.assets),
            assetId: props.assetId,
        };

        this.mapContainer = React.createRef();
    }

    componentDidMount() {
        this.initMap();
        window.addEventListener('resize', this.onResize);
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        if (this.state.assetId && prevState.assetId !== this.state.assetId) {
            logAssetView(this.state.assetId);
        }
    }

    componentWillUnmount() {
        window.removeEventListener('resize', this.onResize);
    }

    onResize = () => {
        this.map && this.map.resize();
    };

    initMap() {
        if (!this.mapContainer.current) {
            return;
        }

        const {data} = this.props;
        const locationAsset = data.assets.filter(a => a.lat)[0];

        this.map = initMapbox(this.mapContainer.current, {
            ...this.state,
            ...(locationAsset || {}),
        });

        this.map.on('move', () => {
            this.setState({
                lng: this.map.getCenter().lng.toFixed(4),
                lat: this.map.getCenter().lat.toFixed(4),
                zoom: this.map.getZoom().toFixed(2),
            });
        });
        this.map.on('load', () => {
            this.configureAssets();
            this.map.on('style.load', this.configureAssets);
        });
    }

    configureAssets = () => {
        const {data} = this.props;
        const {layoutOptions} = data;

        if (layoutOptions.displayMapPins) {
            this.configureAssetPins();
        } else {
            this.configureAssetThumbs();
        }
    };

    async configureAssetThumbs() {
        const images = await Promise.all(
            this.state.assets.map(a => {
                return new Promise(resolve => {
                    this.map.loadImage(
                        a.thumbUrl || getThumbPlaceholder(a.mimeType),
                        async (err, img) => {
                            if (err) {
                                console.error('err', err);

                                return;
                            }
                            let width, height;
                            if (img.width > img.height) {
                                height =
                                    (img.height * maxThumbSize) / img.width;
                                width = maxThumbSize;
                            } else {
                                width = (img.width * maxThumbSize) / img.height;
                                height = maxThumbSize;
                            }

                            resolve({
                                id: a.id,
                                img: await createImageBitmap(img, {
                                    resizeWidth: width,
                                    resizeHeight: height,
                                }),
                            });
                        }
                    );
                });
            })
        );

        this.map.addSource('assets', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: this.state.assets.map(a => {
                    return {
                        type: 'Feature',
                        geometry: {
                            type: 'Point',
                            coordinates: [a.lng, a.lat],
                        },
                        properties: {
                            assetId: a.id,
                        },
                    };
                }),
            },
        });

        this.map.on('click', 'assets', e => {
            this.setState({assetId: e.features[0].properties.assetId});
        });

        images.forEach(({id, img}) => {
            this.map.addImage(id, img);
        });

        this.map.addLayer({
            id: 'assets',
            type: 'symbol',
            source: 'assets',
            layout: {
                'icon-image': '{assetId}',
                'icon-allow-overlap': true,
            },
        });
    }

    configureAssetPins() {
        const popup = new mapboxgl.Popup({
            closeButton: false,
            closeOnClick: false,
            offset: 25,
        });

        this.state.assets.forEach(a => {
            const {asset} = a;

            const marker = new mapboxgl.Marker()
                .setLngLat([asset.lng, asset.lat])
                .addTo(this.map);

            marker.getElement().addEventListener('mouseenter', () => {
                popup
                    .setLngLat([asset.lng, asset.lat])
                    .setHTML(`<img class="thumb" src="${asset.thumbUrl}"/>`)
                    .addTo(this.map);
            });

            marker.getElement().addEventListener('mouseleave', () => {
                popup.remove();
            });

            marker.getElement().addEventListener('click', () => {
                this.setState({assetId: asset.id});
            });
        });
    }

    changeMapLayout = e => {
        const mapLayout = e.target.value;
        this.setState({mapLayout});
        this.map.setStyle('mapbox://styles/mapbox/' + mapLayout);
    };

    render() {
        const {data} = this.props;
        const {assets} = this.state;

        if (assets.length === 0) {
            return <Trans i18nKey={'map.empty'}>Map is empty</Trans>;
        }

        return (
            <div className={'layout-mapbox'}>
                <PublicationHeader data={data} />
                <div className="map-wrapper">
                    <div className="map-controls">
                        <div className={'coordinates'}>
                            Longitude: {this.state.lng} | Latitude:{' '}
                            {this.state.lat} | Zoom: {this.state.zoom}
                        </div>
                        <div className="map-layout">
                            <select onChange={this.changeMapLayout}>
                                {Object.keys(mapLayouts).map(k => (
                                    <option key={k} value={k}>
                                        {mapLayouts[k]}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className={'map-container'} ref={this.mapContainer} />
                </div>
                <div>{this.renderAsset()}</div>
            </div>
        );
    }

    renderAsset() {
        const {assetId} = this.state;

        if (!assetId) {
            return '';
        }

        const asset = this.state.assets.find(a => a.asset.id === assetId).asset;

        return (
            <div className={'image-full'}>
                <AssetProxy asset={asset} />
                <Description
                    descriptionHtml={getTranslatedDescription(asset)}
                />
            </div>
        );
    }
}

export default MapboxLayout;

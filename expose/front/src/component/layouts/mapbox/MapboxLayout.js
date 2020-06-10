import React from 'react';
import {PropTypes} from 'prop-types';
import {dataShape} from "../../props/dataShape";
import mapboxgl from 'mapbox-gl';
import config from "../../../lib/config";
import Description from "../shared-components/Description";
import MapboxLanguage from '@mapbox/mapbox-gl-language';

export function initMapbox(mapContainer, {lng, lat, zoom}) {
    mapboxgl.accessToken = config.get('mapBoxToken');

    let map = new mapboxgl.Map({
        container: mapContainer,
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [lng, lat],
        zoom,
        attributionControl: false
    });

    map.addControl(new mapboxgl.NavigationControl());
    map.addControl(new MapboxLanguage());
    map.addControl(new mapboxgl.AttributionControl({
        compact: true,
    }));

    return map;
}

export const defaultMapProps = {
    lng: 2.32,
    lat: 48.8,
    zoom: 2,
    mapLayout: 'light-v10',
};

function filterGeoAssets(assets) {
    return assets.filter(a => a.asset.lat && a.asset.lng);
}

const maxThumbSize = 100;

const mapLayouts = {
    'light-v10': 'Light',
    'dark-v10': 'Dark',
    'outdoors-v11': 'Outdoors',
    'satellite-v9': 'Satellite',
};

class MapboxLayout extends React.Component {
    static propTypes = {
        data: dataShape,
        assetId: PropTypes.string,
        mapOptions: PropTypes.object,
    };

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

    componentWillUnmount() {
        window.removeEventListener('resize', this.onResize);
    }

    onResize = () => {
        this.map && this.map.resize();
    }

    initMap() {
        if (!this.mapContainer.current) {
            return;
        }

        const {data} = this.props;
        const locationAsset = data.assets.filter(a => a.asset.lat)[0].asset;

        this.map = initMapbox(this.mapContainer.current, {
            ...this.state,
            ...(locationAsset ? locationAsset : {}),
        });

        this.map.on('move', () => {
            this.setState({
                lng: this.map.getCenter().lng.toFixed(4),
                lat: this.map.getCenter().lat.toFixed(4),
                zoom: this.map.getZoom().toFixed(2)
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
    }

    async configureAssetThumbs() {
        const images = await Promise.all(this.state.assets.map(a => {
            const {asset} = a;

            return new Promise(resolve => {
                this.map.loadImage(
                    asset.thumbUrl,
                    async (err, img) => {
                        let width, height;
                        if (img.width > img.height) {
                            height = img.height * maxThumbSize / img.width;
                            width = maxThumbSize;
                        } else {
                            width = img.width * maxThumbSize / img.height;
                            height = maxThumbSize;
                        }

                        resolve({
                            id: asset.id,
                            img: await createImageBitmap(img, {
                                resizeWidth: width,
                                resizeHeight: height,
                            }),
                        })
                    }
                );
            });
        }));

        this.map.addSource('assets', {
            'type': 'geojson',
            'data': {
                'type': 'FeatureCollection',
                'features': this.state.assets.map(a => {
                    const {asset} = a;

                    return {
                        'type': 'Feature',
                        'geometry': {
                            'type': 'Point',
                            'coordinates': [asset.lng, asset.lat]
                        },
                        'properties': {
                            assetId: asset.id,
                        }
                    }
                })
            }
        });

        this.map.on('click', 'assets', (e) => {
            this.setState({assetId: e.features[0].properties.assetId});
        });

        images.forEach(({id, img}) => {
            this.map.addImage(id, img);
        });

        this.map.addLayer({
            'id': 'assets',
            'type': 'symbol',
            'source': 'assets',
            'layout': {
                'icon-image': '{assetId}',
                'icon-allow-overlap': true
            }
        });

    }

    configureAssetPins() {
        const popup = new mapboxgl.Popup({
            closeButton: false,
            closeOnClick: false,
            offset: 25
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

    changeMapLayout = (e) => {
        const mapLayout = e.target.value;
        this.setState({mapLayout});
        this.map.setStyle('mapbox://styles/mapbox/' + mapLayout);
    }

    render() {
        const {data} = this.props;
        const {assets} = this.state;

        if (assets.length === 0) {
            return 'Map is empty';
        }

        return <div className={'layout-mapbox'}>
            <Description
                descriptionHtml={data.description}
            />
            <div className="map-wrapper">
                <div className="map-controls">
                    <div className={'coordinates'}>Longitude: {this.state.lng} | Latitude: {this.state.lat} |
                        Zoom: {this.state.zoom}</div>
                    <div className="map-layout">
                        <select
                            onChange={this.changeMapLayout}
                        >
                            {Object.keys(mapLayouts).map(k => <option
                                key={k}
                                value={k}>{mapLayouts[k]}</option>)}
                        </select>
                    </div>
                </div>

                <div
                    className={'map-container'}
                    ref={this.mapContainer}
                />
            </div>
            <div>
                {this.renderAsset()}
            </div>
        </div>
    }

    renderAsset() {
        const {assetId} = this.state;

        if (!assetId) {
            return '';
        }

        const asset = this.state.assets.find(a => a.asset.id === assetId).asset;

        return <div className={'image-full'}>
            <img src={asset.url} alt={asset.title || 'Image'}/>
            <Description descriptionHtml={asset.description}/>
        </div>
    }
}

export default MapboxLayout;

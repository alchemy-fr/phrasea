import React from 'react';
import {PropTypes} from 'prop-types';
import {dataShape} from "../../props/dataShape";
import mapboxgl from 'mapbox-gl';
import config from "../../../lib/config";
import Description from "../shared-components/Description";

export function initMapbox(mapContainer, {lng, lat, zoom}) {
    mapboxgl.accessToken = config.get('mapBoxToken');

    return new mapboxgl.Map({
        container: mapContainer,
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [lng, lat],
        zoom: zoom
    });
}

export const defaultMapProps = {
    lng: 5,
    lat: 34,
    zoom: 2,
};

function filterGeoAssets(assets) {
    return assets.filter(a => a.asset.lat && a.asset.lng);
}

const maxThumbSize = 100;

class MapboxLayout extends React.Component {
    static propTypes = {
        data: dataShape,
        assetId: PropTypes.string,
    };

    constructor(props) {
        super(props);

        this.state = {
            ...defaultMapProps,
            assets: filterGeoAssets(props.data.assets),
            assetId: props.assetId,
        };

        this.mapContainer = React.createRef();
    }

    componentDidMount() {
        this.initMap();
    }

    initMap() {
        if (!this.mapContainer.current) {
            return;
        }

        const locationAsset = this.props.data.assets.filter(a => a.asset.lat)[0].asset;

        const map = initMapbox(this.mapContainer.current, {
            ...this.state,
            ...(locationAsset ? locationAsset : {}),
        });
        map.on('move', () => {
            this.setState({
                lng: map.getCenter().lng.toFixed(4),
                lat: map.getCenter().lat.toFixed(4),
                zoom: map.getZoom().toFixed(2)
            });
        });
        map.on('load', async () => {
            const images = await Promise.all(this.state.assets.map(a => {
                const {asset} = a;

                return new Promise(resolve => {
                    map.loadImage(
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

            images.forEach(({id, img}) => {
                map.addImage(id, img);
            });

            map.addSource('assets', {
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
            map.addLayer({
                'id': 'assets',
                'type': 'symbol',
                'source': 'assets',
                'layout': {
                    'icon-image': '{assetId}',
                    'icon-allow-overlap': true
                }
            });

            map.on('click', 'assets', (e) => {
                console.log('e.features[0].properties.assetId', e.features[0].properties.assetId);
                this.setState({assetId: e.features[0].properties.assetId});
            });
        });
    }

    render() {
        const {data} = this.props;
        const {assets} = this.state;

        if (assets.length === 0) {
            return 'Map is empty';
        }

        return <div className={'layout-mapbox'}>
            <div className="container">
                <Description
                    descriptionHtml={data.description}
                />
                <div className="map-wrapper">
                    <div className='coordinates'>
                        <div>Longitude: {this.state.lng} | Latitude: {this.state.lat} | Zoom: {this.state.zoom}</div>
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

import Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';

Leaflet.Icon.Default.imagePath = '';

Leaflet.Icon.Default.mergeOptions({
    iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
    iconUrl: require('leaflet/dist/images/marker-icon.png'),
    shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
});

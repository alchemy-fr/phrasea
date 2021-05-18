import {PropTypes} from 'prop-types';

export const dataShape = PropTypes.shape({
    title: PropTypes.string.isRequired,
    assets: PropTypes.array,
    children: PropTypes.array,
    parent: PropTypes.object,
    downloadViaEmail: PropTypes.bool,
});

export const assetShape = PropTypes.shape({
    downloadUrl: PropTypes.string.isRequired,
    thumbUrl: PropTypes.string.isRequired,
    originalName: PropTypes.string,
    mimeType: PropTypes.string.isRequired,
    subDefinitions: PropTypes.array.isRequired,
});

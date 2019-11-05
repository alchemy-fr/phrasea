import {PropTypes} from 'prop-types';

export const dataShape = PropTypes.shape({
    title: PropTypes.string.isRequired,
    assets: PropTypes.array.isRequired,
});

export const assetShape = PropTypes.shape({
    downloadUrl: PropTypes.string.isRequired,
    thumbUrl: PropTypes.string.isRequired,
    originalName: PropTypes.string,
    mimeType: PropTypes.string.isRequired,
    subDefinitions: PropTypes.array.isRequired,
});

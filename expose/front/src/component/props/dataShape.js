import {PropTypes} from 'prop-types';

export const dataShape = PropTypes.shape({
    title: PropTypes.string.isRequired,
    assets: PropTypes.array.isRequired,
});

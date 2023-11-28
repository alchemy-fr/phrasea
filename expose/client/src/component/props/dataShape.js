import { PropTypes } from 'prop-types'

export const dataShape = PropTypes.shape({
    title: PropTypes.string.isRequired,
    assets: PropTypes.array,
    children: PropTypes.array,
    parent: PropTypes.object,
    downloadViaEmail: PropTypes.bool,
    downloadEnabled: PropTypes.bool,
})

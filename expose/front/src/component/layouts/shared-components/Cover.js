import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';

export default class Cover extends PureComponent {
    static propTypes = {
        url: PropTypes.string.isRequired,
        alt: PropTypes.string,
    };

    render() {
        const {url, alt} = this.props;

        return <div
            className="cover"
        >
            <img src={url} alt={alt || 'Cover'} />
        </div>
    }

}


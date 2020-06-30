import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';

export default class Copyright extends PureComponent {
    static propTypes = {
        text: PropTypes.string,
    };

    render() {
        const {text} = this.props;

        if (!text) {
            return '';
        }

        return <div
            className="copy-text"
        >
            {text}
        </div>
    }

}


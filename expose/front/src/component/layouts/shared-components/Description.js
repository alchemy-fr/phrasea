import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';

export default class Description extends PureComponent {
    static propTypes = {
        descriptionHtml: PropTypes.string,
    };

    render() {
        const {descriptionHtml} = this.props;

        if (!descriptionHtml) {
            return '';
        }

        return <div
            className="description"
            dangerouslySetInnerHTML={{
                __html: descriptionHtml,
            }}
        />
    }

}


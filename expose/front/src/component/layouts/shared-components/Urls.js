import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';

export default class Urls extends PureComponent {
    static propTypes = {
        urls: PropTypes.object.isRequired,
    };

    render() {
        const {urls} = this.props;

        if (urls.length === 0) {
            return '';
        }

        return <ul
            className="urls"
        >
            {Object.keys(urls).map(k => <li
                key={k}
            >
                <a href={k}>
                    {urls[k]}
                </a>
            </li>)}
        </ul>
    }

}


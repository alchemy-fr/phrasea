import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';

export default class Urls extends PureComponent {
    static propTypes = {
        urls: PropTypes.array.isRequired,
    };

    render() {
        const {urls} = this.props;

        if (urls.length === 0) {
            return '';
        }

        return <ul
            className="urls"
        >
            {urls.map(url => <li
                key={url.url}
            >
                <a href={url.url}>
                    {url.text}
                </a>
            </li>)}
        </ul>
    }

}


import React, {Component} from 'react';
import PropTypes from "prop-types";

export default class Container extends Component {
    static propTypes = {
        title: PropTypes.string,
    };

    static defaultProps = {
        title: 'Uploader.'
    };

    render() {
        const {title, children} = this.props;

        return (
            <div className="container">
                <header>
                    <h1>{title}</h1>
                </header>
                <div>
                    {children}
                </div>
            </div>
        );
    }
}

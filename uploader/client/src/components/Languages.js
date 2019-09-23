import React, {Component} from 'react';
import PropTypes from "prop-types";
import i18n from '../locales/i18n';

export default class Languages extends Component {
    static propTypes = {
        languages: PropTypes.array,
    };

    static defaultProps = {
        languages: [
            'en',
            'fr',
            'es',
        ],
    };

    changeLanguage = (lng) => {
        i18n.changeLanguage(lng);
    };

    render() {
        const {languages} = this.props;

        return (
            <div className="languages">
                {languages.map(l =>
                    <button
                        key={l}
                        onClick={() => this.changeLanguage(l)}
                    >{l.toUpperCase()}</button>
                )}
            </div>
        );
    }
}

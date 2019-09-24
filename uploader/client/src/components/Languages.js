import React, {Component} from 'react';
import i18n from '../locales/i18n';
import config from "../config";

export default class Languages extends Component {
    changeLanguage = (lng) => {
        i18n.changeLanguage(lng);
    };

    render() {
        return (
            <div className="languages">
                {config.getAvailableLocales().map(l =>
                    <button
                        key={l}
                        onClick={() => this.changeLanguage(l)}
                    >{l.toUpperCase()}</button>
                )}
            </div>
        );
    }
}

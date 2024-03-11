import React, {Component} from 'react';
import i18n from '../locales/i18n';
import config from '../config';

export default class Languages extends Component {
    changeLanguage = lng => {
        i18n.changeLanguage(lng);
    };

    render() {
        const locales = config.locales;

        if (locales.length <= 1) {
            return null;
        }

        return (
            <div className="languages">
                {locales.map(l => (
                    <React.Fragment key={l}>
                        {' '}
                        <a href={'#'} onClick={() => this.changeLanguage(l)}>
                            {l.toUpperCase()}
                        </a>
                    </React.Fragment>
                ))}
            </div>
        );
    }
}

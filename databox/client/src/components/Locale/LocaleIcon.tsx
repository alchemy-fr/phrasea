import {CircleFlag} from 'react-circle-flags';
import React, {ImgHTMLAttributes} from 'react';
import {languageCountryMap} from '@alchemy/i18n/src/Locale/locales';

type Props = {
    locale: string;
    region?: string;
    height?: ImgHTMLAttributes<any>['height'];
};

export default function LocaleIcon({region, locale, height = 25}: Props) {
    let countryCode = region;
    if (!countryCode) {
        const parts = locale.replace(/-/g, '_').split('_');
        const l = parts[0];
        if (parts.length > 1) {
            countryCode = parts[parts.length - 1];
        } else {
            countryCode = languageCountryMap[l] || l;
        }
    }

    return (
        <CircleFlag countryCode={countryCode?.toLowerCase()} height={height} />
    );
}

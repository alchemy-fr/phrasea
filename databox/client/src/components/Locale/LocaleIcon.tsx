import {CircleFlag} from 'react-circle-flags';
import React, {ImgHTMLAttributes} from 'react';

type Props = {
    locale: string;
    height?: ImgHTMLAttributes<any>['height'];
};

export default function LocaleIcon({locale, height = 25}: Props) {
    const l = locale.replace('-', '_').split('_')[0];

    const countryEquivalents: Record<string, string> = {
        en: 'gb',
        pt: 'br',
        zh: 'cn',
        he: 'il',
        iw: 'il',
        fa: 'ir',
        ji: 'ye',
    };

    return (
        <CircleFlag countryCode={countryEquivalents[l] || l} height={height} />
    );
}

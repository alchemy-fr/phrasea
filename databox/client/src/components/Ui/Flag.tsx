import React, {FunctionComponent} from 'react';
import {flagIcons} from './flagIcons';
import {Icon, IconProps} from '@mui/material';
import { useTranslation } from 'react-i18next';

type Props = {
    locale: string;
};

function getLocaleCountryFlag(locale: string): FunctionComponent | undefined {
    const matches = locale.match(
        /^([a-z]{2})[-_]?([a-z]{2})?([-_][a-z0-9]+)?$/i
    );
    if (matches) {
        if (matches[2]) {
            const c = matches[2].toLocaleLowerCase();
            // eslint-disable-next-line no-prototype-builtins
            if (flagIcons.hasOwnProperty(c)) {
                return (flagIcons as Record<string, FunctionComponent>)[c];
            }
        }

        const c = matches[1].toLocaleLowerCase();
        // eslint-disable-next-line no-prototype-builtins
        if (flagIcons.hasOwnProperty(c)) {
            return (flagIcons as Record<string, FunctionComponent>)[c];
        }
    }

    const c = locale.toLocaleLowerCase();
    // eslint-disable-next-line no-prototype-builtins
    if (flagIcons.hasOwnProperty(c)) {
        return (flagIcons as Record<string, FunctionComponent>)[c];
    }
}

export default function Flag({locale, ...iconProps}: Props & IconProps) {
    if (locale === 'en') {
        locale = 'us';
    }
    const component = getLocaleCountryFlag(locale);

    if (component) {
        return (
            <Icon fontSize={'medium'} {...iconProps}>
                {React.createElement(component)}
            </Icon>
        );
    }

    return null;
}

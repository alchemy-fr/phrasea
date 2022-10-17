import 'react-i18next';
import loginNs from '../locales/en_US/login.json';

// react-i18next versions higher than 11.11.0
declare module 'react-i18next' {
    interface CustomTypeOptions {
        // custom namespace type if you changed it
        defaultNS: 'loginNs';
        // custom resources type
        resources: {
            login: typeof loginNs;
        };
    };
};

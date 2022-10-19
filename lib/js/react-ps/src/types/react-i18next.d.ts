import 'react-i18next';
import loginNs from '../locales/domains/en_US/login.json';

declare module 'react-i18next' {
    interface CustomTypeOptions {
        // custom namespace type if you changed it
        defaultNS: 'login';
        // custom resources type
        resources: {
            login: typeof loginNs;
        };
    }
}

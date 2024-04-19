import en from './en/app.json';
import fr from './fr/app.json';
import enApi from '@alchemy/api/translations/en.json';
import frApi from '@alchemy/api/translations/fr.json';
import enNav from '@alchemy/navigation/translations/en.json';
import frNav from '@alchemy/navigation/translations/fr.json';

const enMerged = {
    ...en,
    lib: {
        ...enApi.lib,
        ...enNav.lib,
    },
};

const frMerged = {
    ...fr,
    lib: {
        ...frApi.lib,
        ...frNav.lib,
    },
};

export {enMerged as en, frMerged as fr};

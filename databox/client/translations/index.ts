import en from './en.json';
import fr from './fr.json';
import es from './es.json';
import de from './de.json';
import zh from './zh.json';

import enApi from '@alchemy/api/translations/en.json';
import frApi from '@alchemy/api/translations/fr.json';
import esApi from '@alchemy/api/translations/es.json';
import deApi from '@alchemy/api/translations/de.json';
import zhApi from '@alchemy/api/translations/zh.json';

import enNav from '@alchemy/navigation/translations/en.json';
import frNav from '@alchemy/navigation/translations/fr.json';
import esNav from '@alchemy/navigation/translations/es.json';
import deNav from '@alchemy/navigation/translations/de.json';
import zhNav from '@alchemy/navigation/translations/zh.json';

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

const esMerged = {
    ...es,
    lib: {
        ...esApi.lib,
        ...esNav.lib,
    },
};

const deMerged = {
    ...de,
    lib: {
        ...deApi.lib,
        ...deNav.lib,
    },
};

const zhMerged = {
    ...zh,
    lib: {
        ...zhApi.lib,
        ...zhNav.lib,
    },
};

export {enMerged as en, frMerged as fr, esMerged as es, deMerged as de, zhMerged as zh};

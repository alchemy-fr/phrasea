import en from './en.json';
import fr from './fr.json';
import es from './es.json';
import de from './de.json';

import enApi from '@alchemy/api/translations/en.json';
import frApi from '@alchemy/api/translations/fr.json';
import esApi from '@alchemy/api/translations/es.json';
import deApi from '@alchemy/api/translations/de.json';

import enNav from '@alchemy/navigation/translations/en.json';
import frNav from '@alchemy/navigation/translations/fr.json';
import esNav from '@alchemy/navigation/translations/es.json';
import deNav from '@alchemy/navigation/translations/de.json';

import enUi from '@alchemy/phrasea-ui/translations/en.json';
import frUi from '@alchemy/phrasea-ui/translations/fr.json';
import esUi from '@alchemy/phrasea-ui/translations/es.json';
import deUi from '@alchemy/phrasea-ui/translations/de.json';

import enAuth from '@alchemy/react-auth/translations/en.json';
import frAuth from '@alchemy/react-auth/translations/fr.json';
import esAuth from '@alchemy/react-auth/translations/es.json';
import deAuth from '@alchemy/react-auth/translations/de.json';

import enForm from '@alchemy/react-form/translations/en.json';
import frForm from '@alchemy/react-form/translations/fr.json';
import esForm from '@alchemy/react-form/translations/es.json';
import deForm from '@alchemy/react-form/translations/de.json';

const enMerged = {
    ...en,
    lib: {
        ...enApi.lib,
        ...enNav.lib,
        ...enUi.lib,
        ...enAuth.lib,
        ...enForm.lib,
    },
};

const frMerged = {
    ...fr,
    lib: {
        ...frApi.lib,
        ...frNav.lib,
        ...frUi.lib,
        ...frAuth.lib,
        ...frForm.lib,
    },
};

const esMerged = {
    ...es,
    lib: {
        ...esApi.lib,
        ...esNav.lib,
        ...esUi.lib,
        ...esAuth.lib,
        ...esForm.lib,
    },
};

const deMerged = {
    ...de,
    lib: {
        ...deApi.lib,
        ...deNav.lib,
        ...deUi.lib,
        ...deAuth.lib,
        ...deForm.lib,
    },
};

export {
    enMerged as en,
    frMerged as fr,
    esMerged as es,
    deMerged as de,
};

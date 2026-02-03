import en from './en.json';
import fr from './fr.json';
import es from './es.json';
import de from './de.json';

import {translations} from '@alchemy/phrasea-framework';

const enMerged = {
    ...en,
    ...translations.en,
};

const frMerged = {
    ...fr,
    ...translations.fr,
};

const esMerged = {
    ...es,
    ...translations.es,
};

const deMerged = {
    ...de,
    ...translations.de,
};

export {enMerged as en, frMerged as fr, esMerged as es, deMerged as de};

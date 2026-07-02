import {TFunction} from '@alchemy/i18n';
import {Privacy} from '../api/privacy.ts';

export function getPrivacyTranslations(t: TFunction): Record<Privacy, string> {
    return {
        [Privacy.Secret]: t('privacy.secret', 'Secret'),
        [Privacy.PrivateInWorkspace]: t(
            'privacy.private_in_workspace',
            'Private in workspace'
        ),
        [Privacy.PublicInWorkspace]: t(
            'privacy.public_in_workspace',
            'Public in workspace'
        ),
        [Privacy.Private]: t('privacy.private', 'Private'),
        [Privacy.PublicForUsers]: t(
            'privacy.public_for_users',
            'Public for users'
        ),
        [Privacy.Public]: t('privacy.public', 'Public'),
    };
}

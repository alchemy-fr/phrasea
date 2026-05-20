import {TFunction} from '@alchemy/i18n';

export function getAclDescriptions(t: TFunction) {
    return {
        aclOperatorDesc: t(
            'acl.permission.common.operator.desc',
            'edit title or tags, move to another collection, edit renditions, view file versions'
        ),
    };
}

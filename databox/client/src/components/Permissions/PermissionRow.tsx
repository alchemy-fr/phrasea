import {ChangeEvent, useState} from 'react';
import {Ace, UserType} from '../../types';
import {Button, Checkbox, Skeleton} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {
    AclExtraPermission,
    OnMaskChange,
    PermissionDefinition,
    PermissionType,
} from './permissionsTypes.ts';

type Props = {
    onMaskChange: OnMaskChange;
    onDelete: (userType: UserType, userId: string | null) => void;
    userName: string | undefined;
    definitions: PermissionDefinition[];
    hasAll?: boolean | undefined;
} & Ace;

function isAllChecked(mask: number, allMask: number): boolean | null {
    return allMask === mask ? true : mask === 0 ? false : null;
}

export default function PermissionRow({
    mask: initMask,
    userName,
    definitions,
    userType,
    userId,
    metadata: initMetadata,
    onMaskChange,
    onDelete,
    resolving,
    hasAll,
}: Props) {
    const {t} = useTranslation();
    const [mask, setMask] = useState(initMask);
    const [metadata, setMetadata] = useState<AclExtraPermission[]>(
        initMetadata ?? []
    );

    const allMask: number = definitions
        .filter(def => def.type === PermissionType.Mask)
        .map(def => def.value)
        .reduce((m: number, p: number) => p + m, 0);

    const onChangeMask = (e: ChangeEvent<HTMLInputElement>) => {
        const {checked} = e.target;
        const value = parseInt(e.target.value);

        setMask(p => {
            const newMask = p + (checked ? value : -value);
            onMaskChange(userType, userId, newMask, metadata);

            return newMask;
        });
    };

    const onChangeExtraPermission = (e: ChangeEvent<HTMLInputElement>) => {
        const {checked} = e.target;
        const value = parseInt(e.target.value) as AclExtraPermission;

        setMetadata(p => {
            const newMetadata: AclExtraPermission[] = p
                ? checked
                    ? p.concat(value)
                    : p.filter((v: AclExtraPermission) => v !== value)
                : [value];
            onMaskChange(userType, userId, mask, newMetadata);

            return newMetadata;
        });
    };

    const allChecked: boolean | null = isAllChecked(mask, allMask);

    const toggleAll = () => {
        setMask(p => {
            const extraPermissions = definitions.filter(
                d => d.type === PermissionType.Extra
            );
            const allWasChecked = true === isAllChecked(p, allMask);
            const newMask = allWasChecked ? 0 : allMask;
            const newMetadata: AclExtraPermission[] = allWasChecked
                ? []
                : (extraPermissions?.map(ep => ep.value) ?? []);
            onMaskChange(userType, userId, newMask, newMetadata);

            return newMask;
        });
    };

    return (
        <tr>
            <td className={'ug'}>
                {resolving ? (
                    <Skeleton width={100} />
                ) : (
                    (userName ?? `${userType} - ${userId}`)
                )}
            </td>
            {definitions.map(def => {
                const isMask = def.type === PermissionType.Mask;

                return (
                    <td key={`${def.type}${def.key}`} className={'p'}>
                        <Checkbox
                            onChange={
                                isMask ? onChangeMask : onChangeExtraPermission
                            }
                            value={def.value.toString()}
                            checked={
                                isMask
                                    ? (mask & def.value) === def.value
                                    : metadata?.includes(def.value) || false
                            }
                        />
                    </td>
                );
            })}
            {hasAll && (
                <td className={'p'}>
                    <Checkbox
                        onChange={toggleAll}
                        checked={allChecked || false}
                        indeterminate={null === allChecked}
                    />
                </td>
            )}
            <td className={'a'}>
                <Button
                    color={'error'}
                    onClick={(): void => {
                        onDelete(userType, userId);
                    }}
                >
                    {t('acl.delete', 'Delete')}
                </Button>
            </td>
        </tr>
    );
}

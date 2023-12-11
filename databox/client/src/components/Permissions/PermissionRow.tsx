import {ChangeEvent, useState} from 'react';
import {Ace, UserType} from '../../types';
import {Button, Checkbox} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {aclPermissions} from '../Acl/acl';

type Props = {
    onMaskChange: (
        userType: UserType,
        userId: string | null,
        mask: number
    ) => void;
    onDelete: (userType: UserType, userId: string | null) => void;
    userName: string | undefined;
    permissions: string[];
    all?: boolean | undefined;
} & Ace;

function isAllChecked(mask: number, allMask: number): boolean | null {
    return allMask === mask ? true : mask === 0 ? false : null;
}

export default function PermissionRow({
    mask: initMask,
    userName,
    userType,
    userId,
    onMaskChange,
    onDelete,
    permissions,
    all = true,
}: Props) {
    const {t} = useTranslation();
    const [mask, setMask] = useState(initMask);

    const allMask = permissions
        .map(k => aclPermissions[k])
        .reduce((m, p) => p + m, 0);

    const onChangeMask = (e: ChangeEvent<HTMLInputElement>) => {
        const {checked} = e.target;
        const value = parseInt(e.target.value);

        setMask(p => {
            const newMask = p + (checked ? value : -value);
            onMaskChange(userType, userId, newMask);

            return newMask;
        });
    };

    const allChecked: boolean | null = isAllChecked(mask, allMask);

    const toggleAll = () => {
        setMask(p => {
            const newMask = true === isAllChecked(p, allMask) ? 0 : allMask;
            onMaskChange(userType, userId, newMask);

            return newMask;
        });
    };

    return (
        <tr>
            <td className={'ug'}>{userName ?? `${userType} - ${userId}`}</td>
            {permissions.map((k: string) => {
                return (
                    <td key={k} className={'p'}>
                        <Checkbox
                            onChange={onChangeMask}
                            value={aclPermissions[k].toString()}
                            checked={
                                (mask & aclPermissions[k]) === aclPermissions[k]
                            }
                        />
                    </td>
                );
            })}
            {all && (
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

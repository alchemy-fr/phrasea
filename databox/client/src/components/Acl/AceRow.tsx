import React, {ChangeEvent, useState} from 'react';
import {Ace} from "../../types";
import {aclPermissions} from "./AclForm";
import {Button, Checkbox} from "@mui/material";
import {useTranslation} from 'react-i18next';

type Props = {
    onMaskChange: (userType: string, userId: string | null, mask: number) => void;
    onDelete: (userType: string, userId: string | null) => void;
    userName: string | undefined;
} & Ace;

const allMask = Object.keys(aclPermissions).map(k => aclPermissions[k]).reduce((m, p) => p + m, 0);

function isAllChecked(mask: number): boolean | null {
    return allMask === mask ? true : (mask === 0 ? false : null);
}

export default function AceRow({
                                   mask: initMask,
                                   userName,
                                   userType,
                                   userId,
                                   onMaskChange,
                                   onDelete
                               }: Props) {
    const {t} = useTranslation();
    const [mask, setMask] = useState(initMask);

    const onChangeMask = (e: ChangeEvent<HTMLInputElement>) => {
        const {checked} = e.target;
        const value = parseInt(e.target.value);

        setMask(p => {
            const newMask = p + (checked ? value : -value);
            onMaskChange(userType, userId, newMask);

            return newMask;
        });
    }

    const allChecked: boolean | null = isAllChecked(mask);

    const toggleAll = () => {
        setMask(p => {
            const newMask = true === isAllChecked(p) ? 0 : allMask;
            onMaskChange(userType, userId, newMask);

            return newMask;
        });
    }

    return <tr>
        <td
            className={'ug'}>
            {userName ?? (`${userType} - ${userId}`)}
        </td>
        {Object.keys(aclPermissions).map((k: string) => {
            return <td
                key={k}
                className={'p'}
            >
                <Checkbox
                    onChange={onChangeMask}
                    value={aclPermissions[k].toString()}
                    checked={(mask & aclPermissions[k]) === aclPermissions[k]}
                />
            </td>
        })}
        <td
            className={'p'}
        >
            <Checkbox
                onChange={toggleAll}
                checked={allChecked || false}
                indeterminate={null === allChecked}
            />
        </td>
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
}

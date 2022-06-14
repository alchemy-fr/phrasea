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

import React, {useState} from 'react';
import MenuItem from '@mui/material/MenuItem';
import {
    Alert,
    Checkbox,
    FormControl,
    FormControlLabel,
    InputLabel,
    ListItemText,
    Select,
} from '@mui/material';
import {SelectChangeEvent} from '@mui/material/Select/SelectInput';
import {useController} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {useTranslation} from 'react-i18next';

const choices: {[key: string]: {label: string; helper?: string}} = {
    secret: {label: 'Secret'},
    private: {label: 'Private', helper: 'Users can request access'},
    public: {label: 'Public'},
};

function getValue(value: string, workspace: boolean, auth: boolean): number {
    switch (value) {
        default:
        case 'secret':
            return 0;
        case 'private':
            return workspace ? 1 : 3;
        case 'public':
            return workspace ? 2 : auth ? 4 : 5;
    }
}

function getAllowedValue(value: number, inheritedPrivacy?: number): number {
    return Math.max(inheritedPrivacy ?? 0, value);
}

function getKeyValue(value: string): number {
    switch (value) {
        default:
        case 'secret':
            return 0;
        case 'private':
            return 1;
        case 'public':
            return 2;
    }
}

function getFields(value: number): [string, boolean, boolean] {
    switch (value) {
        default:
        case 0:
            return ['secret', false, false];
        case 1:
            return ['private', true, false];
        case 2:
            return ['public', true, false];
        case 3:
            return ['private', false, false];
        case 4:
            return ['public', false, true];
        case 5:
            return ['public', false, false];
    }
}

type Props<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    inheritedPrivacy?: number;
};

export default function PrivacyField<TFieldValues extends FieldValues>({
    control,
    name,
    inheritedPrivacy,
}: Props<TFieldValues>) {
    const {t} = useTranslation();
    const {
        field: {onChange, value},
    } = useController<TFieldValues>({
        control,
        name,
        defaultValue: 0 as any,
    });

    const [p, w, a] = getFields(value);
    const [privacy, setPrivacy] = useState<string>(p);
    const [workspaceOnly, setWorkspaceOnly] = useState(w);
    const [auth, setAuth] = useState(a);

    const ip = inheritedPrivacy ?? 0;
    const inheritedKeyPrivacy = getKeyValue(getFields(ip)[0]);

    React.useEffect(() => {
        const [p, w, a] = getFields(getAllowedValue(value, inheritedPrivacy));
        setPrivacy(p);
        setWorkspaceOnly(w);
        setAuth(a);
    }, [value]);

    const handlePChange = (e: SelectChangeEvent): void => {
        const v = e.target.value;
        setPrivacy(v);
        onChange(
            getAllowedValue(getValue(v, workspaceOnly, auth), inheritedPrivacy)
        );
    };
    const handleWSOnlyChange = (
        e: React.ChangeEvent<HTMLInputElement>
    ): void => {
        setWorkspaceOnly(e.target.checked);
        onChange(
            getAllowedValue(
                getValue(privacy, e.target.checked, auth),
                inheritedPrivacy
            )
        );
    };
    const handleAuthChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
        setAuth(e.target.checked);
        onChange(
            getAllowedValue(
                getValue(privacy, workspaceOnly, e.target.checked),
                inheritedPrivacy
            )
        );
    };

    const workspaceOnlyLocked =
        !!inheritedPrivacy && getValue(privacy, true, auth) < inheritedPrivacy;
    const authLocked =
        !!inheritedPrivacy &&
        getValue(privacy, workspaceOnly, false) < inheritedPrivacy;

    const label = t('form.privacy.label', 'Privacy');

    return (
        <>
            {inheritedPrivacy ? (
                <>
                    <Alert severity={'warning'}>
                        {t(
                            'form.privacy.inherited',
                            'This collection cannot be more restricted than its parent collection.'
                        )}
                    </Alert>
                </>
            ) : null}
            <FormControl>
                <InputLabel>{label}</InputLabel>
                <Select<string>
                    label={label}
                    value={privacy}
                    onChange={handlePChange}
                >
                    {Object.keys(choices).map(k => {
                        return (
                            <MenuItem
                                key={k}
                                value={k}
                                disabled={
                                    inheritedKeyPrivacy > 0 &&
                                    getKeyValue(k) < inheritedKeyPrivacy
                                }
                            >
                                <ListItemText
                                    primary={choices[k].label}
                                    secondary={choices[k].helper}
                                />
                            </MenuItem>
                        );
                    })}
                </Select>

                {['private', 'public'].includes(privacy) && (
                    <FormControlLabel
                        disabled={workspaceOnlyLocked}
                        control={
                            <Checkbox
                                checked={workspaceOnly}
                                onChange={handleWSOnlyChange}
                            />
                        }
                        label={t(
                            'privacy_field.only_visible_to_workspace',
                            `Only visible to workspace`
                        )}
                        labelPlacement="end"
                    />
                )}
                {privacy === 'public' && (
                    <FormControlLabel
                        disabled={authLocked || workspaceOnly}
                        control={
                            <Checkbox
                                checked={auth || workspaceOnly}
                                onChange={handleAuthChange}
                            />
                        }
                        label={t(
                            'privacy_field.user_must_be_authenticated',
                            `User must be authenticated`
                        )}
                        labelPlacement="end"
                    />
                )}
            </FormControl>
        </>
    );
}

import React, {useState} from 'react';
import MenuItem from '@mui/material/MenuItem';
import {
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
};

export default function PrivacyField<TFieldValues extends FieldValues>({
    control,
    name,
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

    React.useEffect(() => {
        const [p, w, a] = getFields(value);
        setPrivacy(p);
        setWorkspaceOnly(w);
        setAuth(a);
    }, [value]);

    const handlePChange = (e: SelectChangeEvent): void => {
        const v = e.target.value;
        setPrivacy(v);
        onChange(getValue(v, workspaceOnly, auth));
    };
    const handleWSOnlyChange = (
        e: React.ChangeEvent<HTMLInputElement>
    ): void => {
        setWorkspaceOnly(e.target.checked);
        onChange(getValue(privacy, e.target.checked, auth));
    };
    const handleAuthChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
        setAuth(e.target.checked);
        onChange(getValue(privacy, workspaceOnly, e.target.checked));
    };

    const label = t('form.privacy.label', 'Privacy');
    return (
        <FormControl>
            <InputLabel>{label}</InputLabel>
            <Select<string>
                label={label}
                value={privacy}
                onChange={handlePChange}
            >
                {Object.keys(choices).map(k => (
                    <MenuItem key={k} value={k}>
                        <ListItemText
                            primary={choices[k].label}
                            secondary={choices[k].helper}
                        />
                    </MenuItem>
                ))}
            </Select>
            {['private', 'public'].includes(privacy) && (
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={workspaceOnly}
                            onChange={handleWSOnlyChange}
                        />
                    }
                    label={`Only visible to workspace`}
                    labelPlacement="end"
                />
            )}
            {privacy === 'public' && !workspaceOnly && (
                <FormControlLabel
                    control={
                        <Checkbox checked={auth} onChange={handleAuthChange} />
                    }
                    label={`User must be authenticated`}
                    labelPlacement="end"
                />
            )}
        </FormControl>
    );
}

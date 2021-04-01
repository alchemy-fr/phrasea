import React, {useState} from "react";
import MenuItem from '@material-ui/core/MenuItem';
import {Checkbox, FormControl, FormControlLabel, InputLabel, Select} from "@material-ui/core";

const choices: {[key: string]: string} = {
    secret: 'Secret',
    private: 'Private',
    public: 'Public',
}

function getValue(value: string, workspace: boolean, auth: boolean): number {
    switch (value) {
        default:
        case 'secret':
            return 0;
        case 'private':
            return workspace ? 1 : 3;
        case 'public':
            return auth ? 5 : (workspace ? 2 : 4);
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
            return ['public', false, false];
        case 5:
            return ['public', false, true];
    }
}

export default function PrivacyField(params: any) {
    const {value} = params.field;

    const [p, w, a] = getFields(value);

    const [privacy, setPrivacy] = useState(p);
    const [workspaceOnly, setWorkspaceOnly] = useState(w);
    const [auth, setAuth] = useState(a);

    const handlePChange = (e: React.ChangeEvent<{ name?: string; value: unknown; }>): void => {
        const v = e.target.value as string;
        setPrivacy(v);
        params.form.setFieldValue(params.field.name, getValue(v, workspaceOnly, auth));
    }
    const handleWSOnlyChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
        setWorkspaceOnly(e.target.checked);
        params.form.setFieldValue(params.field.name, getValue((privacy as string), e.target.checked, auth));
    }
    const handleAuthChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
        setAuth(e.target.checked);
        params.form.setFieldValue(params.field.name, getValue((privacy as string), workspaceOnly, e.target.checked));
    }

    return <FormControl>
        <input
            type="hidden"
            {...params.field}
        />
        <InputLabel id="demo-controlled-open-select-label">Privacy</InputLabel>
        <Select
            value={privacy}
            onChange={handlePChange}
        >
            {Object.keys(choices).map((k) => <MenuItem
                key={k}
                value={k}
            >
                {choices[k]}
            </MenuItem>)}
        </Select>
        {['private', 'public'].includes(privacy) && <FormControlLabel
            control={<Checkbox
                checked={workspaceOnly}
                onChange={handleWSOnlyChange}
            />}
            label={`Only visible to workspace`}
            labelPlacement="end"
        />}
        {privacy === 'public' && !workspaceOnly  && <FormControlLabel
            control={<Checkbox
                checked={auth}
                onChange={handleAuthChange}
            />}
            label={`User must be authenticated`}
            labelPlacement="end"
        />}
    </FormControl>
}

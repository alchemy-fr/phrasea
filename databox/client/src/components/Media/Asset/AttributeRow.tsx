import React, {useState} from "react";
import {Button, TextField} from "@mui/material";
import {putAssetAttribute} from "../../../api/asset";

type Props = {
    id: string;
    assetId: string;
    type: string;
    name: string;
    value: any;
    valueId?: string | undefined;
}

export default function AttributeRow({
                                         id,
                                         assetId,
                                         name,
                                         value: initialValue,
                                         valueId,
                                         type,
                                     }: Props) {
    const [error, setError] = useState<string>();
    const [realValue, setRealValue] = useState<any>(initialValue);
    const [value, setValue] = useState<any>(initialValue);
    const [saving, setSaving] = useState<any>(false);

    let widget;
    switch (type) {
        default:
        case 'text':
            widget = <TextField
                id={id}
                fullWidth
                disabled={saving}
                label={name}
                onChange={(v) => setValue(v.target.value)}
                value={value}
            />
    }

    const save = async () => {
        setSaving(true);
        try {
            await putAssetAttribute(
                valueId,
                assetId,
                id,
                value
            );
            setRealValue(value);
            setSaving(false);
            if (error) {
                setError(undefined);
            }
        } catch (e: any) {
            setSaving(false);
            if (e.response && typeof e.response.data === 'object') {
                const data = e.response.data;
                setError(`${data['hydra:title']}: ${data['hydra:description']}`);
            } else {
                setError(e.toString());
            }
        }
    }

    return <div
        className={'form-group'}
    >
        {widget}
        <Button
            variant="contained"
            disabled={saving || realValue === value}
            onClick={save}
            color="primary">
            Save
        </Button>
        {error && <div>{error}</div>}
    </div>
}

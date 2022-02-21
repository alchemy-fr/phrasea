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
    const [realValue, setRealValue] = useState<any>(initialValue);
    const [value, setValue] = useState<any>(initialValue);
    const [saving, setSaving] = useState<any>(false);

    let widget;
    switch (type) {
        default:
        case 'text':
            widget = <TextField
                id={id}
                disabled={saving}
                label={name}
                onChange={(v) => setValue(v.target.value)}
                value={value}
            />
    }

    const save = async () => {
        setSaving(true);
        console.log('save value', value);
        await putAssetAttribute(
            valueId,
            assetId,
            id,
            value
        );
        setRealValue(value);
        setSaving(false);
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
    </div>
}

import React, {useCallback, useState} from "react";
import {Button} from "@mui/material";
import {deleteAssetAttribute, putAssetAttribute} from "../../../../api/asset";
import AttributeWidget from "./AttributeWidget";

type Props = {
    id: string;
    assetId: string;
    type: string;
    name: string;
    value: any;
    valueId?: string | undefined;
    locales: string[];
}

export default function AttributeRow({
                                         id,
                                         assetId,
                                         name,
                                         value: initialValue,
                                         valueId: initialValueId,
                                         type,
                                         locales,
                                     }: Props) {
    const [error, setError] = useState<string>();
    const [realValue, setRealValue] = useState<any>(initialValue);
    const [valueId, setValueId] = useState<any>(initialValueId);
    const [value, setValue] = useState<any>(initialValue);
    const [saving, setSaving] = useState<any>(false);

    const onChange = useCallback((value: any) => {
        setValue(value);
    }, [setValue]);

    const save = async () => {
        setSaving(true);
        try {
            if (valueId && !value) {
                await deleteAssetAttribute(valueId);
                setValueId(undefined);
            } else {
                const res = await putAssetAttribute(
                    valueId,
                    assetId,
                    id,
                    value
                );
                console.log('res', res);
                setValueId(res.id);
            }
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
        <AttributeWidget
            value={value}
            disabled={saving}
            type={type}
            name={name}
            onChange={onChange}
            id={id}
        />
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

import React from 'react';
import {AttributeType} from "../../api/attributes";
import {getAttributeType} from "../Media/Asset/Attribute/types";

type Props = {
    date: string;
};

export default function Date({
                                 date
                             }: Props) {
    return <>
        {getAttributeType(AttributeType.DateTime).formatValue({
            value: date,
        })}
    </>
}

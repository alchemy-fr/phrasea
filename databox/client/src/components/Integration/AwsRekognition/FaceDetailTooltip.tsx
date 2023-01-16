import React, {ReactNode} from 'react';
import {FaceDetail, TValueConfidence} from "./types";
import {Chip} from "@mui/material";
import ValueConfidence from "./ValueConfidence";

type Props = {
    detail: FaceDetail;
    title: string;
};

function BooleanAssertion({
                              title,
                              value,
                          }: {
    title: string;
    value: TValueConfidence<boolean> | undefined;
}) {
    if (!value) {
        return null;
    }

    return <Assertion
        title={title}
        value={<>
            {value.Value ? <Chip
                size={'small'}
                color={'success'}
                label={'Yes'}
            /> : <Chip
                size={'small'}
                color={'error'}
                label={'No'}
            />} (<ValueConfidence confidence={value.Confidence}/>)
        </>}
    />
}

function Assertion({
                       title,
                       value,
                   }: {
    title: string;
    value: ReactNode;
}) {
    return <div>
        <b>{title}</b>: {value}
    </div>
}

export default function FaceDetailTooltip({
                                              detail,
                                              title,
                                          }: Props) {

    return <>
        <div>
            <b>{title}</b> <small>(<ValueConfidence confidence={detail.Confidence}/>)</small>
        </div>
        {detail.AgeRange &&
            <Assertion title={'Age range'} value={`${detail.AgeRange.Low} - ${detail.AgeRange.High} years old`}/>}
        {detail.Gender && <Assertion title={'Gender'} value={<>
            {detail.Gender.Value}{' - '}
            <ValueConfidence confidence={detail.Gender.Confidence}/>
        </>}/>}
        <BooleanAssertion title={'Has a beard'} value={detail.Beard}/>
        <BooleanAssertion title={'Smiling'} value={detail.Smile}/>
        <BooleanAssertion title={'Eyes Open'} value={detail.EyesOpen}/>
        <BooleanAssertion title={'Eyeglasses'} value={detail.Eyeglasses}/>
        <BooleanAssertion title={'Mouth Open'} value={detail.MouthOpen}/>
        <BooleanAssertion title={'Mustache'} value={detail.Mustache}/>
    </>
}

import React from 'react';

type Props = {
    confidence: number;
};

export default function ValueConfidence({confidence}: Props) {
    return <>
        {Math.round(confidence * 100) / 100}%
    </>
}

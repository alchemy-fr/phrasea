import {RectangleAnnotation} from "../../../../types.ts";

type Props = {
    borderSize?: number;
} & RectangleAnnotation;

export default function RectAnnotation({
    x1,
    y1,
    x2,
    y2,
    borderSize = 3,
    c = '#000',
    f,
}: Props) {
    return (
        <div
            data-type={'rect'}
            style={{
                position: 'absolute',
                top: `${y1 * 100}%`,
                left: `${x1 * 100}%`,
                height: `${(y2 - y1) * 100}%`,
                width: `${(x2 - x1) * 100}%`,
                backgroundColor: f,
                border: `${borderSize}px solid ${c}`,
            }}
        />
    );
}

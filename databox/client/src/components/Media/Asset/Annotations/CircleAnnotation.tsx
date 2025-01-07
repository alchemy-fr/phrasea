import {CircleAnnotation as TCircleAnnotation} from "../../../../types.ts";

type Props = {
    borderSize?: number;
} & TCircleAnnotation;

export default function CircleAnnotation({
    x,
    y,
    r,
    borderSize = 3,
    c = '#000',
    f,
}: Props) {
    return (
        <div
            data-type={'circle'}
            style={{
                position: 'absolute',
                top: `${y * 100}%`,
                left: `${x * 100}%`,
                width: `${r * 100}%`,
                aspectRatio: `1 / 1`,
                borderRadius: '50%',
                transform: `translateX(-50%) translateY(-50%)`,
                backgroundColor: f,
                border: `${borderSize}px solid ${c}`,
            }}
        />
    );
}

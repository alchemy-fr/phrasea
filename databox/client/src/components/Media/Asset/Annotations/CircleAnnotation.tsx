import {CircleAnnotation as TCircleAnnotation} from "./annotationTypes.ts";

type Props = {
} & TCircleAnnotation;

export default function CircleAnnotation({
    x,
    y,
    r,
    s = 3,
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
                width: `${r * 2 * 100}%`,
                aspectRatio: `1 / 1`,
                borderRadius: '50%',
                transform: `translateX(-50%) translateY(-50%)`,
                backgroundColor: f,
                border: `${s!}px solid ${c}`,
            }}
        />
    );
}

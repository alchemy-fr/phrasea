import {PointAnnotation as TPointAnnotation} from "../../../../types.ts";

type Props = {
    size?: number;
} & TPointAnnotation;

export default function PointAnnotation({x, y, size = 30, c = '#000'}: Props) {
    return (
        <div
            data-type={'point'}
            style={{
                position: 'absolute',
                top: `${y * 100}%`,
                left: `${x * 100}%`,
                width: size,
                height: size,
                borderRadius: '50%',
                transform: `translateX(-50%) translateY(-50%)`,
                backgroundColor: c,
            }}
        />
    );
}

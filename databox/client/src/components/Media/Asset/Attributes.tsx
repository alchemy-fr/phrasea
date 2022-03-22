import {Asset, Attribute} from "../../../types";
import reactStringReplace from 'react-string-replace';

export function replaceHighlight(value: string) {
    return reactStringReplace(value, /\[hl](.*?)\[\/hl]/g, (m) => {
        return <em className="hl">{m}</em>;
    });
}

function AttributeRow({
                          definition,
                          value,
                          highlight,
                      }: Attribute) {
    const finalValue = highlight || value;

    return <div>
        <div>
            <b>{definition.name}</b>
            {' '}
            {finalValue && Array.isArray(finalValue)
                ? <ul>{finalValue.map((v, i) => <li key={i}>
                    {replaceHighlight(v)}
                </li>)}</ul> : replaceHighlight(finalValue)}
        </div>
    </div>
}

type Props = {
    asset: Asset;
}

export default function Attributes({
                                       asset,
                                   }: Props) {
    return <div className={'attributes'}>
        <div className={'attr-title'}>{asset.titleHighlight ? <span dangerouslySetInnerHTML={{
            __html: asset.titleHighlight,
        }}/> : asset.title}</div>
        {asset.attributes.map(a => <AttributeRow
            {...a}
            key={a.id}
        />)}
    </div>
}

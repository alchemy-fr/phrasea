import {Asset, Attribute} from "../../../types";
import {ReactNode} from "react";


function AttributeRow({
                          definition,
                          value,
                          highlight,
                      }: Attribute) {
    return <div>
        <div>
            <b>{definition.name}</b>
            {' '}
            {highlight && <span dangerouslySetInnerHTML={{
                __html: highlight,
            }} />}
            {!highlight && value}
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
        }} /> : asset.title}</div>
        {asset.attributes.map(a => <AttributeRow
            {...a}
            key={a.id}
        />)}
    </div>
}

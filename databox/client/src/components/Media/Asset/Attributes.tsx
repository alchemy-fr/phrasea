import {Asset, Attribute} from "../../../types";


function AttributeRow({
                       definition,
                       value,
                   }: Attribute) {
    return <div>
        <div>
            <b>{definition.name}</b>
            {' '}
            {value}
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
        {asset.attributes.map(a => <AttributeRow
            {...a}
            key={a.id}
        />)}
    </div>
}

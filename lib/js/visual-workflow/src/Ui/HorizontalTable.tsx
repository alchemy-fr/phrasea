import {ReactNode} from 'react';
import '../style/table.scss';

export type Cells = [ReactNode, ReactNode][];

type Props = {
    values: Cells;
};

export default function HorizontalTable({values}: Props) {
    return (
        <table className={'workflow-htable'}>
            <tbody>
                <tr>
                    {values.map(([t], i) => (
                        <th key={i}>{t}</th>
                    ))}
                </tr>
                <tr>
                    {values.map(([, v], i) => (
                        <td key={i}>{v}</td>
                    ))}
                </tr>
            </tbody>
        </table>
    );
}

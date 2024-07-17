import React from 'react';
import {SuggestionTabProps} from '../types.ts';
import ValuesSuggestions from './ValuesSuggestions.tsx';
import Tabs from '../../Ui/Tabs.tsx';
import {TabItem} from '../../Dialog/Tabbed/tabTypes.ts';
import {useTranslation} from 'react-i18next';
import Preview from './Preview.tsx';
import {Optional} from '../../../utils/types.ts';

type Props<T> = {} & Optional<
    SuggestionTabProps<T>,
    'definition' | 'valueContainer'
>;

export default function SuggestionPanel<T = string>(props: Props<T>) {
    const {t} = useTranslation();
    const [tab, setTab] = React.useState('preview');

    const tabs = React.useMemo<TabItem<SuggestionTabProps<T>>[]>(() => {
        return [
            {
                id: 'preview',
                component: Preview,
                title: t('attribute.editor.tabs.preview.title', 'Preview'),
                enabled: props.subSelection.length > 0,
            },
            {
                id: 'values',
                component: ValuesSuggestions,
                title: t('attribute.editor.tabs.values.title', 'Values'),
                enabled: !!props.definition && !!props.valueContainer,
            },
        ];
    }, [!props.definition, !!props.valueContainer]);

    return (
        <>
            <Tabs<SuggestionTabProps<T>>
                tabs={tabs}
                currentTabId={tab}
                onTabChange={id => setTab(id)}
                {...(props as SuggestionTabProps<T>)}
            />
        </>
    );
}

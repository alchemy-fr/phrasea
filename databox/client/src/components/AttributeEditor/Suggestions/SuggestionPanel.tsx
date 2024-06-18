import React from "react";
import {SuggestionTabProps} from "../types.ts";
import ValuesSuggestions from "./ValuesSuggestions.tsx";
import Tabs from "../../Ui/Tabs.tsx";
import {TabItem} from "../../Dialog/Tabbed/tabTypes.ts";
import {useTranslation} from 'react-i18next';

type Props<T> = {} & SuggestionTabProps<T>;


export default function SuggestionPanel<T = string>({
    ...props
}: Props<T>) {
    const {t} = useTranslation();
    const [tab, setTab] = React.useState('values');

    const tabs = React.useMemo<TabItem<SuggestionTabProps<T>>[]>(() => {
        return [
            {
                id: 'values',
                component: ValuesSuggestions,
                title: t('attribute.editor.tabs.values.title', 'Values'),
            },
        ]
    }, []);

    return <>
        <Tabs<SuggestionTabProps<T>>
            tabs={tabs}
            currentTabId={tab}
            onTabChange={(id) => setTab(id)}
            {...props}
        />
    </>
}

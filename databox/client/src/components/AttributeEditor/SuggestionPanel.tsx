import React from "react";
import {SuggestionTabProps} from "./types.ts";
import ValuesSuggestions from "./Suggestions/ValuesSuggestions.tsx";
import Tabs from "../Ui/Tabs.tsx";
import {TabItem} from "../Dialog/Tabbed/tabTypes.ts";
import {useTranslation} from 'react-i18next';

type Props = {} & SuggestionTabProps;


export default function SuggestionPanel({
    ...props
}: Props) {
    const {t} = useTranslation();
    const [tab, setTab] = React.useState('values');

    const tabs = React.useMemo<TabItem<SuggestionTabProps>[]>(() => {
        return [
            {
                id: 'values',
                component: ValuesSuggestions,
                title: t('attribute.editor.tabs.values.title', 'Values'),
            },
        ]
    }, []);

    return <>
        <Tabs<SuggestionTabProps>
            tabs={tabs}
            currentTabId={tab}
            onTabChange={(id) => setTab(id)}
            {...props}
        />
    </>
}

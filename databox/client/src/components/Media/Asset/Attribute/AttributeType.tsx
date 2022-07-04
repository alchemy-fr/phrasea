import React from "react";
import AttributeWidget from "./AttributeWidget";
import {AttributeDefinition} from "../../../../types";
import {AttrValue, LocalizedAttributeIndex, NO_LOCALE, OnChangeHandler} from "./AttributesEditor";
import MultiAttributeRow from "./MultiAttributeRow";
import {isRtlLocale} from "../../../../lib/lang";
import FormRow from "../../../Form/FormRow";
import {Box, FormLabel, Tab, Tabs} from "@mui/material";
import {TabPanelProps} from "@mui/lab";
import Flag from "../../../Ui/Flag";

function TabPanel({children, value, currentValue}: {
    currentValue: string | undefined;
} & TabPanelProps) {
    return (
        <div
            role="tabpanel"
            hidden={value !== currentValue}
            id={`locale-tabpanel-${value}`}
            aria-labelledby={`simple-tab-${value}`}
        >
            {value === currentValue && children}
        </div>
    );
}

type Props = {
    definition: AttributeDefinition;
    attributes: LocalizedAttributeIndex<string | number>;
    disabled: boolean;
    onChange: OnChangeHandler;
    indeterminate?: boolean;
    readOnly?: boolean;
    currentLocale?: string | undefined;
    onLocaleChange: (locale: string) => void;
}

export default function AttributeType({
                                          definition,
                                          readOnly,
                                          attributes,
                                          disabled,
                                          onChange,
                                          indeterminate,
                                          currentLocale,
                                          onLocaleChange,
                                      }: Props) {

    const changeHandler = (locale: string, v: AttrValue<string | number> | AttrValue<string | number>[] | undefined) => {
        const na = {...attributes};

        if (v && !(v instanceof Array)) {
            v = (v as AttrValue<string | number>).value ? v : undefined as any;
        }

        na[locale] = v;

        onChange(definition.id, na);
    };

    if (definition.translatable) {
        return <>
            <FormRow>
                <FormLabel>
                    {definition.name}
                </FormLabel>
                <Box sx={{borderBottom: 1, borderColor: 'divider'}}>
                    <Tabs
                        value={currentLocale}
                        onChange={(e, value) => onLocaleChange(value)}
                        aria-label="Locales"
                    >
                        {definition.locales!.map(l => <Tab
                            key={l}
                            label={<>
                                <Flag
                                    locale={l}
                                    sx={{mb: 1}}
                                />
                                {l}
                            </>}
                            value={l}
                        />)}
                    </Tabs>
                </Box>

                {definition.locales!.map((locale) => {
                    const label = `${definition.name} ${locale}`;

                    return <TabPanel
                        currentValue={currentLocale}
                        value={locale}
                    >
                        {definition.multiple ? <MultiAttributeRow
                            indeterminate={indeterminate}
                            readOnly={readOnly}
                            disabled={disabled}
                            name={label}
                            type={definition.fieldType}
                            isRtl={isRtlLocale(locale)}
                            values={(attributes[locale] || []) as AttrValue<string | number>[]}
                            onChange={(values) => changeHandler(locale, values)}
                            id={definition.id}
                        /> : <AttributeWidget
                            indeterminate={indeterminate}
                            readOnly={readOnly}
                            value={attributes[locale] as AttrValue<string | number> | undefined}
                            disabled={disabled}
                            type={definition.fieldType}
                            isRtl={isRtlLocale(locale)}
                            name={label}
                            required={false}
                            onChange={(v) => changeHandler(locale, v)}
                            id={definition.id}
                        />}
                    </TabPanel>
                })}
            </FormRow>
        </>
    }

    return <FormRow>
        {definition.multiple ? <MultiAttributeRow
            indeterminate={indeterminate}
            readOnly={readOnly}
            isRtl={false}
            disabled={disabled}
            type={definition.fieldType}
            name={definition.name}
            values={(attributes[NO_LOCALE] || []) as AttrValue<string | number>[]}
            onChange={(values) => changeHandler(NO_LOCALE, values)}
            id={definition.id}
        /> : <AttributeWidget
            indeterminate={indeterminate}
            readOnly={readOnly}
            isRtl={false}
            value={attributes[NO_LOCALE] as AttrValue<string | number> | undefined}
            required={false}
            disabled={disabled}
            name={definition.name}
            type={definition.fieldType}
            onChange={(v) => changeHandler(NO_LOCALE, v)}
            id={definition.id}
        />}
    </FormRow>
}

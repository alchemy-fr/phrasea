import {Alert, Box, Tab, Tabs} from '@mui/material';
import Flag from '../../../Ui/Flag';
import MultiAttributeRow from './MultiAttributeRow';
import {isRtlLocale} from '../../../../lib/lang';
import {
    AttrValue,
    LocalizedAttributeIndex,
    NO_LOCALE,
} from './AttributesEditor';
import AttributeWidget from './AttributeWidget';
import {AttributeDefinition} from '../../../../types';
import {TabPanelProps} from '@mui/lab';
import React from 'react';

function TabPanel({
    children,
    value,
    currentValue,
}: {
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
    attributes: LocalizedAttributeIndex<string | number>;
    currentLocale: string;
    onLocaleChange: (locale: string) => void;
    definition: AttributeDefinition;
    indeterminate?: boolean;
    disabled: boolean;
    changeHandler: (
        locale: string,
        v: AttrValue<string | number> | AttrValue<string | number>[] | undefined
    ) => void;
    readOnly?: boolean;
};

export default function TranslatableAttributeTabs({
    currentLocale,
    onLocaleChange,
    definition,
    indeterminate,
    disabled,
    changeHandler,
    attributes,
    readOnly,
}: Props) {
    const locales = React.useMemo<string[]>(() => {
        const l = [...definition.locales!];
        // eslint-disable-next-line no-prototype-builtins
        const hasUndetermined = attributes.hasOwnProperty(NO_LOCALE);

        if (hasUndetermined) {
            l.push(NO_LOCALE);
        }

        return l;
    }, [definition.locales, attributes]);

    if (locales.length === 0) {
        return (
            <Alert severity={'warning'}>
                No locale defined in this workspace
            </Alert>
        );
    }

    const humanLocale = (l: string) => (l === NO_LOCALE ? `Untranslated` : l);

    return (
        <>
            <Box
                sx={{
                    borderBottom: 1,
                    borderColor: 'divider',
                    mb: 2,
                }}
            >
                <Tabs
                    value={currentLocale}
                    onChange={(_e, value) => onLocaleChange(value)}
                    aria-label="Locales"
                    sx={{
                        '.MuiTab-root': {
                            textTransform: 'none',
                        },
                    }}
                >
                    {locales.map(l => (
                        <Tab
                            key={l}
                            label={
                                <>
                                    <Flag locale={l} sx={{mb: 1}} />
                                    {humanLocale(l)}
                                </>
                            }
                            value={l}
                        />
                    ))}
                </Tabs>
            </Box>

            {locales.map(locale => {
                const label = `${definition.name} ${humanLocale(locale)}`;

                return (
                    <TabPanel
                        currentValue={currentLocale}
                        value={locale}
                        key={locale}
                    >
                        {definition.multiple ? (
                            <MultiAttributeRow
                                indeterminate={indeterminate}
                                readOnly={readOnly}
                                disabled={disabled}
                                name={label}
                                type={definition.fieldType}
                                isRtl={isRtlLocale(locale)}
                                values={
                                    (attributes[locale] || []) as AttrValue<
                                        string | number
                                    >[]
                                }
                                onChange={values =>
                                    changeHandler(locale, values)
                                }
                                id={definition.id}
                            />
                        ) : (
                            <AttributeWidget
                                indeterminate={indeterminate}
                                readOnly={readOnly}
                                value={
                                    attributes[locale] as
                                        | AttrValue<string | number>
                                        | undefined
                                }
                                disabled={disabled}
                                type={definition.fieldType}
                                isRtl={isRtlLocale(locale)}
                                name={label}
                                required={false}
                                onChange={v => changeHandler(locale, v)}
                                id={definition.id}
                            />
                        )}
                    </TabPanel>
                );
            })}
        </>
    );
}

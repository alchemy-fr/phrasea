import {Alert, Box, Tab, Tabs} from '@mui/material';
import Flag from '../../../Ui/Flag';
import MultiAttributeRow from './MultiAttributeRow';
import {isRtlLocale} from '../../../../lib/lang';
import {AttrValue} from './AttributesEditor';
import AttributeWidget from './AttributeWidget';
import {TabPanelProps} from '@mui/lab';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {NO_LOCALE} from './constants.ts';
import {TranslatableAttributeTabsProps} from './attributeTypes.ts';

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

export default function TranslatableAttributeTabs({
    currentLocale,
    onLocaleChange,
    definition,
    changeHandler,
    attributes,
    options,
    ...attributeProps
}: TranslatableAttributeTabsProps) {
    const {t} = useTranslation();
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
                {t(
                    'workspace.no_locale_defined',
                    `No locale defined in this workspace`
                )}
            </Alert>
        );
    }

    const humanLocale = (l: string) =>
        l === NO_LOCALE
            ? t('translatable_attribute_tabs.untranslated', `Untranslated`)
            : l;

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
                            display: 'flex',
                            flexDirection: 'row',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 1,
                        },
                        '.MuiIcon-root': {
                            display: 'block',
                            fontSize: '1.2rem',
                            mb: 0,
                            height: 19,
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
                const label = `${definition.nameTranslated ?? definition.name} ${humanLocale(locale)}`;

                return (
                    <TabPanel
                        currentValue={currentLocale}
                        value={locale}
                        key={locale}
                    >
                        {definition.multiple ? (
                            <MultiAttributeRow
                                label={label}
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
                                options={options}
                                {...attributeProps}
                            />
                        ) : (
                            <AttributeWidget
                                value={
                                    attributes[locale] as
                                        | AttrValue<string | number>
                                        | undefined
                                }
                                type={definition.fieldType}
                                isRtl={isRtlLocale(locale)}
                                label={label}
                                required={false}
                                onChange={v => changeHandler(locale, v)}
                                id={definition.id}
                                options={options}
                                {...attributeProps}
                            />
                        )}
                    </TabPanel>
                );
            })}
        </>
    );
}

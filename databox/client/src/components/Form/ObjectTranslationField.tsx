import React, {ReactNode} from 'react';
import {Alert, Box, InputLabel, Tab, Tabs} from '@mui/material';
import Flag from '../Ui/Flag.tsx';
import {NO_LOCALE} from '../Media/Asset/Attribute/AttributesEditor.tsx';
import {useTranslation} from 'react-i18next';
import {TabPanelProps} from '@mui/lab';

type Props = {
    translatable?: boolean;
    locales: string[];
    field: (props: {locale: string | undefined}) => ReactNode;
    label?: ReactNode;
    displayNoLocale?: boolean;
};

export default function ObjectTranslationField({
    translatable = true,
    locales: propLocales,
    field,
    label,
    displayNoLocale = false,
}: Props) {
    const {t} = useTranslation();
    const locales = displayNoLocale ? [...propLocales, NO_LOCALE] : propLocales;

    const [currentLocale, setCurrentLocale] = React.useState(
        (translatable ? locales[0] : null) || NO_LOCALE
    );
    const humanLocale = (l: string) =>
        l === NO_LOCALE
            ? t('translatable_attribute_tabs.untranslated', `Untranslated`)
            : l;

    if (!translatable) {
        return (
            <div>
                <InputLabel>{label}</InputLabel>
                {field({
                    locale: undefined,
                })}
            </div>
        );
    }

    if (translatable && locales.length === 0) {
        return (
            <Alert severity={'warning'}>
                {t(
                    'workspace.no_locale_defined',
                    `No locale defined in this workspace`
                )}
            </Alert>
        );
    }

    return (
        <div>
            <InputLabel>{label}</InputLabel>
            <Box
                sx={{
                    borderBottom: 1,
                    borderColor: 'divider',
                    mb: 2,
                }}
            >
                <Tabs
                    value={currentLocale}
                    onChange={(_e, value) => setCurrentLocale(value)}
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
            <div>
                {locales.map(locale => (
                    <TabPanel
                        currentValue={currentLocale}
                        value={locale}
                        key={locale}
                    >
                        {field({
                            locale,
                        })}
                    </TabPanel>
                ))}
            </div>
        </div>
    );
}

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

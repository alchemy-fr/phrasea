import React from 'react';
import {Asset, AttributeDefinition, StateSetter} from '../../types.ts';
import {Alert, Box, Tab, Tabs} from '@mui/material';
import {SetAttributeValue, ToKeyFunc, Values} from './types.ts';
import AttributeWidget from './AttributeWidget.tsx';
import Flag from '../Ui/Flag.tsx';
import {NO_LOCALE} from '../Media/Asset/Attribute/AttributesEditor.tsx';
import MultiAttributeRow from './MultiAttributeRow.tsx';
import {useDebounce} from '@alchemy/react-hooks/src/useDebounce.ts';

type Props<T> = {
    definition: AttributeDefinition;
    valueContainer: Values;
    subSelection: Asset[];
    setAttributeValue: SetAttributeValue<T>;
    inputValueInc: number;
    locale: string;
    setLocale: StateSetter<string>;
    toKey: ToKeyFunc<T>;
};

export default function EditorPanel<T>({
    definition,
    valueContainer,
    setAttributeValue,
    subSelection,
    inputValueInc,
    locale,
    setLocale,
    toKey,
}: Props<T>) {
    const disabled = false; // TODO
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [proxyValue, setValue] = React.useState<T | T[] | undefined>();
    const [currentDefinition, setCurrentDefinition] =
        React.useState(definition);
    const debounce = useDebounce();

    const value = currentDefinition === definition ? proxyValue : undefined;

    React.useEffect(() => {
        inputRef.current?.focus();
    }, [definition, valueContainer, inputValueInc, locale]);

    React.useEffect(() => {
        setValue(
            valueContainer.indeterminate[locale]
                ? null
                : valueContainer.values[0]?.[locale] ?? ''
        );
        setCurrentDefinition(definition);
    }, [definition, subSelection, inputValueInc, locale]);

    const changeHandler = React.useCallback(
        (v: T | undefined) => {
            setValue(v);
            debounce(() => {
                setAttributeValue(v);
            }, 500);
        },
        [setAttributeValue]
    );

    const locales = React.useMemo<string[] | undefined>(() => {
        if (!definition.translatable) {
            return;
        }

        const locales = [...(definition.locales ?? [])];

        if (
            valueContainer.values.some(v =>
                Object.hasOwnProperty.call(v, NO_LOCALE)
            )
        ) {
            locales.push(NO_LOCALE);
        }

        return locales;
    }, [valueContainer, definition]);

    if (definition.translatable && locales!.length === 0) {
        return (
            <Alert severity={'warning'}>
                No locale defined in this workspace
            </Alert>
        );
    }

    const humanLocale = (l: string) => (l === NO_LOCALE ? `Untranslated` : l);

    const readOnly = !definition.canEdit;

    return (
        <Box
            sx={{
                p: 3,
            }}
        >
            {locales ? (
                <>
                    <Tabs
                        value={locale}
                        onChange={(_e, l) => setLocale(l)}
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
                </>
            ) : (
                ''
            )}

            {definition.multiple ? (
                <MultiAttributeRow
                    id={definition.id}
                    name={definition.name}
                    type={definition.fieldType}
                    setAttributeValue={setAttributeValue}
                    readOnly={readOnly}
                    disabled={disabled}
                    valueContainer={valueContainer}
                    locale={locale}
                    toKey={toKey}
                />
            ) : (
                <AttributeWidget<T>
                    inputRef={inputRef}
                    key={definition.id}
                    id={definition.id}
                    name={definition.name}
                    type={definition.fieldType}
                    indeterminate={valueContainer.indeterminate.g}
                    readOnly={readOnly}
                    isRtl={false}
                    value={value as T | undefined}
                    disabled={disabled}
                    required={false}
                    autoFocus={true}
                    onChange={changeHandler}
                />
            )}
        </Box>
    );
}

import {ReactNode, useEffect, useState} from 'react';
import {PublicationConfig} from '../../types.ts';
import {FieldValues} from 'react-hook-form';
import {UseFormSubmitReturn} from '@alchemy/api';
import {Box, FormControlLabel, Switch} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {FormConst} from '../publication/types.ts';

type Data = {
    config: PublicationConfig;
} & FieldValues;

type RenderWidgetProps<TFieldValues extends Data> = {
    usedFormSubmit: UseFormSubmitReturn<TFieldValues>;
    path: string;
    disabled?: boolean;
};

type Props<TFieldValues extends Data> = {
    profileId?: string;
    configPath: string;
    usedFormSubmit: UseFormSubmitReturn<TFieldValues>;
    renderWidget: (props: RenderWidgetProps<TFieldValues>) => ReactNode;
    disabledValue?: any;
};

export default function ProfileOverrideWrapper<TFieldValues extends Data>({
    profileId,
    configPath,
    usedFormSubmit,
    renderWidget,
    disabledValue,
}: Props<TFieldValues>) {
    const path = `config.${configPath}`;
    const {watch, setValue} = usedFormSubmit;

    const watched = watch(path as any);

    const {t} = useTranslation();
    const [overridden, setOverridden] = useState(
        Boolean(profileId && !!watched)
    );

    useEffect(() => {
        if (profileId) {
            if (!overridden) {
                setValue(path as any, disabledValue ?? (null as any));
            }
        }
    }, [overridden, profileId, setValue, disabledValue]);

    const renderProps = {
        usedFormSubmit,
        path,
    };

    if (!profileId) {
        return renderWidget(renderProps);
    }

    return (
        <Box>
            <FormControlLabel
                control={
                    <Switch
                        checked={overridden}
                        onChange={(_e, checked) => setOverridden(checked)}
                    />
                }
                label={t(
                    'form.publication.config.overridden.label',
                    'Override settings'
                )}
            />
            <div key={overridden ? 'overridden' : 'fallback'}>
                {overridden
                    ? renderWidget(renderProps)
                    : renderWidget({
                          ...renderProps,
                          path: `${FormConst.FallbackProfileProps}.config.${configPath}`,
                          disabled: true,
                      })}
            </div>
        </Box>
    );
}

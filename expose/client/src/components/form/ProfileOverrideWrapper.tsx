import {PropsWithChildren, useEffect, useState} from 'react';
import {PublicationConfig, PublicationProfile} from '../../types.ts';
import {FieldValues} from 'react-hook-form';
import {UseFormSubmitReturn} from '@alchemy/api';
import {Box, FormControlLabel, Switch} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Data = {
    config: PublicationConfig;
} & FieldValues;

type Props<TFieldValues extends Data> = PropsWithChildren<{
    publicationProfile?: PublicationProfile;
    path: string;
    inheritedValue: any;
    usedFormSubmit: UseFormSubmitReturn<TFieldValues>;
}>;

export default function ProfileOverrideWrapper<TFieldValues extends Data>({
    children,
    publicationProfile,
    inheritedValue,
    path,
    usedFormSubmit,
}: Props<TFieldValues>) {
    const {watch, setValue} = usedFormSubmit;

    const watched = watch(path as any);

    const {t} = useTranslation();
    const [overridden, setOverridden] = useState(
        publicationProfile && !!watched
    );

    useEffect(() => {
        if (publicationProfile) {
            if (!overridden) {
                setValue(path as any, null as any);
            } else {
                setValue(path as any, inheritedValue);
            }
        }
    }, [overridden, publicationProfile, setValue, inheritedValue]);

    if (!publicationProfile) {
        return children;
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
            {overridden && <div>{children}</div>}
        </Box>
    );
}

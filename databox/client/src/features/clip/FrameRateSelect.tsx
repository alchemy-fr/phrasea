'use client';

import {useId} from 'react';
import {useTranslation} from 'react-i18next';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {cn} from '@alchemy/next-ui/utils/cn';
import {DEFAULT_FRAME_RATE, STANDARD_FRAME_RATES} from './frameRate';
import type {FrameRateState} from './useFrameRate';

const AUTO = 'auto';

/** The frame rate measured on the video, or one chosen instead */
export function FrameRateSelect({
    state,
    className,
}: {
    state: FrameRateState;
    className?: string;
}) {
    const {t} = useTranslation();
    const id = useId();
    const fps = state.measured ?? DEFAULT_FRAME_RATE;

    return (
        <div
            className={cn('flex items-center gap-2 text-sm', className)}
            data-testid="clip-frame-rate"
        >
            <label htmlFor={id} className="text-muted-foreground">
                {t('clip.frame_rate', 'Frame rate')}
            </label>
            <SimpleSelect
                id={id}
                className="w-36"
                size="sm"
                value={
                    state.override !== undefined ? String(state.override) : AUTO
                }
                onValueChange={value =>
                    state.setOverride(
                        value === AUTO ? undefined : Number(value)
                    )
                }
                options={[
                    {
                        value: AUTO,
                        label: state.measuring
                            ? t('clip.frame_rate_measuring', 'Auto (…)')
                            : t('clip.frame_rate_auto', 'Auto ({{fps}} fps)', {
                                  fps,
                              }),
                    },
                    ...STANDARD_FRAME_RATES.map(rate => ({
                        value: String(rate),
                        label: `${rate} fps`,
                    })),
                ]}
            />
        </div>
    );
}

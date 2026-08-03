import {Alert, Stack} from '@mui/material';
import {TFunction} from '@alchemy/i18n';
import {useTranslation} from 'react-i18next';
import {
    AnalysisMessage,
    getMessageLevel,
    getMessagePayload,
    getMessageType,
} from './analysisTypes.ts';
import {levelToSeverity} from './severity.ts';

/**
 * Turns an analyzer message (`type` + `payload`) into a human-readable,
 * translated string. Each analyzer provides its own resolver so the copy can
 * be tailored to the specific failure.
 */
export type MessageResolver = (
    t: TFunction,
    type: string,
    payload: Record<string, any>
) => React.ReactNode;

type Props = {
    messages: AnalysisMessage[] | undefined;
    resolve: MessageResolver;
};

export default function AnalysisMessages({messages, resolve}: Props) {
    const {t} = useTranslation();

    if (!messages || messages.length === 0) {
        return null;
    }

    return (
        <Stack spacing={1}>
            {messages.map((message, index) => {
                const type = getMessageType(message);

                return (
                    <Alert
                        key={`${type}-${index}`}
                        severity={levelToSeverity(getMessageLevel(message))}
                        variant={'outlined'}
                    >
                        {resolve(t, type, getMessagePayload(message))}
                    </Alert>
                );
            })}
        </Stack>
    );
}

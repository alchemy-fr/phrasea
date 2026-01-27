import {
    Button,
    ButtonGroup,
    ListItemIcon,
    ListItemText,
    MenuItem,
} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {DropdownActions} from '@alchemy/phrasea-ui';
import React from 'react';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import CheckIcon from '@mui/icons-material/Check';
import VisibilityIcon from '@mui/icons-material/Visibility';
import RadioButtonUncheckedIcon from '@mui/icons-material/RadioButtonUnchecked';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import {TopicSubscriptions} from '../../types.ts';
import {apiClient} from '../../init.ts';

type Topic = {
    key: string;
    label: string;
    description?: string | undefined;
    subscribed?: boolean;
};

type Subscriptions = Record<Topic['key'], boolean>;

type Props = {
    entity: string;
    id: string;
    topics: Topic[];
    subscriptions: TopicSubscriptions<Topic['key']>;
};

export default function FollowButton({
    topics,
    entity,
    id,
    subscriptions: initialSubscriptions,
}: Props) {
    const {t} = useTranslation();

    const subFromTopics = React.useMemo((): Subscriptions => {
        const s: Subscriptions = {};

        topics.forEach((t: Topic) => {
            s[t.key] = Boolean(initialSubscriptions[t.key]);
        });

        return s;
    }, [topics]);

    const [subscriptions, setSubscriptions] =
        React.useState<Subscriptions>(subFromTopics);

    React.useEffect(() => {
        setSubscriptions(subFromTopics);
    }, [subFromTopics]);

    if (topics.length === 0) {
        return null;
    }

    const subscribed = Object.entries(subscriptions).some(([_k, s]) => s);
    const mainButtonLabel = subscribed
        ? t('notification.action.unfollow.label', 'Unfollow')
        : t('notification.action.follow.label', 'Follow');

    const createToggleFollow = (key?: string) => {
        return () => {
            setSubscriptions(p => {
                const subscribed = Object.entries(p).some(([_k, s]) => s);

                if (!key) {
                    apiClient.post(
                        `/${entity}/${id}/${subscribed ? 'unfollow' : 'follow'}`,
                        {}
                    );

                    return Object.fromEntries(
                        Object.entries(p).map(([k, _s]) => [k, !subscribed])
                    );
                }

                apiClient.post(
                    `/${entity}/${id}/${p[key] ? 'unfollow' : 'follow'}`,
                    {
                        key,
                    }
                );

                return Object.fromEntries(
                    Object.entries(p).map(([k, s]) => [k, k === key ? !s : s])
                );
            });
        };
    };
    const mainButtonColor = subscribed ? 'inherit' : 'primary';
    const mainButtonIcon = subscribed ? <CheckIcon /> : <VisibilityIcon />;

    if (topics.length === 1) {
        return (
            <Button
                color={mainButtonColor}
                variant="contained"
                onClick={createToggleFollow()}
                startIcon={mainButtonIcon}
            >
                {mainButtonLabel}
            </Button>
        );
    }

    return (
        <DropdownActions
            mainButton={({onClick, ...props}) => {
                return (
                    <ButtonGroup variant="contained" color={mainButtonColor}>
                        <Button
                            {...props}
                            variant="contained"
                            onClick={createToggleFollow()}
                            startIcon={mainButtonIcon}
                        >
                            {mainButtonLabel}
                        </Button>
                        <Button
                            size="small"
                            aria-haspopup="menu"
                            onClick={onClick}
                        >
                            <ArrowDropDownIcon />
                        </Button>
                    </ButtonGroup>
                );
            }}
        >
            {() =>
                topics.map(t => (
                    <MenuItem key={t.key} onClick={createToggleFollow(t.key)}>
                        <ListItemIcon>
                            {subscriptions[t.key] ? (
                                <CheckCircleIcon />
                            ) : (
                                <RadioButtonUncheckedIcon />
                            )}
                        </ListItemIcon>
                        <ListItemText
                            primary={t.label}
                            secondary={t.description}
                        />
                    </MenuItem>
                ))
            }
        </DropdownActions>
    );
}

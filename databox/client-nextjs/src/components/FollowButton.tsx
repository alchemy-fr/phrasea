'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {BellIcon, BellOffIcon, CheckIcon, ChevronDownIcon} from 'lucide-react';
import {toast} from 'sonner';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {follow, unfollow} from '@/lib/api/assets';

export type Topic = {key: string; label: string; description?: string};

/**
 * Follow / unfollow an entity, with per-topic subscriptions.
 */
export function FollowButton({
    entity,
    id,
    topics,
    subscriptions: initial,
    size = 'sm',
}: {
    entity: string;
    id: string;
    topics: Topic[];
    subscriptions: string[];
    size?: 'sm' | 'default';
}) {
    const {t} = useTranslation();
    const [subs, setSubs] = useState<Set<string>>(new Set(initial));
    useEffect(() => setSubs(new Set(initial)), [initial]);
    const any = subs.size > 0;

    const toggleAll = async () => {
        try {
            if (any) {
                await unfollow(entity, id);
                setSubs(new Set());
            } else {
                await follow(entity, id);
                setSubs(new Set(topics.map(tp => tp.key)));
            }
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    const toggleTopic = async (key: string) => {
        try {
            if (subs.has(key)) {
                await unfollow(entity, id, [key]);
                setSubs(prev => {
                    const n = new Set(prev);
                    n.delete(key);

                    return n;
                });
            } else {
                await follow(entity, id, [key]);
                setSubs(prev => new Set([...prev, key]));
            }
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    return (
        <div className="inline-flex items-center">
            <Button
                variant={any ? 'secondary' : 'outline'}
                size={size}
                className="rounded-r-none"
                onClick={toggleAll}
            >
                {any ? <BellOffIcon /> : <BellIcon />}
                <span className="hidden md:inline">
                    {any
                        ? t('follow.unfollow', 'Unfollow')
                        : t('follow.follow', 'Follow')}
                </span>
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant={any ? 'secondary' : 'outline'}
                        size={size}
                        className="rounded-l-none border-l-0 px-1.5"
                        aria-label={t('follow.topics', 'Topics')}
                    >
                        <ChevronDownIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    {topics.map(tp => (
                        <DropdownMenuCheckboxItem
                            key={tp.key}
                            checked={subs.has(tp.key)}
                            onCheckedChange={() => toggleTopic(tp.key)}
                        >
                            {tp.label}
                        </DropdownMenuCheckboxItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

export {CheckIcon};

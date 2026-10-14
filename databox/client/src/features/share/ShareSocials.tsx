'use client';

import {MailIcon} from 'lucide-react';
import {Tooltip} from '@/components/ui/overlays';

type Network = {
    name: string;
    build: (url: string, title: string) => string;
    color: string;
    glyph: string;
};

const networks: Network[] = [
    {
        name: 'Facebook',
        color: '#1877F2',
        glyph: 'f',
        build: u => `https://www.facebook.com/sharer/sharer.php?u=${u}`,
    },
    {
        name: 'X',
        color: '#000000',
        glyph: '𝕏',
        build: (u, t) => `https://twitter.com/intent/tweet?url=${u}&text=${t}`,
    },
    {
        name: 'LinkedIn',
        color: '#0A66C2',
        glyph: 'in',
        build: u => `https://www.linkedin.com/sharing/share-offsite/?url=${u}`,
    },
    {
        name: 'Pinterest',
        color: '#E60023',
        glyph: 'P',
        build: (u, t) =>
            `https://pinterest.com/pin/create/button/?url=${u}&description=${t}`,
    },
    {
        name: 'Telegram',
        color: '#26A5E4',
        glyph: '➤',
        build: (u, t) => `https://t.me/share/url?url=${u}&text=${t}`,
    },
    {
        name: 'WhatsApp',
        color: '#25D366',
        glyph: 'W',
        build: (u, t) => `https://wa.me/?text=${t}%20${u}`,
    },
    {
        name: 'Tumblr',
        color: '#36465D',
        glyph: 't',
        build: (u, t) =>
            `https://www.tumblr.com/widgets/share/tool?canonicalUrl=${u}&title=${t}`,
    },
    {
        name: 'Workplace',
        color: '#4326C4',
        glyph: 'Wp',
        build: u => `https://work.facebook.com/sharer.php?u=${u}`,
    },
    {
        name: 'Pocket',
        color: '#EF3F56',
        glyph: 'Pk',
        build: (u, t) => `https://getpocket.com/save?url=${u}&title=${t}`,
    },
    {
        name: 'Instapaper',
        color: '#1F1F1F',
        glyph: 'I',
        build: (u, t) =>
            `https://www.instapaper.com/hello2?url=${u}&title=${t}`,
    },
];

export function ShareSocials({url, title}: {url: string; title: string}) {
    const u = encodeURIComponent(url);
    const t = encodeURIComponent(title);

    return (
        <div className="flex flex-wrap gap-1.5">
            <Tooltip content="Email">
                <a
                    href={`mailto:?subject=${t}&body=${u}`}
                    className="flex size-8 items-center justify-center rounded-full bg-muted text-foreground hover:opacity-80"
                >
                    <MailIcon className="size-4" />
                </a>
            </Tooltip>
            {networks.map(n => (
                <Tooltip key={n.name} content={n.name}>
                    <a
                        href={n.build(u, t)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex size-8 items-center justify-center rounded-full text-xs font-bold text-white hover:opacity-80"
                        style={{backgroundColor: n.color}}
                    >
                        {n.glyph}
                    </a>
                </Tooltip>
            ))}
        </div>
    );
}

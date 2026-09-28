'use client';

import {AssetStackLoader} from '@/components/ui/asset-loaders';

export default function DevLoadersPage() {
    return (
        <div className="relative h-screen">
            <div className="grid grid-cols-4 gap-3 p-6">
                {Array.from({length: 24}, (_, i) => (
                    <div
                        key={i}
                        className="flex h-28 items-center justify-center rounded-md bg-primary/70 text-lg font-semibold text-primary-foreground"
                    >
                        Asset {i + 1}
                    </div>
                ))}
            </div>
            <div className="absolute inset-0 z-10 flex items-center justify-center animate-asset-overlay motion-reduce:animate-none motion-reduce:bg-background/40 motion-reduce:backdrop-blur-sm">
                <AssetStackLoader
                    className="animate-in fade-in duration-200"
                    label="Loading assets…"
                />
            </div>
        </div>
    );
}

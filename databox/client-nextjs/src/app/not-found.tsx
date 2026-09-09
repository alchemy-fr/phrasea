import Link from 'next/link';

export default function NotFound() {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
            <div className="text-6xl font-bold text-muted-foreground/40">
                404
            </div>
            <h1 className="text-xl font-semibold">Page not found</h1>
            <Link
                href="/assets"
                className="text-primary underline-offset-4 hover:underline"
            >
                Back to assets
            </Link>
        </div>
    );
}

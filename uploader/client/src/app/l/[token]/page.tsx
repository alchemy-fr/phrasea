import type {Metadata} from 'next';
import {UploadLinkScreen} from '@/features/upload-link/UploadLinkScreen';

export const metadata: Metadata = {
    // The URL is a secret: not to be indexed, nor told to the sites it links to
    robots: {index: false, follow: false},
    referrer: 'no-referrer',
};

/** Outside of the signed-in area: an upload link needs no account */
export default async function UploadLinkPage({
    params,
}: {
    params: Promise<{token: string}>;
}) {
    const {token} = await params;

    return <UploadLinkScreen key={token} token={token} />;
}

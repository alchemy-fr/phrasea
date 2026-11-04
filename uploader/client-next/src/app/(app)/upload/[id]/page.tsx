import {UploadScreen} from '@/features/upload/UploadScreen';

export default async function UploadPage({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    // A new target is a new wizard
    return <UploadScreen key={id} targetId={id} />;
}

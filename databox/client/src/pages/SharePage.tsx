import {useParams} from '@alchemy/navigation';
import {Asset, Share} from "../types.ts";
import {useQuery} from "@tanstack/react-query";
import {getPublicShare} from "../api/asset.ts";
import {FullPageLoader} from "@alchemy/phrasea-ui";
import AssetShare from "../components/Share/AssetShare.tsx";

type Props = {};

export default function SharePage({}: Props) {
    const {id, token} = useParams() as { id: string; token: string };

    const {data, isSuccess} = useQuery<Share>({
        queryKey: ['share', id, token],
        queryFn: () => getPublicShare(id, token),
    });

    if (!isSuccess) {
        return <FullPageLoader/>
    }

    return (
        <div>
            {data.asset && (
                <AssetShare asset={data.asset as Asset}/>
            )}
        </div>
    );
}

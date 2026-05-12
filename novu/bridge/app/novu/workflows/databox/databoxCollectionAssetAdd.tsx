import {workflow} from "@novu/framework";
import {z} from "zod";

export const databoxCollectionAssetAdd = workflow(
    'databox-collection-asset-add',
    async ({step, payload}) => {
        await step.inApp('In-App Step', async () => {
            return {
                // @ts-expect-error unknown issue
                subject: `New asset in **${payload.collectionName}**`,
                // @ts-expect-error unknown issue
                body: `**${payload.author}** added Asset **${payload.assetName}** to collection **${payload.collectionName}**.`,
                redirect: {
                    // @ts-expect-error unknown issue
                    url: payload.url,
                },
            };
        });
    },
    {
        payloadSchema: z.object({
            url: z.string().default('/null').describe('The resource URL'),
            assetName: z.string().describe('The Asset Name'),
            collectionName: z.string().describe('The Collection Name'),
            author: z.string().describe('The author of the message'),
        }),
    }
);

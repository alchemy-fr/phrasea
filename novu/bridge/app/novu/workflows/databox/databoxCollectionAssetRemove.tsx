import {workflow} from "@novu/framework";
import {z} from "zod";

export const databoxCollectionAssetRemove = workflow(
    "databox-collection-asset-remove",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                // @ts-expect-error unknown issue
                subject: `Asset removed from **${payload.collectionName}**`,
                // @ts-expect-error unknown issue
                body: `**${payload.author}** removed Asset **${payload.assetName}** from Collection **${payload.collectionName}**.`,
                redirect: {
                // @ts-expect-error unknown issue
                    url: payload.url,
                },
            };
        });
    },
    {
        payloadSchema: z.object({
            url: z
                .string()
                .default('/null')
                .describe("The resource URL"),
            assetName: z
                .string()
                .describe("The Asset Name"),
            collectionName: z
                .string()
                .describe("The Collection Name"),
            author: z
                .string()
                .describe("The author of the message"),
        })
    },
);

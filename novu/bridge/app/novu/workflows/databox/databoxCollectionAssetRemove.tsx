import {workflow} from "@novu/framework";
import {z} from "zod";

export const databoxCollectionAssetRemove = workflow(
    "databox-collection-asset-remove",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                subject: `Asset removed from **${payload.collectionTitle}**`,
                body: `**${payload.author}** removed Asset **${payload.assetTitle}** from Collection **${payload.collectionTitle}**.`,
                redirect: {
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
            assetTitle: z
                .string()
                .describe("The Asset title"),
            collectionTitle: z
                .string()
                .describe("The Collection title"),
            author: z
                .string()
                .describe("The author of the message"),
        })
    },
);

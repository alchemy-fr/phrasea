import {workflow} from "@novu/framework";
import {z} from "zod";

export const databoxCollectionAssetAdd = workflow(
    "databox-collection-asset-add",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                subject: `New asset in **${payload.collectionTitle}**`,
                body: `**${payload.author}** added Asset **${payload.assetTitle}** to collection **${payload.collectionTitle}**.`,
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

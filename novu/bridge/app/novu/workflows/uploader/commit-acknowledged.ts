import {workflow} from "@novu/framework";
import {z} from "zod";

export const uploaderCommitAcknowledged = workflow(
    "uploader-commit-acknowledged",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            const n = parseInt(payload.assetCount);
            const plural = n > 1 ? 's' : '';

            return {
                subject: `You have **${payload.assetCount}** new asset${plural}!`,
                body: `The **${payload.assetCount}** asset${plural} you uploaded were correctly handled.`,
            };
        });
    },
    {
        payloadSchema: z.object({
            assetCount: z
                .string()
                .describe("The number of assets in the commit"),
        })
    },
);

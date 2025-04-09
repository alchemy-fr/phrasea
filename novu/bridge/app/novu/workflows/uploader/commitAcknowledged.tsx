import {workflow} from "@novu/framework";
import {z} from "zod";
import {render, Section, Text} from "@react-email/components";
import DefaultEmail, {styles} from "@/app/novu/emails/DefaultEmail";
import React from "react";

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

        await step.email("send-email", async () => {
            const n = parseInt(payload.assetCount);
            const plural = n > 1 ? 's' : '';

            return {
                subject: `You have new asset !`,
                body: render(<DefaultEmail>
                    <Section>
                        <Text style={styles.text}>
                            The <strong>{payload.assetCount}</strong> asset{plural} you uploaded were correctly handled.
                        </Text>                        
                    </Section>
                </DefaultEmail>
                ),   
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

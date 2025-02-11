import {workflow} from "@novu/framework";
import {z} from "zod";
import {Button, render, Section, Text} from "@react-email/components";
import DefaultEmail, {styles} from "@/app/novu/emails/DefaultEmail";
import React from "react";

export const databoxDiscussionNewComment = workflow(
    "databox-discussion-new-comment",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            return {
                subject: `New comment on **${payload.object}**`,
                body: `**${payload.author}** has commented on **${payload.object}**.`,
                redirect: {
                    url: payload.url,
                },
            };
        });

        const { events } = await step.digest("digest-1-hours", async () => {
            return {
                // unit: "hours",
                // amount: 1,
                unit: "seconds",
                amount: 10,
            };
        });

        await step.email("send-email", async () => {
            const eventCount = events.length;

            const groups = events.reduce((acc, event) => {
                const key = event.payload.objectId;
                acc[key] ??= [];
                acc[key].push(event);

                return acc;
            }, {});

            return {
                subject: `${eventCount} new comments`,
                body: render(<DefaultEmail>
                    {Object.entries(groups).map(([objectId, events]) => {
                        const { object, author, url } = events[0].payload;

                        const authors = events.reduce((acc, event) => {
                            acc[event.payload.authorId] = event.payload.author;

                            return acc;
                        }, {});

                        return (
                            <Section>
                                <Text>
                                    <strong>{author}</strong> have commented on <strong>{object}</strong>.
                                </Text>

                                <Button style={styles.button} href={url}>View</Button>
                            </Section>
                        );
                    })}
                    </DefaultEmail>
                ),
            };
        });
    },
    {
        payloadSchema: z.object({
            url: z
                .string()
                .default('/null')
                .describe("The resource URL"),
            objectId: z
                .string()
                .describe("The object ID"),
            object: z
                .string()
                .describe("The object title"),
            authorId: z
                .string()
                .describe("The author ID of the message"),
            author: z
                .string()
                .describe("The author of the message"),
        })
    },
);

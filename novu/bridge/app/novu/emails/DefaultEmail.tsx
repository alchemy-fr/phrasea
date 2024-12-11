import {Body, Container, Head, Html, Preview} from "@react-email/components";
import React, {CSSProperties, PropsWithChildren} from "react";
import {z, ZodRawShape} from "zod";

type Props = PropsWithChildren<{}>;

export default function DefaultEmail({
    children,
}: Props) {
    return (
        <Html>
            <Head/>
            <Preview>Dropbox reset your password</Preview>
            <Body style={main}>
                <Container style={container}>
                    {children}
                </Container>
            </Body>
        </Html>
    );
}

const main: CSSProperties = {
    backgroundColor: "#abc5ff",
    padding: "8px 0",
};

const container: CSSProperties = {
    backgroundColor: "#ffffff",
    border: "1px solid #f0f0f0",
    padding: "45px",
};

export const text: CSSProperties = {
    fontSize: "16px",
    fontWeight: "300",
    color: "#404040",
    lineHeight: "26px",
};
const headingText: CSSProperties = {
    fontWeight: "300",
    lineHeight: "30px",
}
const button: CSSProperties = {
    backgroundColor: "#007ee6",
    borderRadius: "4px",
    color: "#fff",
    fontSize: "15px",
    textDecoration: "none",
    textAlign: "center" as const,
    display: "block",
    width: "210px",
    padding: "14px 7px",
};

const anchor: CSSProperties = {
    textDecoration: "underline",
};

export const styles: Record<string, CSSProperties> = {
    text,
    headingText: headingText,
    button,
    anchor,
}

export type CreateEmailControlSchemaProps = {
    defaultEmailSubject: string;
    shape?: ZodRawShape;
};

export function createEmailControlSchema({
    defaultEmailSubject,
    shape,
}: CreateEmailControlSchemaProps) {
    return z.object({
        emailSubject: z.string().default(defaultEmailSubject),
        ...(shape ?? {}),
    });
}

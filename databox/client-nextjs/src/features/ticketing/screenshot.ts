'use client';

import {screenshotMaxBytes} from './types';

const maxWidth = 1920;

export function isScreenCaptureSupported(): boolean {
    return (
        typeof navigator !== 'undefined' &&
        typeof navigator.mediaDevices?.getDisplayMedia === 'function'
    );
}

/** Decoded size of a `data:` URL payload */
export function dataUrlBytes(dataUrl: string): number {
    const base64 = dataUrl.slice(dataUrl.indexOf(',') + 1);

    return Math.floor((base64.length * 3) / 4);
}

function encode(canvas: HTMLCanvasElement): string {
    const png = canvas.toDataURL('image/png');
    if (dataUrlBytes(png) <= screenshotMaxBytes) {
        return png;
    }
    for (const quality of [0.85, 0.6]) {
        const jpeg = canvas.toDataURL('image/jpeg', quality);
        if (dataUrlBytes(jpeg) <= screenshotMaxBytes) {
            return jpeg;
        }
    }

    throw new Error('Screenshot is too large');
}

/**
 * Captures the current page through the Screen Capture API: the browser asks
 * the user which surface to share (the current tab is pre-selected where
 * supported), a single frame is grabbed and the stream is closed immediately.
 */
export async function captureScreenshot(): Promise<string> {
    const stream = await navigator.mediaDevices.getDisplayMedia({
        video: {displaySurface: 'browser'},
        audio: false,
        // Non standard hints (Chromium): pre-select the current tab.
        preferCurrentTab: true,
        selfBrowserSurface: 'include',
        surfaceSwitching: 'exclude',
    } as DisplayMediaStreamOptions);

    try {
        const video = document.createElement('video');
        video.srcObject = stream;
        video.muted = true;
        video.playsInline = true;
        await video.play();
        // Let the compositor deliver an actual frame before drawing.
        await new Promise<void>(resolve =>
            requestAnimationFrame(() => requestAnimationFrame(() => resolve()))
        );

        const scale = Math.min(1, maxWidth / (video.videoWidth || maxWidth));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        const ctx = canvas.getContext('2d');
        if (!ctx || !canvas.width || !canvas.height) {
            throw new Error('Unable to capture the screen');
        }
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        video.pause();
        video.srcObject = null;

        return encode(canvas);
    } finally {
        stream.getTracks().forEach(track => track.stop());
    }
}

/** Reads a pasted / dropped image file as a data URL */
export async function imageFileToDataUrl(file: File): Promise<string> {
    if (!file.type.startsWith('image/')) {
        throw new Error('Not an image');
    }
    if (file.size > screenshotMaxBytes) {
        throw new Error('Image is too large');
    }

    return new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onerror = () => reject(reader.error ?? new Error('Read error'));
        reader.onload = () => resolve(reader.result as string);
        reader.readAsDataURL(file);
    });
}

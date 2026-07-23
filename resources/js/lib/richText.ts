import DOMPurify from 'dompurify';

export function isRichTextEmpty(value: string | null | undefined): boolean {
    if (!value) {
        return true;
    }

    const text = value
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return text === '';
}

export function sanitizeRichText(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return DOMPurify.sanitize(value, {
        USE_PROFILES: { html: true },
    });
}

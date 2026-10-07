import DOMPurify from 'dompurify';

/**
 * Rich-text descriptions are stored as HTML. Admin pages show them as plain
 * paragraphs instead of rendering the markup, so nothing is injected there.
 */
export function htmlToParagraphs(html: string | null | undefined): string[] {
    if (!html?.trim()) {
        return [];
    }

    const spaced = html.replace(/<\/(p|h[1-6]|li|div)>|<br\s*\/?>/gi, '$&\n');
    const doc = new DOMParser().parseFromString(spaced, 'text/html');

    return (doc.body.textContent ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

// Tags the product/store editor (Tiptap StarterKit) can produce.
const RICH_TEXT_TAGS = [
    'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
    'h2', 'h3', 'h4', 'blockquote', 'code', 'pre', 'hr', 'a',
];

/**
 * Safe HTML for showing a rich-text description on public pages. Older
 * descriptions are plain text, so those are escaped and split into paragraphs.
 */
export function richTextToHtml(value: string | null | undefined): string {
    if (!value?.trim()) {
        return '';
    }

    if (!/<[a-z][^>]*>/i.test(value)) {
        const escape = (line: string) =>
            line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        return value
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter(Boolean)
            .map((line) => `<p>${escape(line)}</p>`)
            .join('');
    }

    const html = DOMPurify.sanitize(value, {
        ALLOWED_TAGS: RICH_TEXT_TAGS,
        ALLOWED_ATTR: ['href'],
    });

    // Empty paragraphs (Tiptap leaves a trailing <p></p>) only add gaps.
    return html.replace(/<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>/gi, '');
}

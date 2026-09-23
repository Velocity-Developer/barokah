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

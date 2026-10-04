/*
 * واترمارک: the reader's name and a fragment of their account id as a tiled,
 * diagonal SVG background, for a layer laid faintly over protected text (the
 * question card, a lesson), so a screenshot names the account it came from.
 */
export function watermarkImage(mark) {
    const text = String(mark).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"><text x="160" y="90" text-anchor="middle" transform="rotate(-24 160 90)" font-family="sans-serif" font-size="15" fill="#888">${text}</text></svg>`;
    return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`;
}

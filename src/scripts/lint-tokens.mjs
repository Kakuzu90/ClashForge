// Fails when a class string uses an arbitrary colour value instead of a design token (specs/18 §9).
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ARBITRARY_COLOUR = /\b[\w:-]*-\[(?:#[0-9a-fA-F]{3,8}|(?:rgb|rgba|hsl|hsla|oklch|oklab|lab|lch|color)\([^\]]*\))\]/g;

export function findViolations(source) {
    const hits = [];
    source.split('\n').forEach((line, index) => {
        for (const match of line.matchAll(ARBITRARY_COLOUR)) {
            hits.push({ line: index + 1, value: match[0] });
        }
    });
    return hits;
}

function* walk(dir) {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) {
            if (!['types', 'routes', 'actions', 'wayfinder'].includes(entry)) yield* walk(path);
        } else if (/\.(vue|ts)$/.test(entry)) {
            yield path;
        }
    }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    const root = join(fileURLToPath(new URL('.', import.meta.url)), '..');
    let failed = 0;
    for (const file of walk(join(root, 'resources/js'))) {
        for (const hit of findViolations(readFileSync(file, 'utf8'))) {
            console.error(`${relative(root, file)}:${hit.line}  arbitrary colour ${hit.value}; use a design token`);
            failed++;
        }
    }
    process.exit(failed ? 1 : 0);
}

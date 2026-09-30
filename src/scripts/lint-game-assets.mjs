// Fails when frontend code names a game-asset path or host. Game assets reach the UI only as
// GameAssetResolver output in props, rendered by <GameAsset> (specs/18 §2.3, §9; specs/19 §2).
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const RULES = [
    { pattern: /api-assets\.clashofclans\.com/i, reason: 'Clash of Clans asset host' },
    { pattern: /(?:^|[^\w-])game\/[\w.-]+\/(?:units|townhalls|leagues|manifest\.json)/, reason: 'game/ pack path' },
    { pattern: /\/(?:units|townhalls|leagues)\/[\w-]+\.(?:png|webp)/, reason: 'game asset file path' },
];

export function findViolations(source) {
    const hits = [];
    source.split('\n').forEach((line, index) => {
        for (const rule of RULES) {
            const match = line.match(rule.pattern);
            if (match) {
                hits.push({ line: index + 1, value: match[0].trim(), reason: rule.reason });
            }
        }
    });
    return hits;
}

function* walk(dir) {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) {
            if (!['types', 'routes', 'actions', 'wayfinder'].includes(entry)) yield* walk(path);
        } else if (/\.(vue|ts|php)$/.test(entry)) {
            yield path;
        }
    }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    const root = join(fileURLToPath(new URL('.', import.meta.url)), '..');
    let failed = 0;
    for (const dir of ['resources/js', 'resources/views']) {
        for (const file of walk(join(root, dir))) {
            for (const hit of findViolations(readFileSync(file, 'utf8'))) {
                console.error(
                    `${relative(root, file)}:${hit.line}  ${hit.reason} "${hit.value}"; resolve it with GameAssetResolver and render <GameAsset>`,
                );
                failed++;
            }
        }
    }
    process.exit(failed ? 1 : 0);
}

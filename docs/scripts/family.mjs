import { existsSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

export const scriptsDir = dirname(fileURLToPath(import.meta.url))
export const docsDir = join(scriptsDir, '..')
export const coreDir = join(docsDir, '..')

export const SITE_BASE = '/yii3-ab-testing/'

// The one list of family packages: the aggregator, the API generator, the
// integrity check, reflect-api.php and the sidebar all read it.
export const PACKAGES = JSON.parse(readFileSync(join(scriptsDir, 'packages.json'), 'utf8'))

export function repoUrl(pkg) {
    return `https://github.com/rasuvaeff/${pkg.dir}`
}

// CI checks the siblings out under docs/siblings/<dir>; locally they are the
// monorepo directories next to this package. Same order as reflect-api.php.
export function packageDir(pkg) {
    if (pkg.slug === 'core') {
        return coreDir
    }

    const candidates = [join(docsDir, 'siblings', pkg.dir), join(dirname(coreDir), pkg.dir)]

    return candidates.find((candidate) => existsSync(join(candidate, 'src'))) ?? null
}

export function shortName(className) {
    const parts = className.split('\\')

    return parts[parts.length - 1]
}

// Every generated page goes through here: the README, llms.txt and docblock
// prose come from other repositories, and VitePress compiles markdown as a Vue
// template — a `{{` or a bare `<T>` outside a code span would break the build
// or, worse, render as something else. The v-pre container switches that off.
export function vPre(markdown) {
    return `::: v-pre\n${markdown.trim()}\n:::\n`
}

// Docblock prose is written for PHP readers: `list<Foo>` or `a < b` outside a
// code span is ordinary text there, and an unclosed HTML tag to VitePress —
// whose markdown is compiled as a Vue template, v-pre or not. Escapes angle
// brackets everywhere except fenced blocks and inline code spans.
export function escapeProse(markdown) {
    let inFence = false

    return markdown
        .split('\n')
        .map((line) => {
            if (/^\s*(```|~~~)/.test(line)) {
                inFence = !inFence

                return line
            }
            if (inFence) return line

            return line
                .split(/(`[^`]*`)/)
                .map((part, i) => (i % 2 === 1 ? part : part.replaceAll('<', '&lt;').replaceAll('>', '&gt;')))
                .join('')
        })
        .join('\n')
}

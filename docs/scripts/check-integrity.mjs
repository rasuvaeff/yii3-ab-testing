import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { extname, join } from 'node:path'

import { PACKAGES, docsDir, packageDir, scriptsDir, shortName } from './family.mjs'

// Runs after aggregate.mjs and generate-api.mjs, before `vitepress build`.
// Everything here reads the source tree; check-anchors.mjs covers what only
// exists after rendering.

const errors = []
const fail = (message) => errors.push(message)
const warnings = []

// 1. Every family package carries both READMEs and an llms.txt, none of them
//    empty: the site renders README.md and llms.txt, and README.ru.md is the
//    other half of the bilingual-README rule the site does not show.
for (const pkg of PACKAGES) {
    const dir = packageDir(pkg)
    if (dir === null) {
        fail(`${pkg.dir} is not checked out — the site would silently document fewer packages.`)
        continue
    }
    for (const file of ['README.md', 'README.ru.md', 'llms.txt']) {
        const path = join(dir, file)
        if (!existsSync(path)) {
            fail(`${pkg.dir}/${file} is missing.`)
        } else if (readFileSync(path, 'utf8').trim().length < 200) {
            fail(`${pkg.dir}/${file} is shorter than 200 characters — empty or truncated.`)
        }
    }

    // 2. Every package has its pages on the site.
    for (const page of [`packages/${pkg.slug}.md`, `llms/${pkg.slug}.md`]) {
        if (!existsSync(join(docsDir, page))) {
            fail(`${pkg.name} has no generated page ${page}.`)
        }
    }
}

// 3. The API reference covers every @api type of every package, every public
//    member appears on its page, and nothing that is not @api leaks in.
const snapshot = JSON.parse(readFileSync(join(scriptsDir, 'api-snapshot.json'), 'utf8'))
const apiEntries = snapshot.filter((entry) => entry.isApi)

for (const pkg of PACKAGES) {
    if (!apiEntries.some((entry) => entry.package === pkg.slug)) {
        fail(`The reflection snapshot has no @api type for ${pkg.name} — was the package reflected at all?`)
    }
}

const seen = new Map()
for (const entry of apiEntries) {
    const name = shortName(entry.class)
    if (seen.has(name)) {
        fail(`Two @api types share the short name "${name}" (${seen.get(name)}, ${entry.class}) — their API pages would overwrite each other.`)
    }
    seen.set(name, entry.class)

    const pagePath = join(docsDir, 'api', 'classes', `${name}.md`)
    if (!existsSync(pagePath)) {
        fail(`Missing API page api/classes/${name}.md for ${entry.class}.`)
        continue
    }
    const content = readFileSync(pagePath, 'utf8')
    const members = [
        ...entry.publicMethods.map((m) => m.name),
        ...entry.publicProperties.map((p) => p.name),
        ...entry.constants.map((c) => c.name),
        ...entry.cases.map((c) => c.name),
    ]
    for (const member of members) {
        if (!content.includes(member)) {
            fail(`api/classes/${name}.md does not mention "${member}" from the reflection snapshot.`)
        }
    }
}

const classesDir = join(docsDir, 'api', 'classes')
if (existsSync(classesDir)) {
    for (const file of readdirSync(classesDir)) {
        if (!seen.has(file.replace(/\.md$/, ''))) {
            fail(`api/classes/${file} has no @api entry in the reflection snapshot — an internal or removed type leaked into the reference.`)
        }
    }
}

// 4. Internal links — in the sidebar/nav config and in every page — resolve
//    to a file. Raw <a href="/..."> is rejected: VitePress does not apply the
//    site base to it.
const SKIP_DIRS = new Set(['siblings', 'node_modules', '.vitepress', 'public', 'scripts', '.vale'])

function collectMarkdownFiles(dir) {
    const results = []
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        if (SKIP_DIRS.has(entry.name)) continue
        const path = join(dir, entry.name)
        if (entry.isDirectory()) {
            results.push(...collectMarkdownFiles(path))
        } else if (extname(entry.name) === '.md' && !entry.name.endsWith('.ru.md')) {
            results.push(path)
        }
    }

    return results
}

function resolves(link) {
    const clean = link.split('#')[0].split('?')[0].replace(/^\//, '').replace(/\/$/, '/index')
    if (clean === '') return true
    const candidates = [join(docsDir, clean + '.md'), join(docsDir, clean, 'index.md')]

    return candidates.some((path) => existsSync(path) && statSync(path).isFile())
}

const configSource = readFileSync(join(docsDir, '.vitepress', 'config.ts'), 'utf8')
for (const match of configSource.matchAll(/link:\s*'([^']+)'/g)) {
    if (!/^https?:\/\//.test(match[1]) && !resolves(match[1])) {
        fail(`.vitepress/config.ts links to "${match[1]}", which is not a page.`)
    }
}

const rawAnchor = /<a\s[^>]*href="\/(?!\/)[^"]*"/i
for (const file of collectMarkdownFiles(docsDir)) {
    const rel = file.replace(docsDir + '/', '')
    const content = readFileSync(file, 'utf8')
    if (rawAnchor.test(content)) {
        fail(`${rel} uses a raw <a href="/..."> internal link; use a markdown link so the site base applies.`)
    }
    for (const match of content.matchAll(/\]\((\/[^)\s]*)\)/g)) {
        if (!resolves(match[1])) {
            fail(`${rel} links to "${match[1]}", which is not a page.`)
        }
    }
}

// 5. A package's llms.txt names every @api type of that package — the cheap
//    proxy for "the compact reference grew with the public API". A warning,
//    not a failure: the fix lives in the sibling repository, and a gate here
//    would keep this site red over a change no commit in this repository can
//    make. The hard version belongs in each package's own build.yml (#25).
for (const pkg of PACKAGES) {
    const dir = packageDir(pkg)
    if (dir === null || !existsSync(join(dir, 'llms.txt'))) continue
    const llms = readFileSync(join(dir, 'llms.txt'), 'utf8')
    for (const entry of apiEntries.filter((e) => e.package === pkg.slug)) {
        if (!llms.includes(shortName(entry.class))) {
            warnings.push(`${pkg.dir}/llms.txt does not mention @api type ${shortName(entry.class)}.`)
        }
    }
}

if (warnings.length > 0) {
    // `::warning::` is a GitHub Actions annotation; elsewhere it is just a prefix.
    console.warn(`docs integrity check: ${warnings.length} warning(s):\n`)
    for (const warning of warnings) {
        console.warn(`::warning::${warning}`)
    }
    console.warn('')
}

if (errors.length > 0) {
    console.error(`docs integrity check found ${errors.length} problem(s):\n`)
    for (const error of errors) {
        console.error(`  - ${error}`)
    }
    process.exit(1)
}

console.log(`docs integrity check passed: ${PACKAGES.length} packages, ${apiEntries.length} @api types, all internal links resolve.`)

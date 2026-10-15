import { defineConfig } from 'vitepress'

import packages from '../scripts/packages.json'

const SITE_URL = 'https://rasuvaeff.github.io/yii3-ab-testing/'
const REPO = 'https://github.com/rasuvaeff/yii3-ab-testing'

// Ordered the way a request travels: decide, record, keep stable, deliver,
// analyse — then the cross-cutting wiring.
const sidebar = [
    {
        text: 'Introduction',
        items: [
            { text: 'Getting started', link: '/guide/getting-started' },
            { text: 'Choosing a combination', link: '/integration' },
        ],
    },
    {
        text: 'Assignment',
        items: [
            { text: 'Hash bucketing v1', link: '/guide/assignment' },
            { text: 'Decision reason and source', link: '/guide/decision' },
            { text: 'Targeting', link: '/guide/targeting' },
        ],
    },
    {
        text: 'Tracking',
        items: [
            { text: 'Events v2', link: '/guide/events' },
            { text: 'Attribution', link: '/guide/attribution' },
        ],
    },
    {
        text: 'Persistence',
        items: [{ text: 'Sticky assignments', link: '/guide/persistence' }],
    },
    {
        text: 'Delivery',
        items: [{ text: 'Two paths, one row', link: '/guide/delivery' }],
    },
    {
        text: 'Analytics',
        items: [{ text: 'Reading results', link: '/guide/analytics' }],
    },
    {
        text: 'Wiring',
        items: [
            { text: 'DI: one source per key', link: '/guide/di' },
            { text: 'Worker runtimes', link: '/guide/worker-runtimes' },
        ],
    },
    {
        text: 'Packages',
        collapsed: false,
        items: packages.map((pkg) => ({ text: pkg.name.replace('rasuvaeff/', ''), link: `/packages/${pkg.slug}` })),
    },
    {
        text: 'Reference',
        items: [
            { text: 'API reference', link: '/api/' },
            ...packages.map((pkg) => ({ text: `llms.txt — ${pkg.title}`, link: `/llms/${pkg.slug}` })),
        ],
    },
]

export default defineConfig({
    title: 'Yii3 A/B Testing',
    description:
        'Deterministic A/B testing for Yii3: stateless hash assignment, targeting, sticky variants, one event contract for durable and log-shipped delivery, and ClickHouse analytics.',
    base: '/yii3-ab-testing/',
    cleanUrls: true,
    lastUpdated: true,
    sitemap: { hostname: SITE_URL },
    // siblings/ holds the four checked-out sibling repositories and their own
    // vendor/ trees: a third-party CHANGELOG in there must never become a page.
    // integration.ru.md is the Russian half of the package docs; the site is
    // EN-only.
    srcExclude: ['siblings/**', 'node_modules/**', '**/*.ru.md', 'scripts/**'],
    head: [
        ['link', { rel: 'icon', type: 'image/svg+xml', href: '/yii3-ab-testing/favicon.svg' }],
        ['meta', { name: 'theme-color', content: '#0F766E' }],
        ['meta', { property: 'og:type', content: 'website' }],
        ['meta', { property: 'og:site_name', content: 'Yii3 A/B Testing' }],
        ['meta', { name: 'twitter:card', content: 'summary' }],
    ],
    transformHead: ({ pageData, title, description }) => {
        const clean = pageData.relativePath.replace(/\.md$/, '').replace(/(^|\/)index$/, '$1')
        const url = SITE_URL + clean

        return [
            ['link', { rel: 'canonical', href: url }],
            ['meta', { property: 'og:title', content: title }],
            ['meta', { property: 'og:description', content: description }],
            ['meta', { property: 'og:url', content: url }],
            ['meta', { name: 'twitter:title', content: title }],
            ['meta', { name: 'twitter:description', content: description }],
        ]
    },
    themeConfig: {
        logo: '/logo-mark.svg',
        nav: [
            { text: 'Guide', link: '/guide/getting-started' },
            { text: 'Packages', link: '/packages/core' },
            { text: 'API', link: '/api/' },
        ],
        sidebar: { '/': sidebar },
        search: { provider: 'local' },
        outline: { level: [2, 3], label: 'On this page' },
        socialLinks: [{ icon: 'github', link: REPO }],
        editLink: {
            pattern: `${REPO}/edit/master/docs/:path`,
        },
    },
})

<?php

return [

    'default_locale' => 'ru',

    'locales' => ['ru', 'en'],

    'structure' => 'panel-based', // flat, nested, or panel-based

    'backup' => true,

    'git' => [
        'enabled' => true,
        'commit_message' => 'chore: add Filament localization support',
    ],

    'excluded_panels' => [],

    'excluded_resources' => [],

    'translation_key_prefix' => 'filament',

    // Skip Terms - Terms that should remain the same across languages
    'skip_identical_terms' => [
        'API', 'URL', 'HTTP', 'HTTPS', 'PDF', 'CSV', 'JSON', 'XML',
        'Laravel', 'Filament', 'Vue', 'React', 'Angular', 'Google', 'Microsoft',
        'GitHub', 'Docker', 'AWS', 'Azure', 'PayPal', 'Stripe', 'WordPress',
        'Bootstrap', 'Tailwind', 'jQuery', 'TypeScript', 'Webpack', 'Vite',
        'JWT', 'OAuth', 'REST', 'GraphQL', 'SEO', 'UX', 'UI', 'SaaS', 'PaaS',
        'IoT', 'AI', 'ML', 'DevOps', 'CI/CD', 'Agile', 'Scrum', 'TDD', 'BDD',
        'GDPR', 'ISO', 'IEEE', 'W3C', 'OWASP', 'NIST', 'ITIL', 'PMP',
        'SMS', 'Email',
    ],

    // DeepL Translation Configuration
    'deepl' => [
        'api_key' => env('DEEPL_API_KEY'),
        'base_url' => env('DEEPL_BASE_URL', 'https://api-free.deepl.com/v2'),
        'timeout' => 60,
        'batch_size' => 50,
        'preserve_formatting' => true,
    ],

];

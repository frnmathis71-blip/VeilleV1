<?php

return [
    // "gemini" asks Google Gemini to judge each article; "local" keeps the keyword heuristic.
    'analyzer' => env('WATCH_ANALYZER', 'gemini'),
    // When a feed gives no image, read the og:image declared in the <head> of the article page.
    'preview_images' => (bool) env('WATCH_PREVIEW_IMAGES', true),
    'defaults' => [
        'threshold' => 70,
        'batch_size' => 30,
        'interval_hours' => 24,
        'retention_days' => 180,
        'enabled' => true,
        'priority_keywords' => 'intelligence artificielle, cybersécurité, open source, framework',
        'excluded_keywords' => 'code promo, bon plan, soldes',
        'irrelevant_retention_days' => 30,
        'ai_profile' => "Veille technologique informatique pour un étudiant en BTS SIO (options SLAM et SISR).\n"
            .'Pertinent : actualités techniques sur le développement logiciel et web, la cybersécurité, les réseaux, '
            ."les systèmes, le cloud, les bases de données, l'intelligence artificielle et l'open source ; nouvelles versions, "
            ."failles, outils, normes et tendances utiles à un professionnel de l'informatique.\n"
            .'Impertinent : promotions et bons plans, tests de produits grand public sans contenu technique, rumeurs, '
            ."people, jeux vidéo sans angle technique, articles sans rapport avec l'informatique.",
        'weights' => ['novelty' => 25, 'importance' => 15, 'impact' => 15, 'usefulness' => 20, 'originality' => 5, 'reliability' => 10, 'freshness' => 10],
    ],
    'keywords' => [
        'intelligence-artificielle' => ['intelligence artificielle', 'machine learning', 'llm', 'openai', 'anthropic', 'neural', 'gpt'],
        'cybersecurite' => ['cybersécurité', 'ransomware', 'vulnerability', 'vulnérabilité', 'cve-', 'malware', 'security flaw'],
        'developpement-web' => ['javascript', 'html', 'css', 'web development', 'frontend', 'backend'],
        'programmation' => ['framework', 'laravel', 'python', 'php', 'rust', 'typescript', 'developer'],
        'materiel' => ['hardware', 'matériel', 'ordinateur', 'laptop', 'ssd'],
        'processeurs-gpu' => ['processeur', 'gpu', 'cpu', 'nvidia', 'ryzen', 'geforce'],
        'systemes-exploitation' => ['linux', 'windows', 'macos', 'android', 'ios'],
        'cloud' => ['cloud', 'kubernetes', 'docker', 'aws', 'azure'],
        'reseaux' => ['réseau', 'network', '5g', '6g', 'wifi', 'wi-fi', 'télécommunication'],
        'mobile' => ['smartphone', 'iphone', 'pixel', 'mobile', 'samsung'],
        'robotique' => ['robot', 'robotics', 'automatisation', 'automation'],
        'innovations' => ['research', 'recherche', 'scientifique', 'breakthrough', 'quantum'],
        'open-source' => ['open source', 'open-source', 'github', 'logiciel libre'],
        'bases-de-donnees' => ['database', 'base de données', 'postgresql', 'mysql', 'sqlite', 'mongodb'],
        'technologies-emergentes' => ['quantique', 'quantum', 'réalité augmentée', 'virtual reality', 'fusion'],
    ],
];

<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'intelligence-artificielle' => 'Intelligence artificielle et machine learning',
            'cybersecurite' => 'Cybersécurité',
            'developpement-web' => 'Développement web',
            'programmation' => 'Programmation et frameworks',
            'materiel' => 'Matériel informatique',
            'processeurs-gpu' => 'Processeurs et cartes graphiques',
            'systemes-exploitation' => 'Systèmes d’exploitation',
            'cloud' => 'Cloud computing',
            'reseaux' => 'Réseaux et télécommunications',
            'mobile' => 'Smartphones et technologies mobiles',
            'robotique' => 'Robotique et automatisation',
            'innovations' => 'Innovations scientifiques et technologiques',
            'open-source' => 'Open source et logiciels',
            'bases-de-donnees' => 'Bases de données',
            'technologies-emergentes' => 'Technologies émergentes',
        ];
        foreach ($categories as $slug => $name) {
            Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'keywords' => [], 'is_monitored' => true]);
        }
    }
}

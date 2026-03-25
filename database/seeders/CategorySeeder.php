<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = now();

        // Catégories racines
        $roots = [
            ['name' => 'Logiciels',          'slug' => 'logiciels',          'description' => 'Applications et logiciels numériques',          'order' => 1],
            ['name' => 'Formations',          'slug' => 'formations',          'description' => 'Cours et formations en ligne',                   'order' => 2],
            ['name' => 'Design & Graphisme',  'slug' => 'design-graphisme',    'description' => 'Templates, illustrations et ressources design',  'order' => 3],
            ['name' => 'Audio & Musique',     'slug' => 'audio-musique',       'description' => 'Sons, musiques et ressources audio',             'order' => 4],
            ['name' => 'Vidéo',               'slug' => 'video',               'description' => 'Vidéos, animations et effets visuels',           'order' => 5],
            ['name' => 'Développement Web',   'slug' => 'developpement-web',   'description' => 'Thèmes, plugins et scripts web',                 'order' => 6],
        ];

        foreach ($roots as $root) {
            DB::table('categories')->insert(array_merge($root, [
                'parent_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Récupérer les IDs
        $ids = DB::table('categories')->pluck('id', 'slug');

        // Sous-catégories
        $subs = [
            // Logiciels
            ['name' => 'Productivité',      'slug' => 'productivite',       'description' => 'Outils de productivité et bureautique',  'parent' => 'logiciels',        'order' => 1],
            ['name' => 'Sécurité',          'slug' => 'securite',           'description' => 'Antivirus et outils de sécurité',        'parent' => 'logiciels',        'order' => 2],
            ['name' => 'Utilitaires',       'slug' => 'utilitaires',        'description' => 'Outils système et utilitaires',          'parent' => 'logiciels',        'order' => 3],
            // Formations
            ['name' => 'Programmation',     'slug' => 'programmation',      'description' => 'Cours de code et développement',         'parent' => 'formations',       'order' => 1],
            ['name' => 'Marketing Digital', 'slug' => 'marketing-digital',  'description' => 'SEO, réseaux sociaux, publicité',        'parent' => 'formations',       'order' => 2],
            ['name' => 'Business',          'slug' => 'business',           'description' => 'Entrepreneuriat et gestion',             'parent' => 'formations',       'order' => 3],
            // Design
            ['name' => 'Templates UI',      'slug' => 'templates-ui',       'description' => 'Maquettes et templates d\'interface',    'parent' => 'design-graphisme', 'order' => 1],
            ['name' => 'Illustrations',     'slug' => 'illustrations',      'description' => 'Illustrations vectorielles et cliparts', 'parent' => 'design-graphisme', 'order' => 2],
            ['name' => 'Polices',           'slug' => 'polices',            'description' => 'Typographies et polices de caractères',  'parent' => 'design-graphisme', 'order' => 3],
            // Audio
            ['name' => 'Musique',           'slug' => 'musique',            'description' => 'Pistes musicales et jingles',            'parent' => 'audio-musique',    'order' => 1],
            ['name' => 'Effets sonores',    'slug' => 'effets-sonores',     'description' => 'Sons et effets pour projets',            'parent' => 'audio-musique',    'order' => 2],
            // Vidéo
            ['name' => 'Animations',        'slug' => 'animations',         'description' => 'Animations et motion graphics',         'parent' => 'video',            'order' => 1],
            ['name' => 'Stock vidéo',       'slug' => 'stock-video',        'description' => 'Vidéos libres de droits',                'parent' => 'video',            'order' => 2],
            // Dev Web
            ['name' => 'Thèmes WordPress',  'slug' => 'themes-wordpress',   'description' => 'Thèmes et templates WordPress',         'parent' => 'developpement-web','order' => 1],
            ['name' => 'Plugins',           'slug' => 'plugins',            'description' => 'Extensions et plugins web',             'parent' => 'developpement-web','order' => 2],
            ['name' => 'Scripts',           'slug' => 'scripts',            'description' => 'Scripts et snippets de code',           'parent' => 'developpement-web','order' => 3],
        ];

        foreach ($subs as $sub) {
            DB::table('categories')->insert([
                'name'        => $sub['name'],
                'slug'        => $sub['slug'],
                'description' => $sub['description'],
                'parent_id'   => $ids[$sub['parent']],
                'order'       => $sub['order'],
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }
}

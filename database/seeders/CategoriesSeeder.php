<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Électronique',
                'slug' => 'electronique',
                'description' => 'Appareils électroniques et gadgets',
                'children' => [
                    ['name' => 'Smartphones', 'slug' => 'smartphones'],
                    ['name' => 'Ordinateurs', 'slug' => 'ordinateurs'],
                    ['name' => 'Tablettes', 'slug' => 'tablettes'],
                    ['name' => 'Accessoires', 'slug' => 'accessoires-electronique'],
                ]
            ],
            [
                'name' => 'Mode & Vêtements',
                'slug' => 'mode-vetements',
                'description' => 'Vêtements et accessoires de mode',
                'children' => [
                    ['name' => 'Homme', 'slug' => 'vetements-homme'],
                    ['name' => 'Femme', 'slug' => 'vetements-femme'],
                    ['name' => 'Enfant', 'slug' => 'vetements-enfant'],
                    ['name' => 'Chaussures', 'slug' => 'chaussures'],
                    ['name' => 'Accessoires', 'slug' => 'accessoires-mode'],
                ]
            ],
            [
                'name' => 'Maison & Jardin',
                'slug' => 'maison-jardin',
                'description' => 'Articles pour la maison et le jardin',
                'children' => [
                    ['name' => 'Mobilier', 'slug' => 'mobilier'],
                    ['name' => 'Décoration', 'slug' => 'decoration'],
                    ['name' => 'Électroménager', 'slug' => 'electromenager'],
                    ['name' => 'Jardinage', 'slug' => 'jardinage'],
                    ['name' => 'Bricolage', 'slug' => 'bricolage'],
                ]
            ],
            [
                'name' => 'Sport & Loisirs',
                'slug' => 'sport-loisirs',
                'description' => 'Articles de sport et loisirs',
                'children' => [
                    ['name' => 'Fitness', 'slug' => 'fitness'],
                    ['name' => 'Sports d\'équipe', 'slug' => 'sports-equipe'],
                    ['name' => 'Sports individuels', 'slug' => 'sports-individuels'],
                    ['name' => 'Plein air', 'slug' => 'plein-air'],
                ]
            ],
            [
                'name' => 'Livres & Médias',
                'slug' => 'livres-medias',
                'description' => 'Livres, films, musique et médias numériques',
                'children' => [
                    ['name' => 'Livres', 'slug' => 'livres'],
                    ['name' => 'E-books', 'slug' => 'ebooks'],
                    ['name' => 'Films & Séries', 'slug' => 'films-series'],
                    ['name' => 'Musique', 'slug' => 'musique'],
                    ['name' => 'Jeux vidéo', 'slug' => 'jeux-video'],
                ]
            ],
            [
                'name' => 'Beauté & Santé',
                'slug' => 'beaute-sante',
                'description' => 'Produits de beauté et de santé',
                'children' => [
                    ['name' => 'Soins du visage', 'slug' => 'soins-visage'],
                    ['name' => 'Soins du corps', 'slug' => 'soins-corps'],
                    ['name' => 'Maquillage', 'slug' => 'maquillage'],
                    ['name' => 'Parfums', 'slug' => 'parfums'],
                    ['name' => 'Compléments', 'slug' => 'complements'],
                ]
            ],
            [
                'name' => 'Auto & Moto',
                'slug' => 'auto-moto',
                'description' => 'Pièces et accessoires automobiles',
                'children' => [
                    ['name' => 'Pièces auto', 'slug' => 'pieces-auto'],
                    ['name' => 'Accessoires auto', 'slug' => 'accessoires-auto'],
                    ['name' => 'Pièces moto', 'slug' => 'pieces-moto'],
                    ['name' => 'Accessoires moto', 'slug' => 'accessoires-moto'],
                ]
            ],
            [
                'name' => 'Services Numériques',
                'slug' => 'services-numeriques',
                'description' => 'Services et produits numériques',
                'children' => [
                    ['name' => 'Logiciels', 'slug' => 'logiciels'],
                    ['name' => 'Templates', 'slug' => 'templates'],
                    ['name' => 'Formations', 'slug' => 'formations'],
                    ['name' => 'Consultations', 'slug' => 'consultations'],
                ]
            ],
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $category = Category::create($categoryData);

            foreach ($children as $childData) {
                $childData['parent_id'] = $category->id;
                Category::create($childData);
            }
        }
    }
}
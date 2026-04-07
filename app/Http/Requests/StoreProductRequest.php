<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;
        return $role === 'vendor';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Informations de base
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'shortDescription' => 'nullable|string|max:500',
            'sku' => 'nullable|string|unique:products,sku',
            
            // Prix et stock
            'price' => 'required|numeric|min:0',
            'comparePrice' => 'nullable|numeric|min:0|gt:price',
            'costPerItem' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'lowStockThreshold' => 'nullable|integer|min:0',
            
            // Catégorie
            'category' => 'required|exists:categories,id',
            'subcategory' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            
            // Livraison
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric|min:0',
            'dimensions.width' => 'nullable|numeric|min:0',
            'dimensions.height' => 'nullable|numeric|min:0',
            
            // SEO
            'seoTitle' => 'nullable|string|max:60',
            'seoDescription' => 'nullable|string|max:160',
            'slug' => 'nullable|string|unique:products,slug',
            
            // Médias
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'featuredImage' => 'nullable|integer|min:0',

            // Produit digital
            'is_digital' => 'nullable|boolean',
            'digital_file' => 'nullable|file|max:51200', // 50 Mo max
            
            // Métadonnées
            'status' => 'nullable|in:draft,active,archived',
            'visibility' => 'nullable|in:public,private,hidden',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Informations de base
            'name.required' => 'Le nom du produit est obligatoire.',
            'name.max' => 'Le nom du produit ne peut pas dépasser 255 caractères.',
            'description.required' => 'La description du produit est obligatoire.',
            'shortDescription.max' => 'La description courte ne peut pas dépasser 500 caractères.',
            'sku.unique' => 'Ce SKU est déjà utilisé par un autre produit.',
            
            // Prix et stock
            'price.required' => 'Le prix de vente est obligatoire.',
            'price.numeric' => 'Le prix doit être un nombre valide.',
            'price.min' => 'Le prix doit être supérieur ou égal à 0.',
            'comparePrice.numeric' => 'Le prix comparatif doit être un nombre valide.',
            'comparePrice.min' => 'Le prix comparatif doit être supérieur ou égal à 0.',
            'comparePrice.gt' => 'Le prix comparatif doit être supérieur au prix de vente.',
            'costPerItem.numeric' => 'Le coût par article doit être un nombre valide.',
            'costPerItem.min' => 'Le coût par article doit être supérieur ou égal à 0.',
            'stock.required' => 'La quantité en stock est obligatoire.',
            'stock.integer' => 'La quantité en stock doit être un nombre entier.',
            'stock.min' => 'La quantité en stock doit être supérieure ou égale à 0.',
            'lowStockThreshold.integer' => 'Le seuil de stock faible doit être un nombre entier.',
            'lowStockThreshold.min' => 'Le seuil de stock faible doit être supérieur ou égal à 0.',
            
            // Catégorie
            'category.required' => 'La catégorie est obligatoire.',
            'category.exists' => 'La catégorie sélectionnée n\'existe pas.',
            'subcategory.exists' => 'La sous-catégorie sélectionnée n\'existe pas.',
            'tags.array' => 'Les tags doivent être un tableau.',
            'tags.*.string' => 'Chaque tag doit être une chaîne de caractères.',
            'tags.*.max' => 'Chaque tag ne peut pas dépasser 50 caractères.',
            
            // Livraison
            'weight.numeric' => 'Le poids doit être un nombre valide.',
            'weight.min' => 'Le poids doit être supérieur ou égal à 0.',
            'dimensions.array' => 'Les dimensions doivent être un tableau.',
            'dimensions.length.numeric' => 'La longueur doit être un nombre valide.',
            'dimensions.length.min' => 'La longueur doit être supérieure ou égale à 0.',
            'dimensions.width.numeric' => 'La largeur doit être un nombre valide.',
            'dimensions.width.min' => 'La largeur doit être supérieure ou égale à 0.',
            'dimensions.height.numeric' => 'La hauteur doit être un nombre valide.',
            'dimensions.height.min' => 'La hauteur doit être supérieure ou égale à 0.',
            
            // SEO
            'seoTitle.max' => 'Le titre SEO ne peut pas dépasser 60 caractères.',
            'seoDescription.max' => 'La description SEO ne peut pas dépasser 160 caractères.',
            'slug.unique' => 'Ce slug est déjà utilisé par un autre produit.',
            
            // Médias
            'images.required' => 'Au moins une image est obligatoire.',
            'images.array' => 'Les images doivent être un tableau.',
            'images.min' => 'Au moins une image est obligatoire.',
            'images.max' => 'Vous ne pouvez pas télécharger plus de 5 images.',
            'images.*.image' => 'Chaque fichier doit être une image.',
            'images.*.mimes' => 'Les images doivent être au format JPEG, PNG, JPG ou GIF.',
            'images.*.max' => 'Chaque image ne peut pas dépasser 2 Mo.',
            'featuredImage.integer' => 'L\'index de l\'image principale doit être un nombre entier.',
            'featuredImage.min' => 'L\'index de l\'image principale doit être supérieur ou égal à 0.',
            
            // Métadonnées
            'status.in' => 'Le statut doit être draft, active ou archived.',
            'visibility.in' => 'La visibilité doit être public, private ou hidden.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom du produit',
            'description' => 'description',
            'shortDescription' => 'description courte',
            'sku' => 'SKU',
            'price' => 'prix de vente',
            'comparePrice' => 'prix comparatif',
            'costPerItem' => 'coût par article',
            'stock' => 'quantité en stock',
            'lowStockThreshold' => 'seuil de stock faible',
            'category' => 'catégorie',
            'subcategory' => 'sous-catégorie',
            'tags' => 'tags',
            'weight' => 'poids',
            'dimensions' => 'dimensions',
            'seoTitle' => 'titre SEO',
            'seoDescription' => 'description SEO',
            'slug' => 'slug',
            'images' => 'images',
            'featuredImage' => 'image principale',
            'status' => 'statut',
            'visibility' => 'visibilité',
        ];
    }
}
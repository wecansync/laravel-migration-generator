<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'name',
        'description',
        'description2',
        'price',
        'price2',
        'category_id',
        'brand_id',
    ];

    /**
     * Configuration for migration schema.
     *
     * @var array<string, array<string, mixed>>
     */
    public $migrationSchema = [
        'name' => ['type' => 'string', 'length' => 255, 'nullable' => true],
        'description' => ['type' => 'text', 'nullable' => true],
        'description2' => ['type' => 'text', 'nullable' => true],
        'price' => ['type' => 'integer', 'nullable' => true],
        'price2' => ['type' => 'json', 'nullable' => true],
        'category_id' => ['type' => 'unsignedBigInteger', 'nullable' => true],
        'brand_id' => ['type' => 'unsignedBigInteger', 'nullable' => true],
    ];


    // Define relationships in the model configuration
    public $relationships = [
        [
            'type' => 'foreign',
            'column' => 'category_id',
            'table' => 'categories', // Related table
            'field' => 'id', // Primary key in the related table
            'onDelete' => 'set null', // Optional, define the behavior on delete
        ],
        [
            'type' => 'foreign',
            'column' => 'brand_id',
            'table' => 'brands', // Related table
            'field' => 'id', // Primary key in the related table
            'onDelete' => 'set null', // Optional, define the behavior on delete
        ],
        [
            'type' => 'manyToMany',
            'table1' => 'products',
            'table2' => 'tags'
        ],
        [
            'type' => 'manyToMany',
            'table1' => 'products',
            'table2' => 'categories'
        ],
    ];
}


## Migration files generator

The simplest way to generate schema files like the Doctrine method used in Symfony.

Instead of manually writing migration content using the default Laravel command php artisan make:migration, which only creates the file and requires you to add columns manually, our script simplifies the process. 
By modifying the attribute values (like type) in your model, the script automatically detects the changes and generates the corresponding migration file for you. 
This eliminates the need for manual column type changes (e.g., from varchar to text or setting nullable) and streamlines the migration creation process.

## Installation
```
composer require wecansync/laravel-migration-generator
```


## Usage
### 1. Add the configuration attributes to your Model
```
    // App\Models\Product.php

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
        'price3',
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
        'price3' => ['type' => 'string'],
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
            'type' => 'foreign',
            'column' => 'group_id',
            'table' => 'groups', // Related table
            'field' => 'id', // Primary key in the related table
            'onDelete' => 'set null', // Optional, define the behavior on delete
        ],
        [
            'type' => 'manyToMany',
            'table1' => 'products',
            'table2' => 'tags'
        ]
    ];
```

### 2. Generate migration files for your Model
```
php artisan generate:migration Product
```

### 3. Migrate
```
php artisan migrate
```

## Default configuration
If you didn't add the configuration to your Model, the script will read all fields from $fillable attribute and create the default migrations for your Model

## Important
Your $fillable attribute should be public in order to be readable by the script.
```
public $fillable = [
// your fields here
];
```

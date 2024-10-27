
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
    // App\Models\Category.php

    /**
     * Configuration for migration schema.
     *
     * @var array<string, array<string, mixed>>
     */
    public $migration_schema = [
        'name' => ['type' => 'string', 'length' => 255, 'nullable' => true],
    ];

```

```

    // App\Models\Brand.php

    /**
     * Configuration for migration schema.
     *
     * @var array<string, array<string, mixed>>
     */
    public $migration_schema = [
        'name' => ['type' => 'string', 'length' => 255, 'nullable' => true],
    ];

```

```

    // App\Models\Tag.php

    /**
     * Configuration for migration schema.
     *
     * @var array<string, array<string, mixed>>
     */
    public $migration_schema = [
        'name' => ['type' => 'string', 'length' => 255, 'nullable' => true],
    ];

```

```
    // App\Models\Product.php


    /**
     * Configuration for migration schema.
     *
     * @var array<string, array<string, mixed>>
     */
    public $migration_schema = [
        'name' => ['type' => 'string', 'length' => 255, 'nullable' => true],
        'description' => ['type' => 'text', 'nullable' => true],
        'price' => ['type' => 'integer', 'nullable' => true],
    ];

    // Define relationships in the model configuration
    public $relationships = [
        [
            'column' => 'category_id',
            'type' => 'foreign',
            'model' => Category::class, // Related model
            'field' => 'id', // optional: Primary key in the related table (default is 'id')
        ],
        [
            'column' => 'brand_id',
            'type' => 'foreignId',
            'model' => Brand::class, // Related table
        ],
        [
            'type' => 'manyToMany',
            'model' => Tag::class
        ]
    ];
```

### 2. Generate migration files for your Model
```
php artisan generate:migration Category
```
```
php artisan generate:migration Brand
```
```
php artisan generate:migration Tag
```
```
php artisan generate:migration Product
```

### 3. Migrate
```
php artisan migrate
```

## Default configuration
If you didn't add the configuration array to $migration_schema, the script will create the default migrations for your fields
```
public $migration_schema = [
// your fields here
'name' => [], // is the array is empty default_schema will be created
];
```

## Important
Your $relationships and $migration_schema attributes should be public in order to be readable by the script.
```
public $migration_schema = [
// your fields here
];

public $relationships = [
// your relations here
];
```

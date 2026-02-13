# Laravel Make Full

Generate complete CRUD structure from a single command: Model, Controller, Service, Repository, Resource, Requests, Policy, Migration, Factory, Seeder.

## Features

- **Single Command** - Generate all files at once with `php artisan make:full`
- **Field Definition** - Define fields inline and auto-generate validations, migrations, factories
- **Smart Validation** - Generates proper validation rules based on field types
- **Search Built-in** - Service/Repository includes search with pagination
- **Customizable** - Publish stubs to customize generated code

## Installation

```bash
composer require campelo/laravel-make-full --dev
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=make-full-config
```

## Quick Start

```bash
# Basic usage
php artisan make:full Post

# With fields
php artisan make:full Post --fields="title:string,content:text,published:boolean:default(false)"

# Full example
php artisan make:full BlogPost --fields="title:string,slug:string:unique,content:text:nullable,user_id:foreignId,published_at:datetime:nullable" --soft-deletes
```

## Generated Files

| File | Path | Description |
|------|------|-------------|
| Model | `app/Models/Post.php` | Eloquent model with fillable, casts, relations |
| Controller | `app/Http/Controllers/PostController.php` | API controller with CRUD methods |
| Service | `app/Services/PostService.php` | Business logic with search/pagination |
| Repository | `app/Repositories/PostRepository.php` | Data access layer |
| Resource | `app/Http/Resources/PostResource.php` | API resource transformer |
| StoreRequest | `app/Http/Requests/StorePostRequest.php` | Validation for create |
| UpdateRequest | `app/Http/Requests/UpdatePostRequest.php` | Validation for update |
| Policy | `app/Policies/PostPolicy.php` | Authorization policy |
| Migration | `database/migrations/xxx_create_posts_table.php` | Database migration |
| Factory | `database/factories/PostFactory.php` | Model factory with faker |
| Seeder | `database/seeders/PostSeeder.php` | Database seeder |
| Routes | `routes/api.php` | API resource routes (appended) |

## Field Definition Syntax

```
field_name:type:modifier1:modifier2:...
```

### Supported Types

| Type | Migration | Validation | Faker |
|------|-----------|------------|-------|
| `string` | `string()` | `string\|max:255` | `word()` |
| `text` | `text()` | `string` | `paragraph()` |
| `integer` | `integer()` | `integer` | `numberBetween()` |
| `boolean` | `boolean()` | `boolean` | `boolean()` |
| `date` | `date()` | `date` | `date()` |
| `datetime` | `dateTime()` | `date` | `dateTime()` |
| `decimal` | `decimal()` | `numeric` | `randomFloat()` |
| `json` | `json()` | `array` | `[]` |
| `foreignId` | `foreignId()` | `integer\|exists:table,id` | `numberBetween()` |

### Modifiers

| Modifier | Effect |
|----------|--------|
| `nullable` | Field is optional |
| `unique` | Adds unique constraint + validation |
| `index` | Adds database index |
| `default(value)` | Sets default value |

### Smart Field Detection

Field names automatically get appropriate faker methods:

- `*email*` → `safeEmail()`
- `*name*` → `name()`
- `*phone*` → `phoneNumber()`
- `*price*`, `*amount*` → `randomFloat(2, 10, 1000)`
- `*_id` → `numberBetween(1, 10)`
- `*title*` → `sentence(3)`
- `*description*`, `*content*` → `paragraph()`

## Examples

### Blog Post

```bash
php artisan make:full Post --fields="title:string,slug:string:unique,excerpt:text:nullable,content:text,user_id:foreignId,published_at:datetime:nullable,views:integer:default(0)" --soft-deletes
```

### Product

```bash
php artisan make:full Product --fields="name:string,sku:string:unique,description:text:nullable,price:decimal,stock:integer:default(0),category_id:foreignId,active:boolean:default(true)"
```

### User Profile

```bash
php artisan make:full UserProfile --fields="user_id:foreignId:unique,bio:text:nullable,avatar:string:nullable,phone:string:nullable,birth_date:date:nullable"
```

## Generated Code Examples

### Service with Search

```php
public function search(array $params = []): LengthAwarePaginator
{
    $query = Post::query();

    // Search filter
    if (!empty($params['search'])) {
        $search = $params['search'];
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%");
            $q->orWhere('content', 'like', "%{$search}%");
        });
    }

    // Sorting
    $sortField = $params['sort'] ?? 'created_at';
    $sortOrder = $params['order'] ?? 'desc';
    $query->orderBy($sortField, $sortOrder);

    // Pagination
    $limit = $params['limit'] ?? 15;

    return $query->paginate($limit);
}
```

### Controller

```php
public function index(Request $request): AnonymousResourceCollection
{
    $result = $this->service->search($request->all());

    return PostResource::collection($result);
}
```

### Update Request with Unique Validation

```php
public function rules(): array
{
    return [
        'title' => 'sometimes|required|string|max:255',
        'slug' => [
            'sometimes',
            'required',
            'string|max:255',
            Rule::unique('posts', 'slug')->ignore($this->post?->id),
        ],
        'content' => 'sometimes|required|string',
    ];
}
```

## Command Options

```bash
php artisan make:full {name}
    --fields=           # Field definitions
    --no-model          # Skip model
    --no-controller     # Skip controller
    --no-service        # Skip service
    --no-repository     # Skip repository
    --no-resource       # Skip resource
    --no-requests       # Skip requests
    --no-policy         # Skip policy
    --no-migration      # Skip migration
    --no-factory        # Skip factory
    --no-seeder         # Skip seeder
    --no-routes         # Skip routes
    --soft-deletes      # Add soft deletes
    --uuid              # Use UUID primary key
    --web               # Generate web controller (default is API)
    --force             # Overwrite existing files
```

## Configuration

```php
// config/make-full.php

return [
    // Namespaces
    'namespaces' => [
        'model' => 'App\\Models',
        'controller' => 'App\\Http\\Controllers',
        'service' => 'App\\Services',
        'repository' => 'App\\Repositories',
        // ...
    ],

    // Use repository pattern (Controller -> Service -> Repository)
    'use_repository' => true,

    // Default pagination
    'default_pagination' => 15,

    // Auto-add routes to api.php
    'add_routes' => true,
];
```

## Customizing Stubs

Publish stubs to customize generated code:

```bash
php artisan vendor:publish --tag=make-full-stubs
```

Stubs will be published to `stubs/make-full/`.

## License

MIT

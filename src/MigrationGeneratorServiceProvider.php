<?php

namespace FDS\MigrationGenerator\ServiceProvider;

use Illuminate\Support\ServiceProvider;

class MigrationGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publishing config, migrations, or views.
        $this->publishes([
            __DIR__.'/src/config/migration-generator.php' => config_path('migration-generator.php'),
        ]);
    }

    public function register()
    {
        // Merge the config if necessary
        $this->mergeConfigFrom(
            __DIR__.'/src/config/migration-generator.php', 'migration-generator'
        );
    }
}
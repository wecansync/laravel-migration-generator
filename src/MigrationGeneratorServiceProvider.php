<?php

namespace WeCanSync\MigrationGenerator;

use WeCanSync\MigrationGenerator\Commands\GenerateMigrationFromModel;
use Illuminate\Support\ServiceProvider;

class MigrationGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publishing config, migrations, or views.
        $this->publishes([
            __DIR__.'/config/migration-generator.php' => config_path('migration-generator.php'),
        ]);

        // Commands are typically registered in the boot method
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMigrationFromModel::class,
            ]);
        }
    }

    public function register()
    {
        // Merge the config if necessary
        $this->mergeConfigFrom(
            __DIR__.'/config/migration-generator.php', 'migration-generator'
        );
    }
}
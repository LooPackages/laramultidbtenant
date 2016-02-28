<?php

namespace gamerwalt\LaraMultiDbTenant;

use gamerwalt\LaraMultiDbTenant\Commands\TenantMigrationsCommand;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use App;

class LaraMultiDbTenantServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/laramultidbtenant.php';
        $this->mergeConfigFrom($configPath, 'laramultidbtenant');

        $this->app->singleton('LaraMultiDbTenant', function($app) {
            $laraMultiDbTenant = new LaraMultiDbTenant($app('Illuminate\Config\Repository'),$app);

            return $laraMultiDbTenant;
        });

        $this->app->alias('laramultitenantdb', 'gamerwalt\LaraMultiDbTenant\LaraMultiDbTenant');

        $this->app['command.tenant.migrations'] = $this->app->share(
        function($app) {
            return new Commands\MultiDbFoldersCommand($app['laramultitenantdb']);
        }
        );

        $this->app['command.tenant.basemodels'] = $this->app->share(
        function($app) {
            return new Commands\BaseModelsCommand($app['laramultitenantdb'], $app('Illuminate\Contracts\Console\Kernel'));
        }
        );

        $this->commands(array('command.tenant.migrations'));
        $this->commands(array('command.tenant.basemodels'));

        App::singleton('laramultitenantdb', function($app){
            return new LaraMultiDbTenant(app('Illuminate\Config\Repository'),$app);
        });

        App::singleton('tenantdatabaseprovisioner', function(){
            return new MysqlDatabaseProvisioner(app('Illuminate\Contracts\Console\Kernel'));
        });
    }

    /**
     * Bootstrap the application events
     *
     * @param \Illuminate\Contracts\Http\Kernel $kernel
     *
     * @return void
     */
    public function boot(Kernel $kernel)
    {
        $app = $this->app;

        $configPath = __DIR__ . '/../config/laramultidbtenant.php';
        $this->publishes([$configPath => $this->getConfigPath()], 'config');

        $migrationsPath = __DIR__ . '/../migrations/';
        $this->publishes([$migrationsPath => $this->getMigrationsPath()], 'migrations');

        $prefix = $this->app['config']->get('laramultidbtenant.prefix');
        $tenantModel = $this->app['config']->get('laramultidbtenant.tenantmodel');

        if($app->runningInConsole()) {
            return;
        }

        $kernel->pushMiddleware('gamerwalt\LaraMultiDbTenant\AuthTenant');

        $laraMultidbTenant = $this->app['laramultitenantdb'];
        $laraMultidbTenant->boot($tenantModel, $prefix);
    }

    /**
     * Get the active router.
     *
     * @return Router
     */
    protected function getRouter()
    {
        return $this->app['router'];
    }

    /**
     * Returns the config path for
     */
    private function getConfigPath()
    {
        return config_path('laramultidbtenant.php');
    }

    /**
     * Returns the migrations path for
     */
    private function getMigrationsPath()
    {
        return database_path('migrations');
    }
}
<?php
namespace Former;

use Former\Populator;
use Former\Former;
use Illuminate\Config\FileLoader as ConfigLoader;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

/**
 * Register the Former package with the Laravel framework
 */
class FormerServiceProvider extends ServiceProvider
{

  /**
   * Register Former's package with Laravel
   *
   * @return void
   */
  public function register()
  {
    // ...
  }

  /**
   * Boot Former and its classes
   *
   * @return void
   */
  public function boot()
  {
    $this->app = static::make($this->app);
  }

  /**
   * Get the services provided by the provider.
   *
   * @return array
   */
  public function provides()
  {
    return array('former');
  }

  ////////////////////////////////////////////////////////////////////
  /////////////////////////// CLASS BINDINGS /////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Create a Former container
   *
   * @param  Container $app
   *
   * @return Container
   */
  public static function make($app = null)
  {
    if (!$app) {
      $app = new Container;
    }

    // Bind classes to container
    $provider = new static($app);
    $app      = $provider->bindCoreClasses($app);
    $app      = $provider->bindFormer($app);

    return $app;
  }

  /**
   * Bind legacy classes for Laravel 3
   *
   * @param  Container $app
   *
   * @return Container
   */
  public function bindLegacyClasses(Container $app)
  {
    $app->bind('url', function ($app) {
      return new Legacy\Redirector('Laravel\URL');
    });

    $app->bind('session', function ($app) {
      return new Legacy\Session;
    });

    $app->bind('config', function ($app) {
      return new Legacy\Config;
    });

    $app->bind('request', function ($app) {
      return new Legacy\Redirector('Laravel\Input');
    });

    $app->bind('translator', function ($app) {
      return new Legacy\Translator;
    });

    return $app;
  }

  /**
   * Bind the core classes to the Container
   *
   * @param  Container $app
   *
   * @return Container
   */
  public function bindCoreClasses(Container $app)
  {
    // Redirect to Legacy classes
    if (class_exists('Laravel\Input')) {
      return $this->bindLegacyClasses($app);
    }

    // Core classes
    //////////////////////////////////////////////////////////////////

    $app->bindIf('files', 'Illuminate\Filesystem\Filesystem');
    $app->bindIf('url', 'Illuminate\Routing\UrlGenerator');

    // Session and request
    //////////////////////////////////////////////////////////////////

    $app->bindIf('session.manager', function ($app) {
      return new SessionManager($app);
    });

    $app->bindIf('session', function ($app) {
      return $app['session.manager']->driver('array');
    }, true);

    $app->bindIf('request', function ($app) {
      $request = Request::createFromGlobals();
      $request->setSessionStore($app['session']);

      return $request;
    }, true);

    // Config
    //////////////////////////////////////////////////////////////////

    $app->bindIf('config', function ($app) {
      $fileloader = new ConfigLoader($app['files'], __DIR__.'/../config');

      return new Repository($fileloader, 'config');
    }, true);

    // Add config namespace
    $app['config']->package('anahkiasen/former', __DIR__.'/../config');

    // Localization
    //////////////////////////////////////////////////////////////////

    $app->bindIf('translation.loader', function ($app) {
      return new FileLoader($app['files'], 'src/config');
    });

    $app->bindIf('translator', function ($app) {
      $loader = new FileLoader($app['files'], 'lang');

      return new Translator($loader, 'fr');
    });

    return $app;
  }

  /**
   * Bind Former classes to the container
   *
   * @param  Container $app
   *
   * @return Container
   */
  public function bindFormer(Container $app)
  {
    // Get framework to use
    $framework = $app['config']->get('former::framework');

    $frameworkClass = '\Former\Framework\\'.$framework;
    $app->bind('former.framework', function ($app) use ($frameworkClass) {
      return new $frameworkClass($app);
    });

    $app->bindShared('former.populator', function ($app) {
      return new Populator;
    });

    $app->bindShared('former', function ($app) {
      return new Former($app);
    });

    Helpers::setApp($app);

    return $app;
  }
}

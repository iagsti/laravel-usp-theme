<?php

namespace Uspdev\UspTheme;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Factory $view, Dispatcher $events, Repository $config)
    {
        $this->loadViews();
        $this->loadTranslations();
        $this->publishAssets();
        $this->publishConfig();

        $displayTimeout = $this->resolveMensagensDisplayTimeout();

        // config
        View::share('title', config('laravel-usp-theme.title'));
        View::share('container', config('laravel-usp-theme.container') ?? 'container-fluid');
        View::share('menu', config('laravel-usp-theme.menu'));
        View::share('right_menu', config('laravel-usp-theme.right_menu'));
        View::share('app_url', config('laravel-usp-theme.app_url'));
        View::share('logout_method', config('laravel-usp-theme.logout_method'));
        View::share('login_url', config('laravel-usp-theme.login_url'));
        View::share('logout_url', config('laravel-usp-theme.logout_url'));
        View::share('cadastrosAuxiliaresMensagensIntegracao', $this->resolveMensagensIntegracaoEnabled());
        View::share('cadastrosAuxiliaresMensagensTimeout', $displayTimeout);
        View::share('cadastrosAuxiliaresMensagensEndpoint', $this->resolveMensagensEndpointUrl());
        View::share('cadastrosAuxiliaresMensagensLimite', $this->resolveMensagensLimite());
        View::share('cadastrosAuxiliaresMensagensSistema', $this->resolveMensagensSistema());
        View::share('cadastrosAuxiliaresMensagensRefresh', $this->resolveMensagensRefresh());
        View::share('cadastrosAuxiliaresMensagens', $this->fetchCadastrosAuxiliaresMensagens());

        # skin na sessão com fallback para o config
        # https://stackoverflow.com/questions/34577946/how-to-retrieve-session-data-in-service-providers-in-laravel
        view()->composer('*', function ($view) {
            $view->with('skin', session(config('laravel-usp-theme.session_key') . '.skin') ?? config('laravel-usp-theme.skin'));
        });

    }

    private function fetchCadastrosAuxiliaresMensagens(): Collection
    {
        $enabled = $this->resolveMensagensIntegracaoEnabled();
        $endpoint = $this->resolveMensagensEndpointUrl();
        if (!$enabled || $endpoint === '' || app()->runningInConsole()) {
            return collect();
        }

        if (app()->bound('request')) {
            $request = app('request');

            if ($request->is('api/*') || $request->headers->get('X-UspTheme-Mensagens-Internal') === '1') {
                return collect();
            }
        }

        $limite = max(1, $this->resolveMensagensLimite());
        $sistema = $this->resolveMensagensSistema();
        $requestTimeout = 5;

        try {
            $queryString = parse_url($endpoint, PHP_URL_QUERY) ?: '';
            parse_str($queryString, $queryParams);
            $baseUrl = $queryString === '' ? $endpoint : str_replace('?' . $queryString, '', $endpoint);

            if (!array_key_exists('limite', $queryParams)) {
                $queryParams['limite'] = $limite;
            }

            if (!array_key_exists('sistema', $queryParams) && $sistema !== '') {
                $queryParams['sistema'] = $sistema;
            }

            $url = $baseUrl;

            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            if ($this->shouldUseInternalRequest($url)) {
                return $this->fetchViaInternalRequest($url);
            }

            $response = Http::acceptJson()
                ->timeout($requestTimeout)
                ->withHeaders([
                    'X-UspTheme-Mensagens-Internal' => '1',
                ])
                ->get($url);

            if ($response->ok() && is_array($response->json())) {
                return collect($response->json())->values();
            }
        } catch (\Throwable $exception) {
            return collect();
        }

        return collect();
    }

    private function shouldUseInternalRequest(string $url): bool
    {
        if (!app()->bound('request')) {
            return false;
        }

        $endpointHost = parse_url($url, PHP_URL_HOST);
        $currentHost = app('request')->getHost();

        return !empty($endpointHost) && $endpointHost === $currentHost;
    }

    private function fetchViaInternalRequest(string $url): Collection
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/api/mensagens';
        $query = parse_url($url, PHP_URL_QUERY) ?: '';
        $uri = $path . ($query ? ('?' . $query) : '');

        $internalRequest = Request::create($uri, 'GET', [], [], [], [
            'HTTP_X_UspTheme_Mensagens_Internal' => '1',
        ]);

        $response = app()->handle($internalRequest);

        if ($response->getStatusCode() !== 200) {
            return collect();
        }

        $payload = json_decode($response->getContent(), true);

        return is_array($payload) ? collect($payload)->values() : collect();
    }

    private function resolveMensagensDisplayTimeout(): int
    {
        $raw = config('laravel-usp-theme.cadastros_auxiliares_mensagens_timeout');

        if ($raw === null || trim((string) $raw) === '') {
            $raw = env('CADASTROS_AUXILIARES_MENSAGENS_TIMEOUT');
        }

        if ($raw === null || trim((string) $raw) === '') {
            return 0;
        }

        $seconds = (int) $raw;

        return $seconds > 0 ? $seconds : 0;
    }

    private function resolveMensagensIntegracaoEnabled(): bool
    {
        $value = config('laravel-usp-theme.cadastros_auxiliares_mensagens_integracao');

        if ($value === null || trim((string) $value) === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function resolveMensagensEndpointUrl(): string
    {
        $value = config('laravel-usp-theme.cadastros_auxiliares_mensagens_endpoint_url');

        if ($value === null || $value === '') {
            $value = env('CADASTROS_AUXILIARES_MENSAGENS_ENDPOINT_URL', '');
        }

        return (string) $value;
    }

    private function resolveMensagensLimite(): int
    {
        $value = config('laravel-usp-theme.cadastros_auxiliares_mensagens_limite');

        if ($value === null || $value === '') {
            $value = env('CADASTROS_AUXILIARES_MENSAGENS_LIMITE', 5);
        }

        return (int) $value;
    }

    private function resolveMensagensSistema(): string
    {
        $value = config('laravel-usp-theme.cadastros_auxiliares_mensagens_sistema');

        if ($value === null || $value === '') {
            $value = env('CADASTROS_AUXILIARES_SISTEMA_NAME', '');
        }

        return mb_strtolower(trim((string) $value));
    }

    private function resolveMensagensRefresh(): int
    {
        $value = config('laravel-usp-theme.cadastros_auxiliares_mensagens_refresh');

        if ($value === null || $value === '') {
            $value = env('CADASTROS_AUXILIARES_MENSAGENS_REFRESH', 30);
        }

        $seconds = (int) $value;

        return $seconds > 0 ? $seconds : 30;
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // configs
        $this->mergeConfigFrom($this->packagePath('config/skins.php'), 'laravel-usp-theme');
        $sistemas = require $this->packagePath('config/laravel-usp-theme-sistemas.php');
        $config = $this->app['config']->get('laravel-usp-theme', []);
        $this->app['config']->set('laravel-usp-theme', array_merge($sistemas, $config));

        // Facade
        $this->app->bind('uspTheme', function ($app) {
            return new UspTheme();
        });
    }

    private function packagePath($path)
    {
        return __DIR__ . "/../$path";
    }

    private function loadViews()
    {
        $viewsPath = $this->packagePath('resources/views');
        $this->loadViewsFrom($viewsPath, 'laravel-usp-theme');
        $this->publishes([
            $viewsPath => base_path('resources/views/vendor/laravel-usp-theme'),
        ], 'views');
    }

    private function loadTranslations()
    {
        $translationsPath = $this->packagePath('resources/lang');
        $this->loadTranslationsFrom($translationsPath, 'laravel-usp-theme');
        $this->publishes([
            $translationsPath => base_path('resources/lang/vendor/laravel-usp-theme'),
        ], 'translations');
    }

    private function publishAssets()
    {
        $this->publishes([
            $this->packagePath('resources/assets') => public_path('vendor/laravel-usp-theme'),
        ], 'assets');
    }

    private function publishConfig()
    {
        $configPath = $this->packagePath('config/laravel-usp-theme.php');
        $this->publishes([
            $configPath => config_path('laravel-usp-theme.php'),
        ], 'config');
    }

}

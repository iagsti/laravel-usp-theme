<?php

namespace Uspdev\UspTheme\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CadastrosAuxiliaresMensagensService
{
    public function fetch(): Collection
    {
        $enabled = filter_var(
            config('laravel-usp-theme.cadastros_auxiliares_mensagens_integracao', false),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? false;
        $endpoint = trim((string) config('laravel-usp-theme.cadastros_auxiliares_mensagens_endpoint_url', ''));

        if (!$enabled || $endpoint === '' || app()->runningInConsole()) {
            return collect();
        }

        if (app()->bound('request')) {
            $request = app('request');

            if ($request->is('api/*') || $request->headers->get('X-UspTheme-Mensagens-Internal') === '1') {
                return collect();
            }
        }

        $limite = max(1, (int) config('laravel-usp-theme.cadastros_auxiliares_mensagens_limite', 5));
        $sistema = mb_strtolower(trim((string) config('laravel-usp-theme.cadastros_auxiliares_mensagens_sistema', '')));
        $password = trim((string) config('laravel-usp-theme.cadastros_auxiliares_password', ''));
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

            $headers = [
                'X-UspTheme-Mensagens-Internal' => '1',
            ];

            if ($password !== '') {
                $headers['X-Cadastros-Auxiliares-Password'] = $password;
            }

            $response = Http::acceptJson()
                ->timeout($requestTimeout)
                ->withHeaders($headers)
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
}

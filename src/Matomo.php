<?php

namespace Aimeos\AnalyticsBridge\Drivers;

use Aimeos\AnalyticsBridge\Contracts\Driver;
use Illuminate\Support\Facades\Http;


class Matomo implements Driver
{
    private ?string $siteId;
    private ?string $token;
    private ?string $url;


    public function __construct(array $config = [])
    {
        $this->siteId = $config['siteid'] ?? null;
        $this->token = $config['token'] ?? null;
        $this->url = $config['url'] ?? null;
    }


    public function all(string $url, int $days = 30): ?array
    {
        if(!$this->url || !$this->token) {
            return null;
        }

        $shared = [
            'module'     => 'API',
            'method'     => 'API.getBulkRequest',
            'token_auth' => $this->token,
            'idSite'     => $this->siteId,
            'segment'    => "pageUrl==$url",
            'date'       => "last{$days}",
            'format'     => 'json',
            'flat'       => 1,
        ];

        $response = Http::get($this->url, $shared + [
            'urls' => [
                'method=Actions.getPageUrls&period=day',
                'method=VisitsSummary.get&period=day',
                'method=Referrers.getWebsites&period=range',
                'method=UserCountry.getCountry&period=range'
            ],
        ]);

        $data = [];

        if(!$response->ok()) {
            throw new Error( $response->getBody() );
        }

        $data = $response->json();

        return [
            'views' => $this->mapDaily($response[0], 'nb_hits'),
            'visits' => $this->mapDaily($response[1], 'nb_visits'),
            'durations' => $this->mapDaily($response[1], 'avg_time_on_site'),
            'referrers' => $this->mapAggregate($response[2], 'url', 'nb_visits'),
            'countries' => $this->mapAggregate($response[3], 'label', 'nb_visits'),
        ];
    }


    public function views(string $url, int $days = 30): ?array
    {
        if(!$this->url || !$this->token) {
            return null;
        }

        $response = Http::get($this->url, [
            'module'     => 'API',
            'method'     => 'Actions.getPageUrls',
            'idSite'     => $this->siteId,
            'token_auth' => $this->token,
            'segment'    => "pageUrl==$url",
            'date'       => "last{$days}",
            'format'     => 'json',
            'period'     => 'day',
            'flat'       => 1,
        ]);

        return $this->mapDaily($response->json(), 'nb_hits');
    }


    public function visits(string $url, int $days = 30): ?array
    {
        if(!$this->url || !$this->token) {
            return null;
        }

        $response = Http::get($this->url, [
            'module'     => 'API',
            'method'     => 'VisitsSummary.get',
            'idSite'     => $this->siteId,
            'token_auth' => $this->token,
            'segment'    => "pageUrl==$url",
            'date'       => "last{$days}",
            'format'     => 'json',
            'period'     => 'day',
        ]);

        return $this->mapDaily($response->json(), 'nb_visits');
    }


    public function durations(string $url, int $days = 30): ?array
    {
        if(!$this->url || !$this->token) {
            return null;
        }

        $response = Http::get($this->url, [
            'module'     => 'API',
            'method'     => 'VisitsSummary.get',
            'idSite'     => $this->siteId,
            'token_auth' => $this->token,
            'segment'    => "pageUrl==$url",
            'date'       => "last{$days}",
            'format'     => 'json',
            'period'     => 'day',
        ]);

        return $this->mapDaily($response->json(), 'avg_time_on_site');
    }


    public function countries(string $url, int $days = 30): ?array
    {
        if(!$this->url || !$this->token) {
            return null;
        }

        $response = Http::get($this->url, [
            'module'     => 'API',
            'method'     => 'UserCountry.getCountry',
            'idSite'     => $this->siteId,
            'token_auth' => $this->token,
            'segment'    => "pageUrl==$url",
            'date'       => "last{$days}",
            'format'     => 'json',
            'period'     => 'day',
        ]);

        return $this->mapAggregate($response->json(), 'label', 'nb_visits');
    }


    public function referrers(string $url, int $days = 30): ?array
    {
        if(!$this->url || !$this->token) {
            return null;
        }

        $response = Http::get($this->url, [
            'module'     => 'API',
            'method'     => 'Referrers.getWebsites',
            'idSite'     => $this->siteId,
            'token_auth' => $this->token,
            'segment'    => "pageUrl==$url",
            'date'       => "last{$days}",
            'format'     => 'json',
            'period'     => 'day',
        ]);

        return $this->mapAggregate($response->json(), 'label', 'nb_visits');
    }


    /**
     * Map daily responses into [ ['key'=>date, 'value'=>count], ... ]
     */
    protected function mapDaily(array $response, string $field): array
    {
        return collect($response)
            ->map(fn($items, $date) => [
                'key' => $date,
                'value' => collect($items)->sum($field),
            ])
            ->values() // reindex numerically
            ->all();
    }


    /**
     * Map aggregate responses into [ ['key'=>label, 'value'=>count], ... ]
     */
    protected function mapAggregate(array $response, string $labelField, string $valueField): array
    {
        return collect($response)
            ->map(fn($item) => [
                'key' => $item[$labelField],
                'value' => $item[$valueField],
            ])
            ->all();
    }
}

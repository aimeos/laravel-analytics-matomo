<?php

namespace Aimeos\AnalyticsBridge\Drivers;

use Aimeos\AnalyticsBridge\Contracts\Driver;
use Illuminate\Support\Facades\Http;


class Matomo implements Driver
{
    private array $types = ['views', 'visits', 'durations', 'conversions', 'countries', 'referrers'];
    private ?string $siteId;
    private ?string $token;
    private ?string $url;


    public function __construct(array $config = [])
    {
        $this->siteId = $config['siteid'] ?? null;
        $this->token = $config['token'] ?? null;
        $this->url = $config['url'] ?? null;
    }


    public function stats(string $url, int $days = 30): ?array
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
            'date'       => "previous{$days}",
            'format'     => 'json',
        ];

        $urls = [];

        if(in_array('views', $this->types)) {
            $urls[] = 'method=Actions.getPageUrls&period=day';
        }

        if(in_array('visits', $this->types) || in_array('durations', $this->types)) {
            $urls[] = 'method=VisitsSummary.get&period=day&flat=1';
        }

        if(in_array('conversions', $this->types)) {
            $urls[] = 'method=Goals.get&period=day';
        }

        if(in_array('countries', $this->types)) {
            $urls[] = 'method=UserCountry.getCountry&period=range';
        }

        if(in_array('referrers', $this->types)) {
            $urls[] = 'method=Referrers.getReferrerType&expanded=1&period=range';
        }

        $response = Http::get($this->url, $shared + ['urls' => $urls]);

        if(!$response->ok()) {
            throw new \RuntimeException( $response->body() );
        }

        $data = $response->json();
        $result = [];

        if(in_array('views', $this->types)) {
            $result['views'] = $this->mapDaily(array_shift($data) ?? [], 'nb_hits');
        }

        if(in_array('visits', $this->types) || in_array('durations', $this->types)) {
            $entries = array_shift($data) ?? [];

            if(in_array('visits', $this->types)) {
                $result['visits'] = $this->mapDaily($entries, 'nb_visits');
            }

            if(in_array('durations', $this->types)) {
                $result['durations'] = $this->mapDaily($entries, 'avg_time_on_site');
            }
        }

        if(in_array('conversions', $this->types)) {
            $result['conversions'] = $this->mapDaily(array_shift($data) ?? [], 'nb_conversions');
        }

        if(in_array('countries', $this->types)) {
            $result['countries'] = $this->mapAggregate(array_shift($data) ?? []);
        }

        if(in_array('referrers', $this->types)) {
            $result['referrers'] = $this->mapReferrers(array_shift($data) ?? []);
        }

        return $result;
    }


    public function types(array $types): Driver
    {
        $this->types = $types;
        return $this;
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
    protected function mapAggregate(array $response): array
    {
        return collect($response)
            ->map(fn($item) => [
                'key' => $item['label'],
                'value' => $item['nb_visits'],
            ])
            ->all();
    }


    /**
     * Map aggregate responses into [ ['key'=>label, 'value'=>count, 'rows'=>[]], ... ]
     */
    protected function mapReferrers(array $response): array
    {
        return collect($response)
            ->map(fn($item) => [
                'key' => $item['label'],
                'value' => $item['nb_visits'],
                'rows' => collect($item['subtable'] ?? [])->map(fn($row) => [
                    'key' => $row['label'],
                    'value' => $row['nb_visits'],
                ])->all()
            ])
            ->all();
    }
}

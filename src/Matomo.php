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


    public function stats(string $url, int $days = 30, array $types = []): ?array
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
            'flat'       => 1,
        ];

        $types = empty($types) ? ['views', 'visits', 'durations', 'countries', 'referrers', 'referrertypes'] : $types;
        $urls = [];

        if(in_array('views', $types)) {
            $urls[] = 'method=Actions.getPageUrls&period=day';
        }

        if(in_array('visits', $types) || in_array('durations', $types)) {
            $urls[] = 'method=VisitsSummary.get&period=day';
        }

        if(in_array('countries', $types)) {
            $urls[] = 'method=UserCountry.getCountry&period=range';
        }

        if(in_array('referrers', $types)) {
            $urls[] = 'method=Referrers.getWebsites&period=range';
        }

        if(in_array('referrertypes', $types)) {
            $urls[] = 'method=Referrers.getReferrerType&period=range';
        }

        $response = Http::get($this->url, $shared + ['urls' => $urls]);

        if(!$response->ok()) {
            throw new \RuntimeException( $response->body() );
        }

        $data = $response->json();
        $result = [];

        if(in_array('views', $types)) {
            $result['views'] = $this->mapDaily(array_shift($data) ?? [], 'nb_hits');
        }

        if(in_array('visits', $types) || in_array('durations', $types)) {
            $entries = array_shift($data) ?? [];

            if(in_array('visits', $types)) {
                $result['visits'] = $this->mapDaily($entries, 'nb_visits');
            }

            if(in_array('durations', $types)) {
                $result['durations'] = $this->mapDaily($entries, 'avg_time_on_site');
            }
        }

        if(in_array('countries', $types)) {
            $result['countries'] = $this->mapAggregate(array_shift($data) ?? []);
        }

        if(in_array('referrers', $types)) {
            $result['referrers'] = $this->mapReferrers(array_shift($data) ?? []);
        }

        if(in_array('referrertypes', $types)) {
            $result['referrertypes'] = $this->mapAggregate(array_shift($data) ?? []);
        }

        return $result;
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
     * Map aggregate responses into [ ['key'=>label, 'value'=>count], ... ]
     */
    protected function mapReferrers(array $response): array
    {
        $build = function($item) {
            $path = $item['Referrers_WebsitePage'] != 'index' ? $item['Referrers_WebsitePage'] : '';
            return 'https://' . $item['Referrers_Website'] . '/' . $path;
        };

        return collect($response)
            ->map(fn($item) => [
                'key' => $item['url'] ?: $build($item),
                'value' => $item['nb_visits'],
            ])
            ->all();
    }
}

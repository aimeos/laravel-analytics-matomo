<?php

namespace Aimeos\AnalyticsBridge\Drivers;

use Aimeos\AnalyticsBridge\Contracts\Driver;
use VisualAppeal\Matomo as MatomoClient;


class Matomo implements Driver
{
    private MatomoClient $client;


    public function __construct(array $config = [])
    {
        $url = $config['url'] ?? '';
        $token = $config['token'] ?? '';
        $siteId = (int) ($config['siteid'] ?? 1);

        $this->client = new MatomoClient($url, $token, $siteId, MatomoClient::FORMAT_JSON);
    }


    public function all(string $url, int $days = 30): array
    {
        // Methods to call
        $methods = [
            'Actions.getPageUrls',
            'VisitsSummary.get',
            'Referrers.getWebsites',
            'UserCountry.getCountry',
        ];

        // Shared parameters for all methods
        $optional = [
            'period'  => 'day',
            'date'    => "last{$days}",
            'segment' => "pageUrl==$url",
            'flat'    => 1,
        ];

        // Execute bulk request
        $responses = $this->client->getBulkRequest($methods, $optional);

        return [
            'pageViews'     => $this->mapDaily($responses[0], 'nb_hits'),
            'visits'        => $this->mapDaily($responses[1], 'nb_visits'),
            'visitDuration' => $this->mapDaily($responses[1], 'avg_time_on_site'),
            'referrers'     => $this->mapAggregate($responses[2], 'label', 'url', 'nb_visits'),
            'countries'     => $this->mapAggregate($responses[3], 'label', 'label', 'nb_visits'),
        ];
    }


    public function pageViews(string $url, int $days = 30): array
    {
        $segment = "pageUrl==$url";
        $response = $this->client->request('Actions.getPageUrls', [
            'period' => 'day',
            'date'   => "last{$days}",
            'segment'=> $segment,
        ]);

        return $this->mapDaily($response, 'nb_hits');
    }


    public function visits(string $url, int $days = 30): array
    {
        $segment = "pageUrl==$url";
        $response = $this->client->getVisits($segment, [
            'period' => 'day',
            'date'   => "last{$days}",
        ]);

        return $this->mapDaily($response, 'nb_visits');
    }


    public function visitDurations(string $url, int $days = 30): array
    {
        $segment = "pageUrl==$url";
        $response = $this->client->getVisits($segment, [
            'period' => 'day',
            'date'   => "last{$days}",
        ]);

        return $this->mapDaily($response, 'avg_time_on_site');
    }


    public function countries(string $url, int $days = 30): array
    {
        $segment = "pageUrl==$url";
        $response = $this->client->getCountries($segment, [
            'period' => 'range',
            'date'   => "last{$days}",
        ]);

        return $this->mapAggregate($response, 'label', 'nb_visits');
    }


    public function referrers(string $url, int $days = 30): array
    {
        $segment = "pageUrl==$url";
        $response = $this->client->getWebsites($segment, [
            'period' => 'range',
            'date'   => "last{$days}",
        ]);

        return $this->mapAggregate($response, 'label', 'nb_visits');
    }


    /**
     * Map daily responses into [ ['key'=>date, 'value'=>count], ... ]
     */
    protected function mapDaily(object $response, string $field): array
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
    protected function mapAggregate(object $response, string $groupField, string $labelField, string $valueField): array
    {
        return collect($response)
            ->flatten(1) // flatten one level: remove dates
            ->groupBy($groupField)
            ->map(fn($group) => [
                'key' => $group->first()?->$labelField ?? $group->first()?->$groupField,
                'value' => $group->sum($valueField),
            ])
            ->values() // reset numeric keys
            ->all();
    }
}

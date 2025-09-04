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
            'UserCountry.getCountry',
            'Referrers.getWebsites',
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
            'pageViews'     => $this->mapDaily((array) $responses[0], 'nb_hits'),
            'visits'        => $this->mapDaily((array) $responses[1], 'nb_visits'),
            'visitDuration' => $this->mapDaily((array) $responses[1], 'avg_time_on_site'),
            'countries'     => $this->mapAggregate((array) $responses[2], 'label', 'nb_visits'),
            'referrers'     => $this->mapAggregate((array) $responses[3], 'label', 'nb_visits'),
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
    protected function mapDaily(array $response, string $field): array
    {
        $data = [];
        foreach ($response as $date => $row) {
            $data[] = [
                'key' => $date,
                'value' => $row[$field] ?? 0,
            ];
        }
        return $data;
    }


    /**
     * Map aggregate responses into [ ['key'=>label, 'value'=>count], ... ]
     */
    protected function mapAggregate(array $response, string $labelField, string $valueField): array
    {
        $data = [];
        foreach ($response as $row) {
            if(!empty($label = $row[$labelField] ?? null)) {
                $data[] = [
                    'key' => $label,
                    'value' => $row[$valueField] ?? 0,
                ];
            }
        }
        return $data;
    }
}

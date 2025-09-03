<?php

namespace Aimeos\AnalyticsBridge\Drivers;

use Aimeos\AnalyticsBridge\Drivers\Driver;
use VisualAppeal\Matomo as MatomoClient;


class Matomo implements Driver
{
    protected MatomoClient $client;


    public function __construct(array $config = [])
    {
        $url = rtrim($config['url'] ?? '', '/');
        $token = $config['token'] ?? '';
        $siteId = (int) ($config['siteid'] ?? 1);

        $this->client = new MatomoClient($url, $token, $siteId);
    }


    public function pageViews(string $path, int $days = 30): array
    {
        $response = $this->client->get('Actions.getPageUrls', [
            'period' => 'day',
            'date'   => "last{$days}",
            'segment'=> "pageUrl==$path",
        ]);

        return $this->mapDaily($response, 'nb_hits');
    }


    public function visits(string $path, int $days = 30): array
    {
        $response = $this->client->get('VisitsSummary.get', [
            'period' => 'day',
            'date'   => "last{$days}",
            'segment'=> "pageUrl==$path",
        ]);

        return $this->mapDaily($response, 'nb_visits');
    }


    public function visitDurations(string $path, int $days = 30): array
    {
        $response = $this->client->get('VisitsSummary.get', [
            'period' => 'day',
            'date'   => "last{$days}",
            'segment'=> "pageUrl==$path",
        ]);

        return $this->mapDaily($response, 'avg_time_on_site');
    }


    public function countries(string $path, int $days = 30): array
    {
        $response = $this->client->get('UserCountry.getCountry', [
            'period' => 'range',
            'date'   => "last{$days}",
            'segment'=> "pageUrl==$path",
        ]);

        return $this->mapAggregate($response, 'label', 'nb_visits');
    }


    public function referrers(string $path, int $days = 30): array
    {
        $response = $this->client->get('Referrers.getWebsites', [
            'period' => 'range',
            'date'   => "last{$days}",
            'segment'=> "pageUrl==$path",
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
            $data[] = [
                'key' => $row[$labelField] ?? '',
                'value' => $row[$valueField] ?? 0,
            ];
        }
        return $data;
    }
}

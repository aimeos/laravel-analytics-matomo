<?php

namespace Aimeos\AnalyticsBridge\Drivers;

use Aimeos\AnalyticsBridge\Drivers\Driver;
use Matomo\API\Request;


class Matomo implements Driver
{
    protected string $url;
    protected string $token;
    protected int $siteId;


    public function __construct(array $config = [])
    {
        $this->url = $config['url'] ?? '';
        $this->token = $config['token'] ?? '';
        $this->siteId = $config['siteid'] ?? 1;
    }


    public function pageViews(string $path, int $days = 30): array
    {
        $response = $this->query('Actions.getPageUrls', "pageUrl==$path", $days);
        $data = [];

        foreach ($response as $row) {
            $data[$row['date']] = $row['nb_hits'] ?? 0;
        }
        return $data;
    }


    public function visits(string $path, int $days = 30): array
    {
        $response = $this->query('VisitsSummary.get', "pageUrl==$path", $days);
        $data = [];

        foreach ($response as $row) {
            $data[$row['date']] = $row['nb_visits'] ?? 0;
        }
        return $data;
    }


    public function visitDurations(string $path, int $days = 30): array
    {
        $response = $this->query('VisitsSummary.getAverageVisitDuration', "pageUrl==$path", $days);
        $data = [];

        foreach ($response as $row) {
            $data[$row['date']] = $row['avg_time_on_site'] ?? 0;
        }
        return $data;
    }


    public function countries(string $path, int $days = 30): array
    {
        return $this->query('UserCountry.getCountry', "pageUrl==$path", $days);
    }


    public function referrers(string $path, int $days = 30): array
    {
        return $this->query('Referrers.getWebsites', "pageUrl==$path", $days);
    }


    protected function query(string $method, string $segment, int $days): array
    {
        $request = new Request([
            'module' => 'API',
            'method' => $method,
            'idSite' => $this->siteId,
            'period' => 'day',
            'date' => "last{$days}",
            'token_auth' => $this->token,
            'segment' => $segment,
            'format' => 'JSON',
        ]);

        return $request->process();
    }
}

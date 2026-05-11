<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Probes Fleeti's API for any endpoint that returns POIs / places / geofences.
 * The integrated FleetiService only calls /v1/Asset/* — Fleeti may expose more.
 *
 * Usage:
 *   php artisan fleeti:discover-places
 *   php artisan fleeti:discover-places --customer=YOUR_CUSTOMER_REF
 *
 * Non-destructive: only sends GETs, prints status + first few results.
 */
class DiscoverFleetiPlaces extends Command
{
    protected $signature = 'fleeti:discover-places
        {--customer= : Optional CustomerReference query parameter}';

    protected $description = 'Probe Fleeti API for POI/Geofence/Place endpoints';

    public function handle(): int
    {
        $base = rtrim((string) config('services.fleeti.base_url', 'https://api.fleeti.co'), '/');
        $key = (string) config('services.fleeti.api_key');
        if ($key === '') {
            $this->error('FLEETI_API_KEY is not configured.');
            return self::FAILURE;
        }

        $headers = [
            'x-api-key' => $key,
            'Accept' => 'application/json',
        ];
        $bearer = (string) config('services.fleeti.bearer_token', '');
        if ($bearer !== '') {
            $headers['Authorization'] = 'Bearer ' . $bearer;
        }

        $customer = (string) ($this->option('customer') ?? '');

        // Common endpoint patterns used by fleet APIs. We only need ONE to work.
        $candidates = [
            '/v1/Place/Search',
            '/v1/Places/Search',
            '/v1/POI/Search',
            '/v1/Pois/Search',
            '/v1/Geofence/Search',
            '/v1/Geofences/Search',
            '/v1/Zone/Search',
            '/v1/Zones/Search',
            '/v1/Site/Search',
            '/v1/Sites/Search',
            '/v1/Location/Search',
            '/v1/Locations/Search',
            '/v1/Customer/Place/Search',
            '/v1/Customer/Places',
            '/v1/Customer/POI',
        ];

        $this->info("Base URL: {$base}");
        $this->info('Trying ' . count($candidates) . ' candidate endpoints...');
        $this->newLine();

        $hits = 0;
        foreach ($candidates as $path) {
            $query = ['Skip' => 0, 'Take' => 5];
            if ($customer !== '') {
                $query['CustomerReference'] = $customer;
            }

            try {
                $resp = Http::timeout(15)
                    ->withHeaders($headers)
                    ->get($base . $path, $query);

                $status = $resp->status();
                $tag = $resp->successful() ? '<fg=green>OK</>' : ($status === 404 ? '<fg=gray>404</>' : "<fg=yellow>{$status}</>");
                $this->line(" {$tag}  {$path}");

                if ($resp->successful()) {
                    $hits++;
                    $body = $resp->json();
                    $sample = $this->summarize($body);
                    $this->line("        " . str_replace("\n", "\n        ", $sample));
                    $this->newLine();
                }
            } catch (\Throwable $e) {
                $this->line(" <fg=red>ERR</>  {$path}  ({$e->getMessage()})");
            }
        }

        $this->newLine();
        if ($hits === 0) {
            $this->warn('No probed endpoint returned 200. Either Fleeti does not expose places via REST under these names,');
            $this->warn('or your API key lacks permission. Check the Fleeti dashboard / contact Fleeti support for the right URL.');
            return self::FAILURE;
        }

        $this->info("{$hits} endpoint(s) returned data — copy the path that fits and I can write the importer.");
        return self::SUCCESS;
    }

    private function summarize(mixed $body): string
    {
        if (is_array($body)) {
            $keys = array_slice(array_keys($body), 0, 8);
            $first = null;
            foreach (['results', 'result', 'data', 'items'] as $k) {
                if (isset($body[$k]) && is_array($body[$k]) && !empty($body[$k])) {
                    $first = $body[$k][0] ?? null;
                    break;
                }
            }
            if ($first === null && !empty($body)) {
                $first = is_array(reset($body)) ? reset($body) : $body;
            }
            return 'top-level keys: [' . implode(', ', $keys) . "]\n"
                . 'first item: ' . substr(json_encode($first, JSON_UNESCAPED_UNICODE), 0, 400);
        }
        return substr((string) json_encode($body), 0, 400);
    }
}

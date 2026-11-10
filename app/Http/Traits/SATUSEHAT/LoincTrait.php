<?php

namespace App\Http\Traits\SATUSEHAT;

use Illuminate\Support\Facades\Http;

trait LoincTrait
{
    protected string $loincFhirBaseUrl;

    public function initializeLoincFhir(): void
    {
        $this->loincFhirBaseUrl = config('txfhir.base_url', 'http://tx.fhir.org/r4');
    }

    /**
     * Search LOINC codes via FHIR ValueSet/$expand on tx.fhir.org
     * Uses the observation-code ValueSet which includes LOINC
     */
    public function searchLoincConcepts(string $term, int $limit = 15): array
    {
        $url = "{$this->loincFhirBaseUrl}/ValueSet/\$expand";

        $response = Http::withHeaders([
            'Accept' => 'application/fhir+json',
        ])->timeout(10)->get($url, [
            'url'    => 'http://hl7.org/fhir/ValueSet/observation-codes',
            'filter' => $term,
            'count'  => $limit,
            'offset' => 0,
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json()['expansion']['contains'] ?? [];
    }

    /**
     * Lookup single LOINC code
     */
    public function lookupLoincCode(string $code): ?array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/fhir+json',
        ])->timeout(10)->get("{$this->loincFhirBaseUrl}/CodeSystem/\$lookup", [
            'system' => 'http://loinc.org',
            'code'   => $code,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $params = $response->json()['parameter'] ?? [];
        $display = null;

        foreach ($params as $param) {
            if (($param['name'] ?? '') === 'display') {
                $display = $param['valueString'] ?? null;
                break;
            }
        }

        return $display ? ['code' => $code, 'display' => $display] : null;
    }

    /**
     * Periksa kode ke LOINC. Beda dari lookupLoincCode(): kode tak dikenal dibedakan dari server gagal,
     * supaya gangguan tx.fhir.org tidak terbaca sebagai kode salah.
     *
     * @return array{status: 'ada'|'tidak_ada'|'gagal', display: ?string}
     */
    protected function periksaKodeLoinc(string $code): array
    {
        try {
            $response = Http::withHeaders(['Accept' => 'application/fhir+json'])
                ->timeout(8)
                ->get("{$this->loincFhirBaseUrl}/CodeSystem/\$lookup", ['system' => 'http://loinc.org', 'code' => $code]);
        } catch (\Throwable $e) {
            return ['status' => 'gagal', 'display' => null];
        }

        if (in_array($response->status(), [400, 404, 422], true)) {
            return ['status' => 'tidak_ada', 'display' => null];
        }

        if (! $response->successful()) {
            return ['status' => 'gagal', 'display' => null];
        }

        $display = collect($response->json('parameter') ?? [])->firstWhere('name', 'display')['valueString'] ?? null;

        return ['status' => 'ada', 'display' => $display];
    }
}

<?php

namespace Optima\DepotStock\Services;

use Optima\DepotStock\Models\Client;

class ClientRiskSnapshot
{
    public function __construct(
        public Client $client,

        // core metrics (all @20°C)
        public float $physicalStock = 0.0,
        public float $clearedStock = 0.0,
        public float $availableToLoad = 0.0,
        public float $unclearedStock = 0.0,
        public float $clearedIdleStock = 0.0,
        public float $entitlementGap = 0.0,

        // policy evaluation
        public string $status = 'ok',             // ok | warn | critical
        public ?string $primaryFlag = null,       // e.g. 'storage_congestion', 'out_of_entitlement'
        public array $flags = [],                 // list of strings / codes
        public ?string $shortMessage = null,
        public ?string $longMessage = null,
    ) {}
}
<?php

namespace App\Services;

use App\Models\Charm;
use Illuminate\Support\Collection;

class CharmService
{
    /** @var array<string, array{data: Collection, expiresAt: int}> */
    protected static array $cache = [];

    protected const CACHE_TTL_SECONDS = 10;

    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    /**
     * Expire discounts that have passed their endAt date.
     */
    protected function expireDiscounts(): void
    {
        Charm::where('discount.enabled', true)
            ->where('discount.endAt', '<=', now())
            ->update(['discount.enabled' => false]);
    }

    /**
     * Get all charms with optional filters. Uses in-process cache with TTL.
     *
     * @param  array{category?: string, active?: bool}  $filters
     */
    public function getCharms(array $filters = []): Collection
    {
        $this->inventoryService->runReservationExpiryIfNeeded();

        $cacheKey = 'charms:' . json_encode($filters, JSON_THROW_ON_ERROR);

        if (isset(self::$cache[$cacheKey]) && self::$cache[$cacheKey]['expiresAt'] > time()) {
            return self::$cache[$cacheKey]['data'];
        }

        $this->expireDiscounts();

        $query = Charm::query();

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (array_key_exists('active', $filters)) {
            $query->where('active', $filters['active']);
        }

        $charms = $query->with('categoryRef')->orderBy('name')->get();

        self::$cache[$cacheKey] = [
            'data' => $charms,
            'expiresAt' => time() + self::CACHE_TTL_SECONDS,
        ];

        return $charms;
    }

    /**
     * Invalidate all in-process charm caches.
     */
    public static function invalidateCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get a single charm by ID.
     */
    public function getCharmById(string $id): ?Charm
    {
        return Charm::with('categoryRef')->find($id);
    }

    /**
     * Get a single charm by slug.
     */
    public function getCharmBySlug(string $slug): ?Charm
    {
        return Charm::with('categoryRef')->where('slug', $slug)->first();
    }

    /**
     * Create a new charm.
     */
    public function createCharm(array $data): Charm
    {
        if (isset($data['categoryId']) && !isset($data['category'])) {
            $data['category'] = $data['categoryId'];
        }
        unset($data['categoryId']);

        $charm = Charm::create($data);

        self::invalidateCache();

        return $charm;
    }

    /**
     * Update an existing charm.
     */
    public function updateCharm(string $id, array $data): ?Charm
    {
        $charm = Charm::find($id);

        if (!$charm) {
            return null;
        }

        if (isset($data['categoryId'])) {
            $data['category'] = $data['categoryId'];
        }
        unset($data['categoryId']);

        $charm->update($data);
        $charm->refresh();

        self::invalidateCache();

        return $charm;
    }

    /**
     * Delete a charm by ID.
     */
    public function deleteCharm(string $id): ?Charm
    {
        $charm = Charm::find($id);

        if (!$charm) {
            return null;
        }

        $charm->delete();

        self::invalidateCache();

        return $charm;
    }
}

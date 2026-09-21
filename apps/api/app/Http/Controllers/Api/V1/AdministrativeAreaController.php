<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdministrativeAreaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\StoreAdministrativeAreaRequest;
use App\Http\Resources\AdministrativeAreaResource;
use App\Models\AdministrativeArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class AdministrativeAreaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $areas = AdministrativeArea::query()
            ->when($request->string('type')->isNotEmpty(), fn ($query) => $query->where('type', $request->string('type')))
            ->orderByRaw("CASE type WHEN 'province' THEN 1 WHEN 'municipality' THEN 2 WHEN 'barangay' THEN 3 WHEN 'purok' THEN 4 ELSE 5 END")
            ->orderBy('name')
            ->get();
        $byId = $areas->keyBy('id');

        $areas->each(function (AdministrativeArea $area) use ($byId): void {
            $names = [$area->name];
            $parent = $byId->get($area->parent_id);
            while ($parent) {
                array_unshift($names, $parent->name);
                $parent = $byId->get($parent->parent_id);
            }
            $area->setAttribute('path', implode(' / ', $names));
        });

        return AdministrativeAreaResource::collection($areas);
    }

    public function store(StoreAdministrativeAreaRequest $request): AdministrativeAreaResource
    {
        $type = AdministrativeAreaType::from($request->validated('type'));
        $parent = $request->validated('parent_id')
            ? AdministrativeArea::findOrFail($request->validated('parent_id'))
            : null;

        if ($type->parentType() !== $parent?->type) {
            throw ValidationException::withMessages([
                'parent_id' => $type === AdministrativeAreaType::Province
                    ? 'A province cannot have a parent area.'
                    : "A {$type->label()} must belong to a {$type->parentType()?->label()}.",
            ]);
        }

        $area = AdministrativeArea::create($request->validated());

        return new AdministrativeAreaResource($area);
    }
}

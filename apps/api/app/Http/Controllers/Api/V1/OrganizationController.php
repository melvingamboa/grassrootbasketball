<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $memberships = $request->user()->memberships()->get()->keyBy('organization_id');
        $organizations = Organization::query()
            ->whereIn('id', $memberships->keys())
            ->withCount('memberships')
            ->orderBy('name')
            ->get()
            ->each(fn (Organization $organization) => $organization->setAttribute(
                'current_role',
                $memberships->get($organization->id)?->role->value,
            ));

        return OrganizationResource::collection($organizations);
    }

    public function store(StoreOrganizationRequest $request): OrganizationResource
    {
        $this->authorize('create', Organization::class);
        $organization = $this->organizations->create($request->user(), $request->validated());
        $organization->loadCount('memberships')->setAttribute('current_role', 'owner');

        return new OrganizationResource($organization);
    }

    public function show(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);
        $membership = $organization->memberships()->where('user_id', $request->user()->id)->first();
        $organization->loadCount('memberships')->setAttribute(
            'current_role',
            $request->user()->is_platform_admin ? 'platform_admin' : $membership?->role->value,
        );

        return new OrganizationResource($organization);
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
    ): OrganizationResource {
        $this->authorize('update', $organization);
        $organization->update($request->validated());

        return new OrganizationResource($organization->refresh());
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateMembershipRoleRequest;
use App\Http\Resources\OrganizationMembershipResource;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationMemberController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);

        return OrganizationMembershipResource::collection(
            $organization->memberships()->with('user')->orderBy('role')->get(),
        );
    }

    public function update(
        UpdateMembershipRoleRequest $request,
        Organization $organization,
        OrganizationMembership $membership,
    ): OrganizationMembershipResource {
        $this->authorize('manageMembers', $organization);
        $membership = $this->organizations->updateMembershipRole(
            $request->user(),
            $organization,
            $membership,
            OrganizationRole::from($request->validated('role')),
        );

        return new OrganizationMembershipResource($membership);
    }

    public function destroy(
        Request $request,
        Organization $organization,
        OrganizationMembership $membership,
    ): JsonResponse {
        $this->authorize('manageMembers', $organization);
        $this->organizations->removeMembership($request->user(), $organization, $membership);

        return response()->json(['message' => 'Member removed.']);
    }
}

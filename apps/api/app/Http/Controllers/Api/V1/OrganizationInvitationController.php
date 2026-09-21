<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterFromInvitationRequest;
use App\Http\Requests\Organization\StoreInvitationRequest;
use App\Http\Resources\InvitationPreviewResource;
use App\Http\Resources\OrganizationInvitationResource;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Services\OrganizationInvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class OrganizationInvitationController extends Controller
{
    public function __construct(private readonly OrganizationInvitationService $invitations) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('manageMembers', $organization);

        return OrganizationInvitationResource::collection(
            $organization->invitations()->with('inviter')->latest()->get(),
        );
    }

    public function show(string $token): InvitationPreviewResource
    {
        abort_unless(strlen($token) === 64, 404);

        return new InvitationPreviewResource($this->invitations->preview($token));
    }

    public function register(RegisterFromInvitationRequest $request, string $token): UserResource
    {
        abort_unless(strlen($token) === 64, 404);
        $user = $this->invitations->registerAndAccept($request->validated(), $token);
        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function store(
        StoreInvitationRequest $request,
        Organization $organization,
    ): OrganizationInvitationResource {
        $this->authorize('manageMembers', $organization);
        $invitation = $this->invitations->invite(
            $request->user(),
            $organization,
            $request->validated('email'),
            OrganizationRole::from($request->validated('role')),
        );

        return new OrganizationInvitationResource($invitation);
    }

    public function accept(Request $request): OrganizationResource
    {
        $request->validate(['token' => ['required', 'string', 'size:64']]);
        $organization = $this->invitations->accept($request->user(), $request->string('token'));
        $role = $organization->memberships()->where('user_id', $request->user()->id)->firstOrFail()->role;
        $organization->loadCount('memberships')->setAttribute('current_role', $role->value);

        return new OrganizationResource($organization);
    }
}

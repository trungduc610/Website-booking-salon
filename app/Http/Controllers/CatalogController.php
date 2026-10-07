<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function branches(Request $request): View
    {
        $query = Branch::query()->whereHas('business')->with('business');
        if (! $request->user()->hasRole('PLATFORM_ADMIN')) {
            $query->whereExists(function ($grants) use ($request): void {
                $grants->select('user_roles.id')->from('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('user_roles.user_id', $request->user()->id)
                    ->whereColumn('user_roles.business_id', 'branches.business_id')
                    ->where(fn ($q) => $q->whereNull('user_roles.expires_at')->orWhere('user_roles.expires_at', '>', now()))
                    ->where(function ($q): void {
                        $q->where(fn ($owner) => $owner->where('roles.code', 'BUSINESS_OWNER')->whereNull('user_roles.branch_id'))
                            ->orWhere(fn ($staff) => $staff->whereIn('roles.code', ['BRANCH_MANAGER', 'RECEPTIONIST', 'STAFF'])
                                ->whereColumn('user_roles.branch_id', 'branches.id'));
                    });
            });
        }

        return view('catalog.branches', ['branches' => $query->orderBy('name')->paginate(12)]);
    }

    public function index(Request $request, Branch $branch): View
    {
        Gate::authorize('view', $branch);
        $input = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $services = $branch->services()->with('category')
            ->when($input['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('catalog.index', compact('branch', 'services'));
    }

    public function create(Branch $branch): View
    {
        Gate::authorize('update', $branch);

        return $this->form($branch, new Service(['duration_minutes' => 60, 'status' => 'ACTIVE', 'bookable' => true]));
    }

    public function store(ServiceRequest $request, Branch $branch): RedirectResponse
    {
        $service = new Service($request->validated());
        $service->business_id = $branch->business_id;
        $branch->services()->save($service);

        return redirect()->route('catalog.index', $branch)->with('success', 'Đã thêm dịch vụ.');
    }

    public function edit(Branch $branch, Service $service): View
    {
        Gate::authorize('update', $branch);
        abort_unless($service->branch_id === $branch->id && $service->business_id === $branch->business_id, 404);

        return $this->form($branch, $service);
    }

    public function update(ServiceRequest $request, Branch $branch, Service $service): RedirectResponse
    {
        abort_unless($service->branch_id === $branch->id && $service->business_id === $branch->business_id, 404);
        $service->update($request->validated());

        return redirect()->route('catalog.index', $branch)->with('success', 'Đã cập nhật dịch vụ.');
    }

    private function form(Branch $branch, Service $service): View
    {
        $categories = ServiceCategory::where('business_id', $branch->business_id)->orderBy('name')->get();

        return view('catalog.form', compact('branch', 'service', 'categories'));
    }
}

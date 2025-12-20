<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all companies (Admin only).
     * GET /api/v1/admin/companies
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view all companies.');
        }

        $companies = Company::query()
            ->when($request->has('active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'companies' => CompanyResource::collection($companies),
            'pagination' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    /**
     * Get active companies (Public - for resale checkout).
     * GET /api/v1/companies
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activeCompanies()
    {
        $companies = Company::active()
            ->orderBy('name')
            ->get();

        return $this->successResponse([
            'companies' => CompanyResource::collection($companies),
        ]);
    }

    /**
     * Store a new company (Admin only).
     * POST /api/v1/admin/companies
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCompanyRequest $request)
    {
        $validated = $request->validated();

        // Handle logo upload if present
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $validated['logo'] = file_get_contents($file->getRealPath());
            $validated['logo_mime_type'] = $file->getMimeType();
        }

        $company = Company::create($validated);

        return $this->createdResponse(
            new CompanyResource($company),
            'Company created successfully'
        );
    }

    /**
     * Get a specific company (Admin only).
     * GET /api/v1/admin/companies/{id}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view company details.');
        }

        $company = Company::find($id);

        if (! $company) {
            return $this->notFoundResponse('Company not found');
        }

        return $this->successResponse(new CompanyResource($company));
    }

    /**
     * Update a company (Admin only).
     * PUT/PATCH /api/v1/admin/companies/{id}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCompanyRequest $request, int $id)
    {
        $company = Company::find($id);

        if (! $company) {
            return $this->notFoundResponse('Company not found');
        }

        $validated = $request->validated();

        // Handle logo upload if present
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $validated['logo'] = file_get_contents($file->getRealPath());
            $validated['logo_mime_type'] = $file->getMimeType();
        }

        $company->update($validated);

        return $this->successResponse(
            new CompanyResource($company),
            'Company updated successfully'
        );
    }

    /**
     * Delete a company (Admin only).
     * DELETE /api/v1/admin/companies/{id}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, int $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can delete companies.');
        }

        $company = Company::find($id);

        if (! $company) {
            return $this->notFoundResponse('Company not found');
        }

        $company->delete();

        return $this->successResponse([], 'Company deleted successfully');
    }

    /**
     * Serve company logo as binary.
     * GET /api/v1/companies/{id}/logo
     *
     * @return \Illuminate\Http\Response
     */
    public function logo(int $id)
    {
        $company = Company::find($id);

        if (! $company || ! $company->logo) {
            return response('Logo not found', 404);
        }

        return response($company->logo)
            ->header('Content-Type', $company->logo_mime_type ?? 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}

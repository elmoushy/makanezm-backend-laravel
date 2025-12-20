<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Public Routes Tests ====================

    /**
     * Test public can view active companies.
     */
    public function test_public_can_view_active_companies(): void
    {
        Company::factory()->count(3)->create(['is_active' => true]);
        Company::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/companies');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.companies');
    }

    /**
     * Test public cannot view inactive companies in public list.
     */
    public function test_public_companies_list_excludes_inactive(): void
    {
        Company::factory()->create(['name' => 'Active Company', 'is_active' => true]);
        Company::factory()->create(['name' => 'Inactive Company', 'is_active' => false]);

        $response = $this->getJson('/api/v1/companies');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.companies')
            ->assertJsonFragment(['name' => 'Active Company'])
            ->assertJsonMissing(['name' => 'Inactive Company']);
    }

    // ==================== Admin List Tests ====================

    /**
     * Test admin can view all companies including inactive.
     */
    public function test_admin_can_view_all_companies(): void
    {
        $admin = User::factory()->admin()->create();

        Company::factory()->count(3)->create(['is_active' => true]);
        Company::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/companies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'companies',
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);

        $this->assertEquals(5, $response->json('data.pagination.total'));
    }

    /**
     * Test admin can filter companies by active status.
     */
    public function test_admin_can_filter_companies_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        Company::factory()->count(3)->create(['is_active' => true]);
        Company::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/companies?active=true');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.pagination.total'));

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/companies?active=false');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    /**
     * Test regular user cannot view admin companies list.
     */
    public function test_regular_user_cannot_view_admin_companies(): void
    {
        $user = User::factory()->create(['role' => 'USER']);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/companies');

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated cannot view admin companies list.
     */
    public function test_unauthenticated_cannot_view_admin_companies(): void
    {
        $response = $this->getJson('/api/v1/admin/companies');

        $response->assertStatus(401);
    }

    // ==================== Admin Create Tests ====================

    /**
     * Test admin can create a company.
     */
    public function test_admin_can_create_company(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/companies', [
            'name' => 'Bishwar',
            'activity' => 'E-commerce',
            'store_url' => 'https://bishwar.com',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Bishwar'])
            ->assertJsonFragment(['activity' => 'E-commerce']);

        $this->assertDatabaseHas('companies', ['name' => 'Bishwar']);
    }

    /**
     * Test admin can create company with logo.
     */
    public function test_admin_can_create_company_with_logo(): void
    {
        $admin = User::factory()->admin()->create();

        // Create a fake file without requiring GD extension
        $logo = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/companies', [
            'name' => 'Sam Company',
            'activity' => 'Retail',
            'logo' => $logo,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Sam Company']);

        $company = Company::where('name', 'Sam Company')->first();
        $this->assertNotNull($company->logo);
        $this->assertNotNull($company->logo_mime_type);
    }

    /**
     * Test company name is required.
     */
    public function test_company_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/companies', [
            'activity' => 'E-commerce',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test company name must be unique.
     */
    public function test_company_name_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        Company::factory()->create(['name' => 'Bishwar']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/companies', [
            'name' => 'Bishwar',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test regular user cannot create company.
     */
    public function test_regular_user_cannot_create_company(): void
    {
        $user = User::factory()->create(['role' => 'USER']);

        $response = $this->actingAs($user)->postJson('/api/v1/admin/companies', [
            'name' => 'Test Company',
        ]);

        $response->assertStatus(403);
    }

    // ==================== Admin Show Tests ====================

    /**
     * Test admin can view specific company.
     */
    public function test_admin_can_view_specific_company(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create(['name' => 'Test Company']);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/companies/{$company->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Test Company']);
    }

    /**
     * Test admin gets 404 for non-existent company.
     */
    public function test_admin_gets_404_for_nonexistent_company(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/companies/99999');

        $response->assertStatus(404);
    }

    // ==================== Admin Update Tests ====================

    /**
     * Test admin can update company.
     */
    public function test_admin_can_update_company(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/companies/{$company->id}", [
            'name' => 'New Name',
            'activity' => 'Wholesale',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name'])
            ->assertJsonFragment(['activity' => 'Wholesale']);

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'New Name']);
    }

    /**
     * Test admin can deactivate company.
     */
    public function test_admin_can_deactivate_company(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/companies/{$company->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['is_active' => false]);

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'is_active' => false]);
    }

    /**
     * Test update validates unique name excluding current company.
     */
    public function test_update_allows_same_name_for_same_company(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create(['name' => 'Test Company']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/companies/{$company->id}", [
            'name' => 'Test Company',
            'activity' => 'Updated Activity',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test update rejects duplicate name from other company.
     */
    public function test_update_rejects_duplicate_name(): void
    {
        $admin = User::factory()->admin()->create();
        Company::factory()->create(['name' => 'Existing Company']);
        $company = Company::factory()->create(['name' => 'Test Company']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/companies/{$company->id}", [
            'name' => 'Existing Company',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ==================== Admin Delete Tests ====================

    /**
     * Test admin can delete company.
     */
    public function test_admin_can_delete_company(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/companies/{$company->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    /**
     * Test regular user cannot delete company.
     */
    public function test_regular_user_cannot_delete_company(): void
    {
        $user = User::factory()->create(['role' => 'USER']);
        $company = Company::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/admin/companies/{$company->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    // ==================== Logo Endpoint Tests ====================

    /**
     * Test can retrieve company logo.
     */
    public function test_can_retrieve_company_logo(): void
    {
        // Create company with fake binary logo data
        $company = Company::factory()->create([
            'logo' => 'fake-binary-logo-content',
            'logo_mime_type' => 'image/png',
        ]);

        $response = $this->get("/api/v1/companies/{$company->id}/logo");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');
    }

    /**
     * Test logo returns 404 when no logo exists.
     */
    public function test_logo_returns_404_when_no_logo(): void
    {
        $company = Company::factory()->create(['logo' => null]);

        $response = $this->get("/api/v1/companies/{$company->id}/logo");

        $response->assertStatus(404);
    }
}

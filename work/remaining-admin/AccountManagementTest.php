<?php

namespace Tests\Feature\Staff;

use App\Enums\AccountType;
use App\Enums\BaseCv\Domain;
use App\Enums\BaseCv\Language;
use App\Enums\BaseCv\LanguageLevel;
use App\Enums\EmployerApprovalStatus;
use App\Models\CandidateDocument;
use App\Models\CandidateProfile;
use App\Models\EmployerDocument;
use App\Models\EmployerProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_updates_and_safely_deletes_a_client(): void
    {
        Notification::fake();
        $administrator = $this->administrator();
        $data = $this->clientData();
        $data['description'] = '<p onclick="alert(1)">Présentation <strong>riche</strong></p><script>alert(1)</script>';

        $this->actingAs($administrator)->post(route('staff.clients.store'), $data)->assertRedirect(route('staff.admin.clients'));
        $profile = EmployerProfile::firstOrFail();
        $this->assertSame(AccountType::Employer, $profile->user->account_type);
        $this->assertNull($profile->account_configured_at);
        $this->assertSame('emp_'.$profile->id.'@pending.beta-conseils.invalid', $profile->user->email);
        $this->assertSame(Domain::ItTelecommunications->value, $profile->domain);
        $this->assertSame('<p>Présentation <strong>riche</strong></p>', $profile->description);
        Notification::assertNothingSent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'employer.created_by_staff', 'auditable_id' => $profile->id]);

        $this->actingAs($administrator)->put(route('staff.clients.access.update', $profile), [
            'name' => 'Client Owner',
            'email' => 'owner@example.test',
        ])->assertRedirect(route('staff.admin.clients'));
        $this->assertNotNull($profile->fresh()->account_configured_at);
        $this->assertSame('owner@example.test', $profile->user->fresh()->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employer.access_configured_by_staff', 'auditable_id' => $profile->id]);

        $this->actingAs($administrator)->put(route('staff.clients.update', $profile), array_merge($data, ['company_name' => 'Updated Company']))->assertRedirect();
        $this->assertSame('Updated Company', $profile->fresh()->company_name);
        $this->actingAs($administrator)->delete(route('staff.clients.destroy', $profile))->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    }

    public function test_administrator_can_add_the_logo_when_creating_a_client(): void
    {
        Storage::fake('logos');
        $administrator = $this->administrator();
        $image = imagecreatetruecolor(128, 128);
        $path = tempnam(sys_get_temp_dir(), 'new-client-logo').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        $this->actingAs($administrator)->post(route('staff.clients.store'), array_merge($this->clientData(), [
            'logo' => new UploadedFile($path, 'logo.png', 'image/png', null, true),
        ]))->assertRedirect(route('staff.admin.clients'));

        $profile = EmployerProfile::firstOrFail();
        $this->assertNotNull($profile->logo_path);
        Storage::disk('logos')->assertExists($profile->logo_path);
    }

    public function test_client_management_is_validated_permission_protected_and_filters_work(): void
    {
        $administrator = $this->administrator();
        $assistant = User::factory()->create(['account_type' => AccountType::Staff])->assignRole('assistant');
        $this->actingAs($assistant)->post(route('staff.clients.store'), $this->clientData())->assertForbidden();
        $this->actingAs($administrator)->post(route('staff.clients.store'), array_merge($this->clientData(), ['company_email' => 'invalid']))->assertSessionHasErrors('company_email');

        $user = User::factory()->create(['account_type' => AccountType::Employer]);
        $profile = EmployerProfile::create(['user_id' => $user->id, 'company_name' => 'Filtered', 'company_email' => 'f@example.test', 'company_phone' => '+222 11111111', 'organisation_type' => 'local_company_consultancy', 'country' => 'MR', 'commune_id' => 1]);
        $profile->forceFill(['approval_status' => EmployerApprovalStatus::Approved])->save();
        $this->actingAs($administrator)->get(route('staff.admin.clients', ['status' => 'approved', 'type' => 'local_company_consultancy']))->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));
    }

    public function test_administrator_replaces_and_removes_a_client_logo(): void
    {
        Storage::fake('logos');
        $administrator = $this->administrator();
        $user = User::factory()->create(['account_type' => AccountType::Employer]);
        $profile = EmployerProfile::create(['user_id' => $user->id, 'company_name' => 'Logo Client']);
        $image = imagecreatetruecolor(128, 128);
        $path = tempnam(sys_get_temp_dir(), 'client-logo').'.png';
        imagepng($image, $path);

        $this->actingAs($administrator)->post(route('staff.clients.logo.store', $profile), [
            'logo' => new UploadedFile($path, 'logo.png', 'image/png', null, true),
        ])->assertRedirect();

        $stored = $profile->fresh()->logo_path;
        $this->assertNotNull($stored);
        Storage::disk('logos')->assertExists($stored);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employer.logo_replaced_by_staff', 'auditable_id' => $profile->id]);

        $this->actingAs($administrator)->delete(route('staff.clients.logo.destroy', $profile))->assertRedirect();
        $this->assertNull($profile->fresh()->logo_path);
        Storage::disk('logos')->assertMissing($stored);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employer.logo_removed_by_staff', 'auditable_id' => $profile->id]);
    }

    public function test_administrator_validates_deactivates_and_manages_private_client_documents(): void
    {
        Storage::fake(EmployerDocument::DISK);
        $administrator = $this->administrator();
        $user = User::factory()->create(['account_type' => AccountType::Employer]);
        $profile = EmployerProfile::create([
            'user_id' => $user->id,
            'company_name' => 'Document Client',
            'approval_status' => EmployerApprovalStatus::Pending,
        ]);

        $this->actingAs($administrator)->patch(route('staff.clients.approve', $profile))->assertRedirect();
        $this->assertSame(EmployerApprovalStatus::Approved, $profile->fresh()->approval_status);
        $this->actingAs($administrator)->patch(route('staff.clients.deactivate', $profile))->assertRedirect();
        $this->assertSame(EmployerApprovalStatus::Pending, $profile->fresh()->approval_status);

        $image = imagecreatetruecolor(32, 32);
        $path = tempnam(sys_get_temp_dir(), 'client-document').'.png';
        imagepng($image, $path);
        imagedestroy($image);
        $this->actingAs($administrator)->post(route('staff.clients.documents.store', $profile), [
            'label' => 'Agrément',
            'document_type' => 'Agrément administratif',
            'file' => new UploadedFile($path, 'agrement.png', 'image/png', null, true),
        ])->assertRedirect();

        $document = EmployerDocument::sole();
        Storage::disk(EmployerDocument::DISK)->assertExists($document->path);
        $this->actingAs($administrator)->get(route('staff.clients.documents.download', [$profile, $document]))
            ->assertOk()->assertDownload('agrement.png');
        $this->actingAs($administrator)->delete(route('staff.clients.documents.destroy', [$profile, $document]))->assertRedirect();
        Storage::disk(EmployerDocument::DISK)->assertMissing($document->path);
        $this->assertDatabaseMissing('employer_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employer.document_added_by_staff', 'auditable_id' => $profile->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employer.document_removed_by_staff', 'auditable_id' => $profile->id]);
    }

    public function test_staff_without_account_management_permission_do_not_receive_client_mutation_actions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $assistant = User::factory()->create(['account_type' => AccountType::Staff])->assignRole('assistant');
        $user = User::factory()->create(['account_type' => AccountType::Employer]);
        EmployerProfile::create(['user_id' => $user->id, 'company_name' => 'Visible Client']);

        $this->actingAs($assistant)->get(route('staff.admin.clients'))->assertInertia(fn (Assert $page) => $page
            ->missing('primaryAction')
            ->missing('rows.data.0.actions'));
    }

    public function test_administrator_manages_candidate_profile_records_and_moderation_without_public_leakage(): void
    {
        Notification::fake();
        $administrator = $this->administrator();
        $data = ['name' => 'Private Candidate', 'email' => 'candidate@example.test', 'phone' => '+22211111111', 'date_of_birth' => '1990-01-01', 'gender' => 'female', 'country' => 'MR', 'domain' => Domain::ItTelecommunications->value, 'specialty' => 'Security', 'study_level' => 'master_2', 'experience_level' => '5_to_9_years', 'is_validated' => '1', 'ranking' => 5, 'featured' => '1'];
        $this->actingAs($administrator)->post(route('staff.candidates.store'), $data)->assertRedirect(route('staff.admin.candidates'));
        $profile = CandidateProfile::firstOrFail();
        $this->assertFalse($profile->is_validated);
        $this->assertNull($profile->account_configured_at);
        $this->assertSame('cand_'.$profile->id.'@pending.beta-conseils.invalid', $profile->user->email);
        Notification::assertNothingSent();

        $this->actingAs($administrator)->put(route('staff.candidates.access.update', $profile), ['email' => 'candidate@example.test'])->assertRedirect(route('staff.admin.candidates'));
        $this->assertNotNull($profile->fresh()->account_configured_at);
        $this->actingAs($administrator)->put(route('staff.candidates.update', $profile), $data)->assertRedirect(route('staff.admin.candidates'));
        $this->assertTrue($profile->fresh()->is_validated);
        $this->assertNotNull($profile->fresh()->featured_at);

        $records = ['languages' => [['language' => Language::French->value, 'custom_name' => null, 'level' => LanguageLevel::VeryGood->value]], 'educations' => [['title' => 'Master', 'institution' => 'Private School', 'started_on' => '2010-09', 'ended_on' => '2012-06']], 'experiences' => [['job_title' => 'Engineer', 'employer_name' => 'Private Employer', 'description' => 'Private details', 'started_on' => '2013-01', 'ended_on' => null]]];
        $this->actingAs($administrator)->put(route('staff.candidates.records.update', $profile), $records)->assertRedirect(route('staff.admin.candidates'));
        $this->assertDatabaseHas('candidate_languages', ['candidate_profile_id' => $profile->id, 'language' => 'french']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'candidate.records_updated_by_staff', 'auditable_id' => $profile->id]);

        $employer = User::factory()->create(['account_type' => AccountType::Employer]);
        $this->actingAs($employer)->get(route('staff.candidates.records', $profile))->assertForbidden();
        $this->get(route('home'))->assertDontSee('Private Candidate')->assertDontSee('Private Employer')->assertDontSee('Private details');
    }

    public function test_featuring_a_candidate_replaces_the_previous_featured_candidate(): void
    {
        Notification::fake();
        $administrator = $this->administrator();
        $oldUser = User::factory()->create(['account_type' => AccountType::Candidate]);
        $old = CandidateProfile::create(['user_id' => $oldUser->id]);
        $old->forceFill(['featured_at' => now()->subDay()])->save();
        $data = ['name' => 'New Featured', 'email' => 'new-featured@example.test', 'phone' => null, 'date_of_birth' => '1990-01-01', 'gender' => 'female', 'country' => 'MR', 'domain' => Domain::Legal->value, 'specialty' => 'Juriste', 'study_level' => 'master_2', 'experience_level' => '5_to_9_years', 'is_validated' => '1', 'ranking' => 5, 'featured' => '1'];

        $this->actingAs($administrator)->post(route('staff.candidates.store'), $data)->assertRedirect();
        $new = CandidateProfile::query()->where('user_id', '!=', $oldUser->id)->sole();
        $this->actingAs($administrator)->put(route('staff.candidates.update', $new), $data)->assertRedirect();

        $this->assertNull($old->fresh()->featured_at);
        $this->assertNotNull(CandidateProfile::query()->where('user_id', '!=', $oldUser->id)->sole()->featured_at);
    }

    public function test_candidate_filters_and_invalid_cross_account_requests_are_enforced(): void
    {
        $administrator = $this->administrator();
        $candidateUser = User::factory()->create(['account_type' => AccountType::Candidate]);
        $profile = CandidateProfile::create(['user_id' => $candidateUser->id, 'domain' => Domain::Legal, 'is_validated' => true, 'ranking' => 4]);
        $profile->languages()->create(['language' => Language::Arabic, 'level' => LanguageLevel::Native]);
        $this->actingAs($administrator)->get(route('staff.admin.candidates', ['domain' => 'legal', 'language' => 'arabic', 'ranking' => 4, 'validated' => 'yes']))->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.reference', $profile->reference_code));
        $this->actingAs($candidateUser)->put(route('staff.candidates.update', $profile), [])->assertForbidden();
        $this->actingAs($administrator)->put(route('staff.candidates.records.update', $profile), ['languages' => [['language' => 'invalid', 'level' => 'native']]])->assertSessionHasErrors('languages.0.language');
    }

    public function test_candidate_photo_is_reencoded_private_and_staff_managed(): void
    {
        Storage::fake('candidate_photos');
        $administrator = $this->administrator();
        $candidateUser = User::factory()->create(['account_type' => AccountType::Candidate]);
        $profile = CandidateProfile::create(['user_id' => $candidateUser->id]);
        $image = imagecreatetruecolor(200, 200);
        $path = tempnam(sys_get_temp_dir(), 'candidate-photo').'.png';
        imagepng($image, $path);

        $this->actingAs($administrator)->post(route('staff.candidates.photo.store', $profile), [
            'photo' => new UploadedFile($path, 'identity.png', 'image/png', null, true),
        ])->assertSessionHasNoErrors();
        $storedPath = $profile->fresh()->photo_path;
        $this->assertNotNull($storedPath);
        $this->assertStringStartsWith('RIFF', (string) Storage::disk('candidate_photos')->get($storedPath));
        $this->assertDatabaseHas('audit_logs', ['action' => 'candidate.photo_replaced_by_staff']);

        $employer = User::factory()->create(['account_type' => AccountType::Employer]);
        $this->actingAs($employer)->get(route('candidate-photos.show', $profile))->assertNotFound();
        $this->actingAs($candidateUser)->get(route('candidate-photos.show', $profile))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($administrator)->delete(route('staff.candidates.photo.destroy', $profile))->assertRedirect();
        Storage::disk('candidate_photos')->assertMissing($storedPath);
    }

    public function test_administrator_validates_deactivates_and_manages_candidate_documents(): void
    {
        Storage::fake(CandidateDocument::DISK);
        $administrator = $this->administrator();
        $candidateUser = User::factory()->create(['account_type' => AccountType::Candidate]);
        $profile = CandidateProfile::create(['user_id' => $candidateUser->id, 'is_validated' => false]);

        $this->actingAs($administrator)->patch(route('staff.candidates.validate', $profile))->assertRedirect();
        $this->assertTrue($profile->fresh()->is_validated);
        $this->actingAs($administrator)->patch(route('staff.candidates.deactivate', $profile))->assertRedirect();
        $this->assertFalse($profile->fresh()->is_validated);

        $image = imagecreatetruecolor(32, 32);
        $path = tempnam(sys_get_temp_dir(), 'candidate-document').'.png';
        imagepng($image, $path);
        imagedestroy($image);
        $this->actingAs($administrator)->post(route('staff.candidates.documents.store', $profile), [
            'label' => 'Diplôme', 'document_type' => 'Diplôme',
            'file' => new UploadedFile($path, 'diplome.png', 'image/png', null, true),
        ])->assertRedirect();
        $document = CandidateDocument::sole();
        Storage::disk(CandidateDocument::DISK)->assertExists($document->path);
        $this->actingAs($administrator)->get(route('staff.candidates.documents.download', [$profile, $document]))->assertOk()->assertDownload('diplome.png');
        $this->actingAs($administrator)->delete(route('staff.candidates.documents.destroy', [$profile, $document]))->assertRedirect();
        Storage::disk(CandidateDocument::DISK)->assertMissing($document->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'candidate.document_added_by_staff', 'auditable_id' => $profile->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'candidate.document_removed_by_staff', 'auditable_id' => $profile->id]);
    }

    public function test_user_edit_delete_and_last_administrator_guards_are_enforced(): void
    {
        $administrator = $this->administrator();
        $candidate = User::factory()->create(['account_type' => AccountType::Candidate]);
        CandidateProfile::create(['user_id' => $candidate->id]);
        $this->actingAs($administrator)->put(route('staff.users.update', $candidate), ['name' => 'Edited', 'email' => 'edited@example.test'])->assertRedirect();
        $this->assertSame(AccountType::Candidate, $candidate->fresh()->account_type);
        $this->actingAs($administrator)->delete(route('staff.users.destroy', $administrator))->assertStatus(422);
        $this->actingAs($administrator)->delete(route('staff.users.destroy', $candidate))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deleted_by_staff']);
    }

    private function administrator(): User
    {
        $this->seed(RolePermissionSeeder::class);

        return User::factory()->create(['account_type' => AccountType::Staff])->assignRole('administrator');
    }

    /** @return array<string, mixed> */
    private function clientData(): array
    {
        return ['name' => 'Client Owner', 'email' => 'owner@example.test', 'company_name' => 'Company', 'organisation_type' => 'local_company_consultancy', 'domain' => Domain::ItTelecommunications->value, 'country' => 'MR', 'commune_id' => 1, 'company_email' => 'company@example.test', 'company_phone' => '+222 11111111', 'approval_status' => 'pending'];
    }
}

<?php

namespace Tests\Feature\Staff;

use App\Actions\Audit\RecordAuditLogEntry;
use App\Enums\AccountType;
use App\Enums\Announcement\AnnouncementCategory;
use App\Enums\Announcement\AnnouncementStatus;
use App\Enums\BaseCv\Domain;
use App\Enums\BaseCv\ExperienceLevel;
use App\Enums\BaseCv\Gender;
use App\Enums\BaseCv\Language;
use App\Enums\BaseCv\LanguageLevel;
use App\Enums\BaseCv\StudyLevel;
use App\Models\CandidateProfile;
use App\Models\EmployerProfile;
use App\Models\StaffInvitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdministrationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_announcement_views_are_counted_and_visible_to_staff(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create(['account_type' => AccountType::Staff])->assignRole('administrator');
        $employerUser = User::factory()->create(['account_type' => AccountType::Employer]);
        $employer = EmployerProfile::create(['user_id' => $employerUser->id, 'company_name' => 'Viewed Client']);
        $announcement = $employer->announcements()->create(['category' => AnnouncementCategory::JobOffer, 'title' => 'Viewed announcement']);
        $announcement->forceFill(['status' => AnnouncementStatus::Published, 'published_at' => now()])->save();

        $this->get(route('announcements.show', $announcement))->assertOk();
        $this->get(route('announcements.show', $announcement))->assertOk();
        $this->assertSame(2, $announcement->fresh()->view_count);
        $this->actingAs($administrator)->get(route('staff.admin.announcements'))->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.views', 2)
            ->where('rows.data.0.tone', 'success'));
    }

    public function test_calls_directory_preserves_the_beta_mr_table_contract(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create(['account_type' => AccountType::Staff])
            ->assignRole('administrator');
        $employerUser = User::factory()->create(['account_type' => AccountType::Employer]);
        $employer = EmployerProfile::create([
            'user_id' => $employerUser->id,
            'company_name' => 'Legacy Client',
        ]);

        foreach (range(1, 11) as $index) {
            $employer->announcements()->create([
                'category' => AnnouncementCategory::LocalTender,
                'title' => $index === 11
                    ? '<p>Fourniture &amp; installation d&rsquo;équipements</p>'
                    : 'Appel d’offres '.$index,
                'view_count' => $index,
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('staff.admin.calls'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/Administration')
                ->where('title', "Appels d'offres")
                ->where('primaryAction.label', "Ajouter un appel d'offres")
                ->where('columns', [
                    ['key' => 'id', 'label' => '#'],
                    ['key' => 'title', 'label' => 'Intitulé'],
                    ['key' => 'views', 'label' => 'Nbr de vues'],
                    ['key' => 'status', 'label' => 'État', 'translation' => 'announcement.status'],
                    ['key' => 'category', 'label' => 'Type', 'translation' => 'announcement.category'],
                    ['key' => 'company', 'label' => 'Client'],
                    ['key' => 'deadline', 'label' => 'Date limite'],
                    ['key' => 'actions', 'label' => 'Actions'],
                ])
                ->where('filters.0.label', 'État')
                ->where('filters.1.label', 'État de traduction')
                ->where('filters.2.label', 'Type')
                ->where('filters.3.label', 'Client')
                ->where('rows.per_page', 10)
                ->has('rows.data', 10)
                ->where('rows.data.0.title', 'Fourniture & installation d’équipements')
                ->where('rows.data.0.company', 'Legacy Client'));
    }

    public function test_every_announcement_family_uses_the_same_beta_mr_directory_layout(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create(['account_type' => AccountType::Staff])
            ->assignRole('administrator');

        foreach ([
            'staff.admin.announcements' => ['Annonces regroupées', 'Ajouter une annonce'],
            'staff.admin.offers' => ["Offres d'emploi", 'Ajouter une offre'],
            'staff.admin.calls' => ["Appels d'offres", "Ajouter un appel d'offres"],
            'staff.admin.notices' => ['Avis & infos', 'Ajouter un avis'],
        ] as $route => [$title, $action]) {
            $this->actingAs($administrator)
                ->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('staff/Administration')
                    ->where('title', $title)
                    ->where('primaryAction.label', $action)
                    ->has('filters', 4)
                    ->where('rows.per_page', 10)
                    ->where('columns.1.label', 'Intitulé')
                    ->where('columns.2.label', 'Nbr de vues')
                    ->where('columns.7.label', 'Actions'));
        }
    }

    public function test_administrator_can_open_every_legacy_administration_section(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create(['account_type' => AccountType::Staff])
            ->assignRole('administrator');

        foreach ([
            'staff.admin.announcements', 'staff.admin.offers', 'staff.admin.calls',
            'staff.admin.notices', 'staff.admin.clients',
            'staff.admin.candidates',
            'staff.admin.users', 'staff.admin.audit',
        ] as $route) {
            $this->actingAs($administrator)
                ->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('staff/Administration'));
        }

        $this->actingAs($administrator)
            ->get(route('staff.information-pages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('staff/InformationPages'));

        $this->actingAs($administrator)
            ->get(route('staff.admin.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('staff/SiteSettings'));

        $this->actingAs($administrator)
            ->get(route('staff.references.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('staff/ReferenceValues'));
    }

    public function test_non_administrator_cannot_open_administrator_directories(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $assistant = User::factory()->create(['account_type' => AccountType::Staff])
            ->assignRole('assistant');

        $this->actingAs($assistant)->get(route('staff.admin.users'))->assertForbidden();
        $this->actingAs($assistant)->get(route('staff.admin.candidates'))->assertForbidden();
        $this->actingAs($assistant)->get(route('staff.admin.audit'))->assertForbidden();
    }

    public function test_administrator_can_combine_structured_announcement_filters(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create(['account_type' => AccountType::Staff])
            ->assignRole('administrator');
        $employerUser = User::factory()->create(['account_type' => AccountType::Employer]);
        $employer = EmployerProfile::create(['user_id' => $employerUser->id, 'company_name' => 'Filtered Client']);

        $matching = $employer->announcements()->create([
            'category' => AnnouncementCategory::JobOffer,
            'title' => 'Matching announcement',
        ]);
        $matching->forceFill([
            'status' => AnnouncementStatus::Published,
            'featured_at' => now(),
            'title_ar' => 'إعلان',
            'description_ar' => 'وصف',
            'translation_source_hash' => $matching->translationSourceHash(),
        ])->save();

        $employer->announcements()->create([
            'category' => AnnouncementCategory::Consultant,
            'title' => 'Untranslated draft',
        ]);

        $this->actingAs($administrator)
            ->get(route('staff.admin.offers', [
                'employer' => $employer->id,
                'status' => AnnouncementStatus::Published->value,
                'category' => AnnouncementCategory::JobOffer->value,
                'translated' => 'yes',
                'featured' => 'yes',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filters', 4)
                ->where('filters.0.value', AnnouncementStatus::Published->value)
                ->where('filters.1.value', 'yes')
                ->where('filters.2.value', AnnouncementCategory::JobOffer->value)
                ->where('filters.3.value', (string) $employer->id)
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $matching->id));
    }

    public function test_administrator_can_combine_all_candidate_directory_filters(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create(['account_type' => AccountType::Staff])
            ->assignRole('administrator');
        $matchingUser = User::factory()->create([
            'account_type' => AccountType::Candidate,
            'last_login_at' => now()->subMinutes(5),
        ]);
        $matching = CandidateProfile::create([
            'user_id' => $matchingUser->id,
            'date_of_birth' => '1990-05-15',
            'gender' => Gender::Female,
            'country' => 'MR',
            'domain' => Domain::ItTelecommunications,
            'specialty' => 'Architecture logicielle',
            'study_level' => StudyLevel::Master2,
            'experience_level' => ExperienceLevel::FiveToNineYears,
            'is_validated' => true,
            'ranking' => 5,
            'featured_at' => now(),
        ]);
        $matching->languages()->create([
            'language' => Language::Arabic,
            'level' => LanguageLevel::Native,
        ]);

        $otherUser = User::factory()->create([
            'account_type' => AccountType::Candidate,
            'last_login_at' => now()->subDay(),
        ]);
        $other = CandidateProfile::create([
            'user_id' => $otherUser->id,
            'domain' => Domain::AgricultureLivestock,
            'study_level' => StudyLevel::Bac,
            'experience_level' => ExperienceLevel::UnderOneYear,
            'gender' => Gender::Male,
            'is_validated' => false,
            'ranking' => 1,
        ]);
        $other->languages()->create([
            'language' => Language::French,
            'level' => LanguageLevel::Basic,
        ]);

        $this->actingAs($administrator)
            ->get(route('staff.admin.candidates', [
                'domain' => Domain::ItTelecommunications->value,
                'study' => StudyLevel::Master2->value,
                'experience' => ExperienceLevel::FiveToNineYears->value,
                'gender' => Gender::Female->value,
                'language' => Language::Arabic->value,
                'ranking' => '5',
                'validated' => 'yes',
                'featured' => 'yes',
                'online' => 'yes',
                'cv' => 'complete',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filters', 10)
                ->where('filters.0.value', Domain::ItTelecommunications->value)
                ->where('filters.1.value', StudyLevel::Master2->value)
                ->where('filters.2.value', ExperienceLevel::FiveToNineYears->value)
                ->where('filters.3.value', Gender::Female->value)
                ->where('filters.4.value', Language::Arabic->value)
                ->where('filters.5.value', '5')
                ->where('filters.6.value', 'yes')
                ->where('filters.7.value', 'yes')
                ->where('filters.8.value', 'yes')
                ->where('filters.9.value', 'complete')
                ->has('rows.data', 1)
                ->where('rows.data.0.reference', $matching->reference_code));
    }

    public function test_audit_history_exposes_only_safe_event_metadata(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create(['account_type' => AccountType::Staff])
            ->assignRole('administrator');
        $subject = StaffInvitation::create([
            'email' => 'invitee@example.test',
            'role_id' => Role::findOrCreate('assistant')->id,
            'token_hash' => hash('sha256', 'private invitation token'),
            'expires_at' => now()->addDay(),
        ]);

        (new RecordAuditLogEntry)->record(
            'staff.invitation.created',
            user: $administrator,
            auditable: $subject,
            before: ['password' => 'never expose this'],
            after: ['private_record_content' => 'never expose this either'],
            ip: '203.0.113.5',
        );

        $this->actingAs($administrator)
            ->get(route('staff.admin.audit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/Administration')
                ->where('columns', [
                    ['key' => 'date', 'label' => 'Date'],
                    ['key' => 'actor', 'label' => 'Acteur'],
                    ['key' => 'action', 'label' => 'Action'],
                    ['key' => 'subject', 'label' => 'Objet'],
                ])
                ->has('rows.data', 1)
                ->where('rows.data.0.actor', $administrator->name.' — '.$administrator->email)
                ->where('rows.data.0.action', 'staff.invitation.created')
                ->where('rows.data.0.subject', 'StaffInvitation #'.$subject->id)
                ->missing('rows.data.0.before')
                ->missing('rows.data.0.after')
                ->missing('rows.data.0.ip_hash')
                ->missing('rows.data.0.user_id')
                ->missing('rows.data.0.actor_email'));
    }

    public function test_audit_actor_attribution_survives_user_deletion(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $administrator = User::factory()->create(['account_type' => AccountType::Staff])
            ->assignRole('administrator');
        $deletedActor = User::factory()->create([
            'account_type' => AccountType::Staff,
            'name' => 'Former Staff Member',
            'email' => 'former.staff@example.test',
        ]);

        $entry = (new RecordAuditLogEntry)->record('staff.account.updated', user: $deletedActor);
        $deletedActor->delete();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $entry->id,
            'user_id' => null,
            'actor_name' => 'Former Staff Member',
            'actor_email' => 'former.staff@example.test',
        ]);

        $this->actingAs($administrator)
            ->get(route('staff.admin.audit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.actor', 'Former Staff Member — former.staff@example.test')
                ->where('rows.data.0.action', 'staff.account.updated'));
    }
}

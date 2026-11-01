<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AccountType;
use App\Enums\Announcement\AnnouncementCategory;
use App\Enums\Announcement\AnnouncementStatus;
use App\Enums\BaseCv\ExperienceLevel;
use App\Enums\BaseCv\Gender;
use App\Enums\BaseCv\StudyLevel;
use App\Enums\Company\OrganisationType;
use App\Enums\EmployerApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\CandidateProfile;
use App\Models\EmployerProfile;
use App\Models\ReferenceValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function announcements(Request $request, string $family = 'all'): Response
    {
        abort_unless(in_array($family, ['all', 'offer', 'call', 'notice'], true), 404);

        $categories = collect(AnnouncementCategory::cases())
            ->filter(fn (AnnouncementCategory $category) => $family === 'all' || $category->family() === $family)
            ->pluck('value');

        $query = Announcement::query()
            ->whereNull('revision_of_id')
            ->whereIn('category', $categories)
            ->with('employerProfile.user:id,name')
            ->latest('id');

        if ($family === 'offer') {
            $query->whereNull('closed_at');
        }

        $employer = ctype_digit((string) $request->query('employer')) ? (int) $request->query('employer') : null;
        $status = AnnouncementStatus::tryFrom((string) $request->query('status'));
        $category = AnnouncementCategory::tryFrom((string) $request->query('category'));
        $translated = in_array($request->query('translated'), ['yes', 'no'], true) ? $request->query('translated') : null;
        $featured = in_array($request->query('featured'), ['yes', 'no'], true) ? $request->query('featured') : null;

        if ($employer !== null) {
            $query->where('employer_profile_id', $employer);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($category !== null && $categories->contains($category->value)) {
            $query->where('category', $category);
        }
        if ($translated !== null) {
            $query->{$translated === 'yes' ? 'whereNotNull' : 'whereNull'}('translation_source_hash');
        }
        if ($featured !== null) {
            $query->{$featured === 'yes' ? 'whereNotNull' : 'whereNull'}('featured_at');
        }

        $this->search($query, $request, ['title', 'description']);

        $titles = [
            'all' => 'Annonces regroupées',
            'offer' => "Offres d'emploi",
            'call' => "Appels d'offres",
            'notice' => 'Avis & infos',
        ];

        return $this->table($request, $titles[$family], 'Consultez toutes les annonces et leur état de publication.', [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'title', 'label' => 'Intitulé'],
            ['key' => 'views', 'label' => 'Nbr de vues'],
            ['key' => 'status', 'label' => 'État', 'translation' => 'announcement.status'],
            ['key' => 'category', 'label' => 'Type', 'translation' => 'announcement.category'],
            ['key' => 'company', 'label' => 'Client'],
            ['key' => 'deadline', 'label' => 'Date limite'],
            ['key' => 'actions', 'label' => 'Actions'],
        ], $query->paginate(10)->withQueryString()->through(fn (Announcement $announcement) => [
            'id' => $announcement->id,
            'title' => $announcement->publicTitle() ?: '—',
            'category' => $announcement->category->value,
            'company' => $announcement->employerProfile->company_name ?: $announcement->employerProfile->user->name,
            'status' => $announcement->isClosed() ? 'closed' : $announcement->status->value,
            'deadline' => $announcement->deadline_at?->format('d-m-Y') ?? '—',
            'views' => $announcement->view_count,
            'tone' => match ($announcement->status) {
                AnnouncementStatus::PendingValidation => 'warning',
                AnnouncementStatus::Published => 'success',
                AnnouncementStatus::Rejected => 'danger',
                default => null,
            },
            'actions' => [
                'edit' => route('staff.announcements.edit', $announcement, false),
                'changeType' => route('staff.announcements.edit', $announcement, false).'#category-section',
                'translate' => route('staff.announcements.edit', $announcement, false).'#arabic-translation',
                'review' => $announcement->status === AnnouncementStatus::PendingValidation
                    ? route('staff.announcements.index', [], false).'#announcement-'.$announcement->id : null,
                'feature' => $announcement->status === AnnouncementStatus::Published && ! $announcement->isClosed() && $announcement->featured_at === null
                    ? route('announcements.feature', $announcement, false) : null,
                'unfeature' => $announcement->featured_at !== null
                    ? route('announcements.unfeature', $announcement, false) : null,
                'delete' => in_array($announcement->status->value, ['draft', 'pending_validation', 'rejected'], true)
                    ? route('staff.announcements.destroy', $announcement, false) : null,
            ],
        ]), primaryAction: [
            'label' => match ($family) {
                'offer' => 'Ajouter une offre',
                'call' => "Ajouter un appel d'offres",
                'notice' => 'Ajouter un avis',
                default => 'Ajouter une annonce',
            },
            'url' => route('staff.announcements.create', $family === 'all' ? [] : ['family' => $family], false),
        ], filters: [
            $this->filter('status', 'État', collect(AnnouncementStatus::cases())->map(fn (AnnouncementStatus $item) => [
                'value' => $item->value,
                'label' => Str::headline($item->value),
            ])->values()->all(), $status === null ? '' : $status->value),
            $this->filter('translated', 'État de traduction', [['value' => 'yes', 'label' => 'Traduit'], ['value' => 'no', 'label' => 'Non traduit']], is_string($translated) ? $translated : ''),
            $this->filter('category', 'Type', collect(AnnouncementCategory::cases())->filter(fn (AnnouncementCategory $item) => $categories->contains($item->value))->map(fn (AnnouncementCategory $item) => [
                'value' => $item->value,
                'label' => Str::headline($item->value),
            ])->values()->all(), $category !== null && $categories->contains($category->value) ? $category->value : ''),
            $this->filter('employer', 'Client', EmployerProfile::query()->orderBy('company_name')->get()->map(fn (EmployerProfile $profile) => [
                'value' => (string) $profile->id,
                'label' => $profile->company_name ?: '#'.$profile->id,
            ])->values()->all(), $employer === null ? '' : (string) $employer),
        ]);
    }

    public function employers(Request $request): Response
    {
        $canManageAccounts = $request->user()->can('manage-roles');
        $query = EmployerProfile::query()->with('user:id,name,email')->withCount('announcements')->latest('id');
        $status = EmployerApprovalStatus::tryFrom((string) $request->query('status'));
        $type = OrganisationType::tryFrom((string) $request->query('type'));
        $statusValue = $status === null ? '' : $status->value;
        $typeValue = $type === null ? '' : $type->value;
        if ($status !== null) {
            $query->where('approval_status', $status);
        }
        if ($type !== null) {
            $query->where('organisation_type', $type);
        }
        $this->search($query, $request, ['company_name', 'company_email'], ['user' => ['name', 'email']]);

        return $this->table($request, 'Clients', 'Tous les comptes employeur et leurs annonces.', [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'company', 'label' => 'Entreprise'],
            ['key' => 'contact', 'label' => 'Compte'],
            ['key' => 'email', 'label' => 'E-mail'],
            ['key' => 'status', 'label' => 'État'],
            ['key' => 'announcements', 'label' => 'Annonces'],
            ['key' => 'actions', 'label' => 'Actions'],
        ], $query->paginate(10)->withQueryString()->through(fn (EmployerProfile $profile) => [
            'id' => $profile->id,
            'company' => $profile->company_name ?: '—',
            'contact' => $profile->user->name,
            'email' => $profile->user->email,
            'status' => $profile->approval_status->value,
            'announcements' => $profile->announcements_count,
            'actions' => $canManageAccounts ? [
                'access' => route('staff.clients.access.edit', $profile, false),
                'edit' => route('staff.clients.edit', $profile, false),
                'translate' => route('staff.clients.edit', $profile, false).'#arabic-translation',
                'approve' => $profile->approval_status !== EmployerApprovalStatus::Approved ? route('staff.clients.approve', $profile, false) : null,
                'deactivate' => $profile->approval_status === EmployerApprovalStatus::Approved ? route('staff.clients.deactivate', $profile, false) : null,
                'delete' => $profile->announcements_count === 0 ? route('staff.clients.destroy', $profile, false) : null,
                'reset' => $profile->account_configured_at !== null ? route('staff.clients.password-reset', $profile, false) : null,
            ] : [],
        ]), primaryAction: $canManageAccounts ? ['label' => 'Ajouter un client', 'url' => route('staff.clients.create', [], false)] : null, filters: [
            $this->filter('status', 'État', collect(EmployerApprovalStatus::cases())->map(fn ($v) => ['value' => $v->value, 'label' => Str::headline($v->value)])->all(), $statusValue),
            $this->filter('type', 'Type', collect(OrganisationType::cases())->map(fn ($v) => ['value' => $v->value, 'label' => Str::headline($v->value)])->all(), $typeValue),
        ]);
    }

    public function candidates(Request $request): Response
    {
        $query = CandidateProfile::query()->with('user:id,name,email')->withCount(['applications', 'languages'])->latest('id');
        $domain = in_array((string) $request->query('domain'), ReferenceValue::activeCodes('domain'), true) ? (string) $request->query('domain') : null;
        $study = StudyLevel::tryFrom((string) $request->query('study'));
        $experience = ExperienceLevel::tryFrom((string) $request->query('experience'));
        $gender = Gender::tryFrom((string) $request->query('gender'));
        $language = in_array((string) $request->query('language'), ReferenceValue::activeCodes('language'), true) ? (string) $request->query('language') : null;
        $ranking = in_array((string) $request->query('ranking'), ['1', '2', '3', '4', '5'], true) ? (int) $request->query('ranking') : null;
        $validated = in_array($request->query('validated'), ['yes', 'no'], true) ? $request->query('validated') : null;
        $featured = in_array($request->query('featured'), ['yes', 'no'], true) ? $request->query('featured') : null;
        $online = in_array($request->query('online'), ['yes', 'no'], true) ? $request->query('online') : null;
        $cv = in_array($request->query('cv'), ['complete', 'incomplete'], true) ? $request->query('cv') : null;
        $domainValue = $domain ?? '';
        $studyValue = $study === null ? '' : $study->value;
        $experienceValue = $experience === null ? '' : $experience->value;
        $genderValue = $gender === null ? '' : $gender->value;
        $languageValue = $language ?? '';
        if ($domain) {
            $query->where('domain', $domain);
        }
        if ($study) {
            $query->where('study_level', $study);
        }
        if ($experience) {
            $query->where('experience_level', $experience);
        }
        if ($gender) {
            $query->where('gender', $gender);
        }
        if ($language) {
            $query->whereHas('languages', fn (Builder $q) => $q->where('language', $language));
        }
        if ($ranking) {
            $query->where('ranking', $ranking);
        }
        if ($validated) {
            $query->where('is_validated', $validated === 'yes');
        }
        if ($featured) {
            $query->{$featured === 'yes' ? 'whereNotNull' : 'whereNull'}('featured_at');
        }
        if ($online) {
            $query->whereHas('user', fn (Builder $q) => $online === 'yes' ? $q->where('last_login_at', '>=', now()->subMinutes(15)) : $q->where(fn (Builder $u) => $u->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subMinutes(15))));
        }
        if ($cv === 'complete') {
            $query->completeBaseCv();
        }
        if ($cv === 'incomplete') {
            $query->where(fn (Builder $q) => $q->whereNull('date_of_birth')->orWhereNull('gender')->orWhereNull('country')->orWhereNull('domain')->orWhereNull('specialty')->orWhereNull('study_level')->orWhereNull('experience_level')->orWhereDoesntHave('languages'));
        }
        $this->search($query, $request, ['reference_code', 'specialty'], ['user' => ['name', 'email']]);

        return $this->table($request, 'Candidats', 'Base candidats et état des CV.', [
            ['key' => 'reference', 'label' => 'Référence'],
            ['key' => 'name', 'label' => 'Nom'],
            ['key' => 'email', 'label' => 'E-mail'],
            ['key' => 'specialty', 'label' => 'Spécialité'],
            ['key' => 'cv', 'label' => 'Base CV'],
            ['key' => 'applications', 'label' => 'Candidatures'],
            ['key' => 'moderation', 'label' => 'Modération'],
            ['key' => 'actions', 'label' => 'Actions'],
        ], $query->paginate(10)->withQueryString()->through(fn (CandidateProfile $profile) => [
            'reference' => $profile->reference_code,
            'name' => $profile->user->name,
            'email' => $profile->user->email,
            'specialty' => $profile->specialty ?: '—',
            'cv' => $profile->hasCompleteBaseCv() ? 'Complet' : 'Incomplet',
            'applications' => $profile->applications_count,
            'moderation' => ($profile->is_validated ? 'Validé' : 'Non validé').($profile->ranking ? ' · '.$profile->ranking.'/5' : '').($profile->featured_at ? ' · En vedette' : ''),
            'actions' => [
                'access' => route('staff.candidates.access.edit', $profile, false),
                'edit' => route('staff.candidates.edit', $profile, false),
                'records' => route('staff.candidates.records', $profile, false),
                'translate' => route('staff.candidates.edit', $profile, false).'#arabic-translation',
                'approve' => $profile->is_validated ? null : route('staff.candidates.validate', $profile, false),
                'deactivate' => $profile->is_validated ? route('staff.candidates.deactivate', $profile, false) : null,
                'delete' => $profile->applications_count === 0 ? route('staff.candidates.destroy', $profile, false) : null,
                'reset' => $profile->account_configured_at !== null ? route('staff.candidates.password-reset', $profile, false) : null,
            ],
        ]), primaryAction: ['label' => 'Ajouter un candidat', 'url' => route('staff.candidates.create', [], false)], filters: [
            $this->referenceFilter('domain', 'Domaine', $domainValue), $this->enumFilter('study', 'Études', StudyLevel::cases(), $studyValue),
            $this->enumFilter('experience', 'Expérience', ExperienceLevel::cases(), $experienceValue), $this->enumFilter('gender', 'Genre', Gender::cases(), $genderValue),
            $this->referenceFilter('language', 'Langue', $languageValue), $this->filter('ranking', 'Classement', collect(range(1, 5))->map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v])->all(), $ranking ? (string) $ranking : ''),
            $this->yesNoFilter('validated', 'Validé', $validated), $this->yesNoFilter('featured', 'En vedette', $featured), $this->yesNoFilter('online', 'En ligne (15 min)', $online),
            $this->filter('cv', 'Type de CV', [['value' => 'complete', 'label' => 'Complet'], ['value' => 'incomplete', 'label' => 'Incomplet']], is_string($cv) ? $cv : ''),
        ]);
    }

    public function users(Request $request): Response
    {
        $query = User::query()->latest('id');
        $type = AccountType::tryFrom((string) $request->query('type'));
        $status = in_array($request->query('status'), ['active', 'suspended'], true) ? $request->query('status') : null;
        $verified = in_array($request->query('verified'), ['yes', 'no'], true) ? $request->query('verified') : null;
        $typeValue = $type === null ? '' : $type->value;
        if ($type !== null) {
            $query->where('account_type', $type);
        }
        if ($status !== null) {
            $query->{$status === 'active' ? 'whereNull' : 'whereNotNull'}('suspended_at');
        }
        if ($verified !== null) {
            $query->{$verified === 'yes' ? 'whereNotNull' : 'whereNull'}('email_verified_at');
        }
        $this->search($query, $request, ['name', 'email']);

        return $this->table($request, 'Gestion des utilisateurs', 'Tous les comptes de la plateforme.', [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Nom'],
            ['key' => 'email', 'label' => 'E-mail'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'verified', 'label' => 'E-mail vérifié'],
            ['key' => 'created', 'label' => 'Inscription'],
            ['key' => 'status', 'label' => 'État'],
            ['key' => 'actions', 'label' => 'Actions'],
        ], $query->paginate(10)->withQueryString()->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'type' => $user->account_type->value,
            'verified' => $user->email_verified_at ? 'Oui' : 'Non',
            'created' => $user->created_at?->format('d/m/Y') ?? '—',
            'status' => $user->suspended_at === null ? 'Actif' : 'Suspendu',
            'actions' => [
                'edit' => route('staff.users.edit', $user, false),
                'reset' => route('staff.users.password-reset', $user, false),
                'delete' => $user->id !== $request->user()->id ? route('staff.users.destroy', $user, false) : null,
                $user->suspended_at === null ? 'suspend' : 'reactivate' => $user->suspended_at === null
                    ? route('staff.users.suspend', $user, false)
                    : route('staff.users.reactivate', $user, false),
            ],
        ]), filters: [
            $this->enumFilter('type', 'Type', AccountType::cases(), $typeValue),
            $this->filter('status', 'État', [['value' => 'active', 'label' => 'Actif'], ['value' => 'suspended', 'label' => 'Suspendu']], is_string($status) ? $status : ''),
            $this->yesNoFilter('verified', 'E-mail vérifié', $verified),
        ]);
    }

    public function audit(Request $request): Response
    {
        $query = AuditLog::query()->latest('id');
        $this->search($query, $request, ['action', 'actor_name', 'actor_email']);

        return $this->table($request, "Historique d'audit", 'Actions sensibles enregistrées par la plateforme.', [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'actor', 'label' => 'Acteur'],
            ['key' => 'action', 'label' => 'Action'],
            ['key' => 'subject', 'label' => 'Objet'],
        ], $query->paginate(50)->withQueryString()->through(fn (AuditLog $entry) => [
            'id' => $entry->id,
            'date' => $entry->created_at->format('d/m/Y H:i'),
            'actor' => $entry->actor_name === null ? 'Système' : $entry->actor_name.' — '.$entry->actor_email,
            'action' => $entry->action,
            'subject' => $entry->auditable_type === null ? '—' : class_basename($entry->auditable_type).' #'.$entry->auditable_id,
        ]));
    }

    public function references(Request $request): Response
    {
        $rows = ReferenceValue::query()->orderBy('type')->orderBy('display_order')->get()
            ->map(fn (ReferenceValue $value) => ['group' => $value->type === 'domain' ? 'Domaines' : 'Langues', 'code' => $value->code, 'label' => $value->label_fr])
            ->concat(collect(AnnouncementCategory::cases())->map(fn (AnnouncementCategory $category) => ['group' => "Types d'annonces", 'code' => $category->value, 'label' => Str::headline($category->value)]));

        return $this->table($request, 'Référentiels', 'Valeurs de référence utilisées par les formulaires.', [
            ['key' => 'group', 'label' => 'Référentiel'],
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'label', 'label' => 'Libellé'],
        ], ['data' => $rows->values(), 'links' => []], false);
    }

    public function settings(Request $request): Response
    {
        $rows = [
            ['parameter' => 'Langue principale', 'value' => config('app.locale')],
            ['parameter' => 'Fuseau horaire', 'value' => config('app.timezone')],
            ['parameter' => 'Comptes candidats', 'value' => User::where('account_type', AccountType::Candidate)->count()],
            ['parameter' => 'Comptes employeur', 'value' => User::where('account_type', AccountType::Employer)->count()],
            ['parameter' => 'Comptes staff', 'value' => User::where('account_type', AccountType::Staff)->count()],
            ['parameter' => 'Annonces', 'value' => Announcement::whereNull('revision_of_id')->count()],
        ];

        return $this->table($request, 'Paramètres', 'Configuration générale non sensible et compteurs de la plateforme.', [
            ['key' => 'parameter', 'label' => 'Paramètre'],
            ['key' => 'value', 'label' => 'Valeur'],
        ], ['data' => $rows, 'links' => []], false);
    }

    public function info(Request $request): Response
    {
        $rows = collect(['beta_mr', 'beta_conseils', 'recrutement', 'conseils_rh', 'assistance_emploi', 'tests_psycho'])
            ->map(fn (string $slug) => ['page' => Str::headline($slug), 'address' => "/beta/{$slug}", 'status' => 'Publiée']);

        return $this->table($request, 'Infos', "Pages d'information publiées sur le portail.", [
            ['key' => 'page', 'label' => 'Page'],
            ['key' => 'address', 'label' => 'Adresse'],
            ['key' => 'status', 'label' => 'État'],
        ], ['data' => $rows, 'links' => []], false);
    }

    /**
     * @param  list<array{key: string, label: string, translation?: string}>  $columns
     * @param  array{label: string, url: string}|null  $primaryAction
     * @param  list<array{name: string, label: string, options: array<int, array{value: string, label: string}>, value: string}>  $filters
     */
    private function table(Request $request, string $title, string $description, array $columns, mixed $rows, bool $searchable = true, ?array $primaryAction = null, array $filters = []): Response
    {
        return Inertia::render('staff/Administration', compact('title', 'description', 'columns', 'rows', 'searchable', 'primaryAction', 'filters'));
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     * @return array{name: string, label: string, options: array<int, array{value: string, label: string}>, value: string}
     */
    private function filter(string $name, string $label, array $options, string $value): array
    {
        return compact('name', 'label', 'options', 'value');
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array{name: string, label: string, options: array<int, array{value: string, label: string}>, value: string}
     */
    private function enumFilter(string $name, string $label, array $cases, string $value): array
    {
        return $this->filter($name, $label, collect($cases)->map(fn (\BackedEnum $v) => ['value' => (string) $v->value, 'label' => Str::headline((string) $v->value)])->all(), $value);
    }

    /** @return array{name: string, label: string, options: array<int, array{value: string, label: string}>, value: string} */
    private function referenceFilter(string $name, string $label, string $value): array
    {
        return $this->filter($name, $label, ReferenceValue::query()->where('type', $name)->where('is_active', true)
            ->orderBy('display_order')->get()->map(fn (ReferenceValue $item) => ['value' => $item->code, 'label' => $item->label_fr])->all(), $value);
    }

    /** @return array{name: string, label: string, options: array<int, array{value: string, label: string}>, value: string} */
    private function yesNoFilter(string $name, string $label, mixed $value): array
    {
        return $this->filter($name, $label, [['value' => 'yes', 'label' => 'Oui'], ['value' => 'no', 'label' => 'Non']], is_string($value) ? $value : '');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @param  array<string, list<string>>  $relations
     */
    private function search(Builder $query, Request $request, array $columns, array $relations = []): void
    {
        $search = trim((string) $request->query('q'));
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search, $columns, $relations): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', "%{$search}%");
            }
            foreach ($relations as $relation => $fields) {
                $query->orWhereHas($relation, function (Builder $related) use ($search, $fields): void {
                    foreach ($fields as $field) {
                        $related->orWhere($field, 'like', "%{$search}%");
                    }
                });
            }
        });
    }
}

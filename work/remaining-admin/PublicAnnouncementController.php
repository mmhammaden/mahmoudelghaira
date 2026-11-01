<?php

namespace App\Http\Controllers;

use App\Actions\Announcements\AnnouncementWorkflow;
use App\Actions\Announcements\FeatureAnnouncement;
use App\Enums\AccountType;
use App\Enums\Announcement\AnnouncementCategory;
use App\Enums\Announcement\AnnouncementStatus;
use App\Enums\Announcement\Wilaya;
use App\Enums\BaseCv\Country;
use App\Enums\EmployerApprovalStatus;
use App\Http\Controllers\Settings\CompanyLogoController;
use App\Models\AdBanner;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\ReferenceValue;
use App\Models\User;
use App\Support\StaffPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public announcements (roadmap 5.7, D6). The list shows published, open
 * announcements; a published announcement keeps its page after closing,
 * with a notice. Anything else — draft, pending, rejected — is a 404 for
 * everyone, like an unknown id (legacy showed any offer by id; not
 * copied). Filtering and pagination happen here, never in the browser.
 */
class PublicAnnouncementController extends Controller
{
    public const PER_PAGE = 20;

    public const SEARCH_MAX = 100;

    public function index(Request $request): Response
    {
        $archived = $request->routeIs('announcements.archives');
        $category = AnnouncementCategory::tryFrom((string) $request->query('category'));
        $section = in_array($request->query('section'), ['calls', 'offers', 'notices'], true)
            ? (string) $request->query('section')
            : '';
        $domain = in_array((string) $request->query('domain'), ReferenceValue::activeCodes('domain'), true)
            ? (string) $request->query('domain') : null;
        // A wilaya id, or 'abroad' for every country but Mauritania.
        $where = (string) $request->query('where');
        $where = ($where === 'abroad' || (ctype_digit($where) && Wilaya::isValid((int) $where))) ? $where : '';
        $employer = ctype_digit((string) $request->query('employer'))
            ? (string) $request->query('employer')
            : '';
        $search = Str::limit(trim(is_string($request->query('q')) ? $request->query('q') : ''), self::SEARCH_MAX, '');

        $query = Announcement::query()->with('employerProfile');
        if ($archived) {
            $query->where('status', AnnouncementStatus::Published)
                ->where(fn ($closed) => $closed->whereNotNull('closed_at')->orWhere('deadline_at', '<=', now()));
        } else {
            $query->publiclyListed();
        }

        if ($section !== '') {
            $query->whereIn('category', match ($section) {
                'calls' => [AnnouncementCategory::InternationalTender, AnnouncementCategory::LocalTender, AnnouncementCategory::Consultation, AnnouncementCategory::ExpressionOfInterest],
                'offers' => [AnnouncementCategory::JobOffer, AnnouncementCategory::Consultant],
                'notices' => [AnnouncementCategory::Notice],
            });
        }

        if ($category !== null) {
            $query->where('category', $category);
        }
        if ($domain !== null) {
            $query->where('domain', $domain);
        }
        if ($where === 'abroad') {
            $query->where('country', '!=', Country::MAURITANIA);
        } elseif ($where !== '') {
            $query->where('country', Country::MAURITANIA)->where('wilaya_id', (int) $where);
        }
        if ($search !== '') {
            // Search both title languages; visitor wildcards remain literal.
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $query->where(fn ($titles) => $titles
                ->whereRaw("title like ? escape '!'", ['%'.$escaped.'%'])
                ->orWhereRaw("title_ar like ? escape '!'", ['%'.$escaped.'%']));
        }
        if ($employer !== '') {
            $query->where('employer_profile_id', (int) $employer);
        }

        $filters = array_filter([
            'category' => $category->value ?? '',
            'section' => $section,
            'domain' => $domain ?? '',
            'where' => $where,
            'q' => $search,
            'employer' => $employer,
        ], fn (string $v) => $v !== '');

        $page = $query->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            // Only the validated filters, never arbitrary input echoed back.
            ->appends($filters)
            ->through(fn (Announcement $a) => self::listItem($a));

        return Inertia::render('annonces/Index', [
            'announcements' => $page,
            'filters' => ['category' => '', 'domain' => '', 'where' => '', 'q' => '', 'section' => '', ...$filters],
            'archived' => $archived,
            'options' => [
                'category' => array_column(AnnouncementCategory::cases(), 'value'),
                'domain' => ReferenceValue::activeCodes('domain'),
                'wilaya' => array_map(fn (int $id, array $n) => ['id' => $id, 'fr' => $n[0], 'ar' => $n[1]], array_keys(Wilaya::NAMES), Wilaya::NAMES),
            ],
        ]);
    }

    public function show(Request $request, Announcement $announcement): Response
    {
        // 404, not 403: an unpublished announcement looks like an unknown one.
        abort_unless($announcement->status === AnnouncementStatus::Published, 404);

        // A database-side increment is atomic under concurrent public views.
        $announcement->increment('view_count');

        $profile = $announcement->employerProfile;
        $companyAnnouncements = $profile->announcements()->publiclyListed()->get(['category']);
        $offerCategories = [AnnouncementCategory::JobOffer, AnnouncementCategory::Consultant];
        $callCategories = [AnnouncementCategory::InternationalTender, AnnouncementCategory::LocalTender, AnnouncementCategory::Consultation, AnnouncementCategory::ExpressionOfInterest];

        return Inertia::render('annonces/Show', [
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->publicTitle(),
                'category' => $announcement->category->value,
                'description' => $announcement->publicDescription(),
                'descriptionFormat' => $announcement->description_format,
                'sourceUrl' => $announcement->source_url,
                'domain' => $announcement->domain,
                'location' => self::location($announcement),
                'deadlineAt' => $announcement->deadline_at?->format('Y-m-d\TH:i'),
                'publishedAt' => $announcement->published_at?->toIso8601String(),
                'externalUrl' => $announcement->category->allowsExternalLink() ? $announcement->external_url : null,
                'closed' => $announcement->isClosed(),
                'attachments' => $announcement->attachments()->orderBy('id')->get()->map(fn (AnnouncementAttachment $file) => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'size' => $file->size,
                    'url' => route('announcement-files.download', $file, absolute: false),
                ]),
            ],
            'company' => [
                'id' => $profile->id,
                'name' => $announcement->publicCompanyName(),
                'organisationType' => $profile->organisation_type?->value,
                // Logos are public only for approved employers (5.1).
                'logoUrl' => $profile->approval_status === EmployerApprovalStatus::Approved ? CompanyLogoController::url($profile) : null,
                'counts' => [
                    'offers' => $companyAnnouncements->whereIn('category', $offerCategories)->count(),
                    'calls' => $companyAnnouncements->whereIn('category', $callCategories)->count(),
                    'notices' => $companyAnnouncements->where('category', AnnouncementCategory::Notice)->count(),
                ],
            ],
            'canClose' => $announcement->canBeClosed() && self::mayClose($request->user(), $announcement),
            // "À la une" (H1): staff with the privilege; offered while the
            // announcement is open, or to take a closed one off.
            'featuring' => ($request->user()?->can(StaffPermissions::FEATURE_ANNOUNCEMENTS) ?? false)
                && ($announcement->canBeClosed() || $announcement->featured_at !== null)
                ? ['featured' => $announcement->featured_at !== null]
                : null,
            'application' => $this->applicationState($request->user(), $announcement),
            // The employer's own banners replace the general ones (H3b-2).
            'topAds' => AdBanner::topFor($announcement),
        ]);
    }

    public function close(Request $request, Announcement $announcement, AnnouncementWorkflow $workflow): RedirectResponse
    {
        // Same answer as for an unknown id to anyone who may not close it.
        abort_unless(self::mayClose($request->user(), $announcement), 404);

        $workflow->close($announcement, $request->user(), $request->ip());

        return to_route('announcements.show', $announcement);
    }

    public function feature(Request $request, Announcement $announcement, FeatureAnnouncement $action): RedirectResponse
    {
        $action->feature($announcement, $request->user(), $request->ip());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement featured on the home page.')]);

        return to_route('announcements.show', $announcement);
    }

    public function unfeature(Request $request, Announcement $announcement, FeatureAnnouncement $action): RedirectResponse
    {
        $action->unfeature($announcement, $request->user(), $request->ip());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement removed from the home page.')]);

        return to_route('announcements.show', $announcement);
    }

    /**
     * D6: the owning employer or staff — administrators (D4).
     */
    private static function mayClose(?User $user, Announcement $announcement): bool
    {
        return match ($user?->account_type) {
            AccountType::Employer => $user->employerProfile?->id === $announcement->employer_profile_id,
            AccountType::Staff => $user->hasRole(StaffPermissions::ADMINISTRATOR),
            default => false,
        };
    }

    /**
     * What the "Postuler" area shows (roadmap 5.3). Null when the
     * announcement takes no applications at all (calls, notices).
     *
     * @return array<string, mixed>|null
     */
    private function applicationState(?User $user, Announcement $announcement): ?array
    {
        if ($announcement->source_url !== null || ! $announcement->category->acceptsApplications()) {
            return null;
        }

        $profile = $user?->account_type === AccountType::Candidate ? $user->candidateProfile : null;
        $existing = $profile?->applications()->where('announcement_id', $announcement->id)->first();

        return [
            // 'guest', 'candidate', or 'other' (employers and staff cannot apply).
            'viewer' => $user === null ? 'guest' : ($profile !== null ? 'candidate' : 'other'),
            'open' => $announcement->acceptsApplicationsNow(),
            'cvComplete' => $profile?->hasCompleteBaseCv() ?? false,
            'applied' => $existing === null ? null : [
                'at' => $existing->created_at?->toIso8601String(),
                'status' => $existing->status->value,
            ],
        ];
    }

    /**
     * One announcement as the public list shows it (also the home page).
     * Needs the employer profile loaded.
     *
     * @return array{id: int, title: string, category: string, domain: string|null, location: string|null, deadlineAt: string|null, company: string}
     */
    public static function listItem(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->publicTitle(),
            'category' => $a->category->value,
            'domain' => $a->domain,
            'location' => self::location($a),
            'deadlineAt' => $a->deadline_at?->format('Y-m-d\TH:i'),
            'company' => $a->publicCompanyName(),
            'logoUrl' => $a->employerProfile->approval_status === EmployerApprovalStatus::Approved
                ? CompanyLogoController::url($a->employerProfile)
                : null,
        ];
    }

    private static function location(Announcement $announcement): ?string
    {
        if ($announcement->country === Country::MAURITANIA) {
            return $announcement->wilaya_id === null ? null : (Wilaya::NAMES[$announcement->wilaya_id][app()->getLocale() === 'ar' ? 1 : 0] ?? null);
        }

        // Other countries: the page names them from the code.
        return $announcement->country;
    }
}

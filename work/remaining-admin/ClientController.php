<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Audit\RecordAuditLogEntry;
use App\Actions\Employers\ReplaceCompanyLogo;
use App\Actions\Staff\ApproveEmployerAccount;
use App\Enums\AccountType;
use App\Enums\BaseCv\Country;
use App\Enums\Company\Commune;
use App\Enums\Company\OrganisationType;
use App\Enums\EmployerApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\CompanyLogoController;
use App\Http\Requests\Settings\UploadCompanyLogoRequest;
use App\Models\EmployerDocument;
use App\Models\EmployerProfile;
use App\Models\ReferenceValue;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function create(): Response
    {
        return $this->form();
    }

    public function edit(EmployerProfile $client): Response
    {
        $client->load('user:id,name,email');

        return $this->form($client);
    }

    public function store(Request $request, RecordAuditLogEntry $audit, ReplaceCompanyLogo $logoAction): RedirectResponse
    {
        $data = $this->validatedForCreation($request);
        $request->validate([
            'logo' => [
                'nullable',
                'file',
                'max:'.UploadCompanyLogoRequest::MAX_KILOBYTES,
                'mimetypes:image/png,image/jpeg,image/webp',
                'dimensions:min_width=64,min_height=64,max_width=3000,max_height=3000',
            ],
        ]);
        $data['description'] = app(HtmlSanitizer::class)->sanitize($data['description'] ?? null);

        $profile = DB::transaction(function () use ($data, $request, $audit): EmployerProfile {
            $user = User::create([
                'name' => $data['company_name'],
                'email' => Str::uuid().'@pending.beta-conseils.invalid',
                'password' => Str::password(64),
                'account_type' => AccountType::Employer,
            ]);
            $profile = $user->employerProfile()->create($data);
            $profile->forceFill(['approval_status' => EmployerApprovalStatus::Pending])->save();
            $user->forceFill(['email' => "emp_{$profile->id}@pending.beta-conseils.invalid"])->save();
            $audit->record('employer.created_by_staff', $request->user(), $profile, null, $this->safeSnapshot($profile, $user), $request->ip());

            return $profile;
        });

        $logo = $request->file('logo');
        if ($logo instanceof UploadedFile) {
            $logoAction->replace($profile, $logo);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created. Configure its account access when it is ready.')]);

        return to_route('staff.admin.clients');
    }

    public function update(Request $request, EmployerProfile $client, RecordAuditLogEntry $audit): RedirectResponse
    {
        $client->load('user');
        $data = $this->validated($request, $client);
        $data['description'] = app(HtmlSanitizer::class)->sanitize($data['description'] ?? null);
        $before = $this->safeSnapshot($client, $client->user);

        DB::transaction(function () use ($data, $request, $client, $audit, $before): void {
            $client->user->update(['name' => $data['name'], 'email' => $data['email']]);
            $client->update($this->profileData($data));
            $client->forceFill(['approval_status' => $data['approval_status']])->save();
            $audit->record('employer.updated_by_staff', $request->user(), $client, $before, $this->safeSnapshot($client->fresh(), $client->user->fresh()), $request->ip());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('staff.admin.clients');
    }

    public function storeLogo(UploadCompanyLogoRequest $request, EmployerProfile $client, ReplaceCompanyLogo $action, RecordAuditLogEntry $audit): RedirectResponse
    {
        $file = $request->file('logo');
        abort_unless($file instanceof UploadedFile, 422);
        $before = ['logo_path' => $client->logo_path];

        $action->replace($client, $file);
        $audit->record('employer.logo_replaced_by_staff', $request->user(), $client, $before, ['logo_path' => $client->fresh()->logo_path], $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client logo saved.')]);

        return back();
    }

    public function destroyLogo(Request $request, EmployerProfile $client, ReplaceCompanyLogo $action, RecordAuditLogEntry $audit): RedirectResponse
    {
        $before = ['logo_path' => $client->logo_path];
        $action->remove($client);
        $audit->record('employer.logo_removed_by_staff', $request->user(), $client, $before, ['logo_path' => null], $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client logo removed.')]);

        return back();
    }

    public function storeDocument(Request $request, EmployerProfile $client, RecordAuditLogEntry $audit): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255', Rule::unique(EmployerDocument::class)->where('employer_profile_id', $client->id)],
            'document_type' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:'.EmployerDocument::MAX_KILOBYTES, 'mimetypes:'.implode(',', array_keys(EmployerDocument::EXTENSIONS))],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $mime = (string) $file->getMimeType();
        $extension = EmployerDocument::EXTENSIONS[$mime] ?? null;
        abort_if($extension === null, 422, __('The file type is not allowed.'));
        $path = $file->storeAs((string) $client->id, Str::uuid().'.'.$extension, EmployerDocument::DISK);
        abort_unless(is_string($path), 422);

        try {
            $document = $client->documents()->create([
                'label' => $data['label'],
                'document_type' => $data['document_type'],
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk(EmployerDocument::DISK)->delete($path);
            throw $exception;
        }

        $audit->record('employer.document_added_by_staff', $request->user(), $client, null, ['document_id' => $document->id, 'label' => $document->label], $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client document added.')]);

        return back();
    }

    public function downloadDocument(EmployerProfile $client, EmployerDocument $document): StreamedResponse
    {
        abort_unless($document->employer_profile_id === $client->id, 404);
        $disk = Storage::disk(EmployerDocument::DISK);
        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function destroyDocument(Request $request, EmployerProfile $client, EmployerDocument $document, RecordAuditLogEntry $audit): RedirectResponse
    {
        abort_unless($document->employer_profile_id === $client->id, 404);
        $snapshot = ['document_id' => $document->id, 'label' => $document->label];
        $path = $document->path;
        $document->delete();
        Storage::disk(EmployerDocument::DISK)->delete($path);
        $audit->record('employer.document_removed_by_staff', $request->user(), $client, $snapshot, null, $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client document removed.')]);

        return back();
    }

    public function approve(Request $request, EmployerProfile $client, ApproveEmployerAccount $action): RedirectResponse
    {
        $action->approve($client, $request->user(), $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client approved.')]);

        return back();
    }

    public function deactivate(Request $request, EmployerProfile $client, RecordAuditLogEntry $audit): RedirectResponse
    {
        $before = $client->approval_status;
        $client->forceFill(['approval_status' => EmployerApprovalStatus::Pending])->save();
        $audit->record('employer.deactivated_by_staff', $request->user(), $client, ['approval_status' => $before->value], ['approval_status' => EmployerApprovalStatus::Pending->value], $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deactivated.')]);

        return back();
    }

    public function editAccess(EmployerProfile $client): Response
    {
        $client->load('user:id,name,email');

        return Inertia::render('staff/ClientAccessForm', [
            'client' => [
                'id' => $client->id,
                'companyName' => $client->company_name,
                'name' => $client->account_configured_at === null ? '' : $client->user->name,
                'email' => $client->account_configured_at === null ? '' : $client->user->email,
                'configured' => $client->account_configured_at !== null,
            ],
        ]);
    }

    public function updateAccess(Request $request, EmployerProfile $client, RecordAuditLogEntry $audit): RedirectResponse
    {
        $client->load('user');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($client->user_id)],
        ]);
        $before = $this->safeSnapshot($client, $client->user);

        DB::transaction(function () use ($client, $data, $request, $audit, $before): void {
            $client->user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::password(64),
            ])->save();
            $client->forceFill(['account_configured_at' => now()])->save();
            $audit->record('employer.access_configured_by_staff', $request->user(), $client, $before, $this->safeSnapshot($client, $client->user), $request->ip());
        });

        Password::sendResetLink(['email' => $data['email']]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client access configured and a password setup link was sent.')]);

        return to_route('staff.admin.clients');
    }

    public function destroy(Request $request, EmployerProfile $client, RecordAuditLogEntry $audit): RedirectResponse
    {
        $client->load(['user', 'documents:id,employer_profile_id,path']);
        abort_if($client->announcements()->exists(), 422, __('A client with announcements cannot be deleted; suspend the account instead.'));
        $before = $this->safeSnapshot($client, $client->user);
        $documentPaths = $client->documents->pluck('path')->all();
        $logoPath = $client->logo_path;

        DB::transaction(function () use ($request, $client, $audit, $before): void {
            $audit->record('employer.deleted_by_staff', $request->user(), $client, $before, null, $request->ip());
            $client->user->delete();
        });

        Storage::disk(EmployerDocument::DISK)->delete($documentPaths);
        if ($logoPath !== null) {
            Storage::disk('logos')->delete($logoPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('staff.admin.clients');
    }

    public function resetPassword(Request $request, EmployerProfile $client, RecordAuditLogEntry $audit): RedirectResponse
    {
        abort_if($client->account_configured_at === null, 422, __('Configure client access before sending a password reset link.'));
        $status = Password::sendResetLink(['email' => $client->user()->value('email')]);
        abort_unless($status === Password::RESET_LINK_SENT, 422, __($status));
        $audit->record('employer.password_reset_sent_by_staff', $request->user(), $client, null, null, $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password reset link sent.')]);

        return back();
    }

    private function form(?EmployerProfile $client = null): Response
    {
        $client?->load(['user:id,name,email', 'documents:id,employer_profile_id,label,document_type,original_name,mime_type,size,created_at']);

        return Inertia::render('staff/ClientForm', [
            'client' => $client === null ? null : array_merge([
                'id' => $client->id, 'name' => $client->user->name, 'email' => $client->user->email,
            ], $client->only(['company_name', 'company_name_ar', 'organisation_type', 'domain', 'country', 'commune_id', 'company_email', 'company_phone', 'description', 'website', 'address', 'nif', 'representative_name', 'representative_title', 'representative_phone', 'representative_email', 'approval_status'])),
            'logoUrl' => $client === null ? null : CompanyLogoController::url($client),
            'documents' => $client?->documents->map(fn (EmployerDocument $document) => [
                'id' => $document->id,
                'label' => $document->label,
                'type' => $document->document_type,
                'name' => $document->original_name,
                'size' => $document->size,
                'downloadUrl' => route('staff.clients.documents.download', [$client, $document], false),
                'deleteUrl' => route('staff.clients.documents.destroy', [$client, $document], false),
            ])->values() ?? [],
            'organisationTypes' => collect(OrganisationType::cases())->pluck('value'),
            'domains' => ReferenceValue::activeCodes('domain'),
            'countries' => Country::CODES,
            'communes' => collect(Commune::NAMES)->map(fn (array $names, int $id) => ['id' => $id, 'label' => $names[0]])->values(),
            'statuses' => collect(EmployerApprovalStatus::cases())->pluck('value'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?EmployerProfile $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($client?->user_id)],
            'company_name' => ['required', 'string', 'max:150', Rule::unique(EmployerProfile::class)->ignore($client?->id)],
            'company_name_ar' => ['nullable', 'string', 'max:150'],
            'organisation_type' => ['required', Rule::enum(OrganisationType::class)],
            'domain' => ['required', Rule::in(collect(ReferenceValue::activeCodes('domain'))->push($client?->domain)->filter()->unique()->all())],
            'country' => ['required', Rule::in(Country::CODES)],
            'commune_id' => ['exclude_unless:country,'.Country::MAURITANIA, 'required', 'integer', Rule::in(array_keys(Commune::NAMES))],
            'company_email' => ['required', 'email', 'max:255'],
            'company_phone' => ['required', 'regex:/^\+?[0-9][0-9 ().-]{5,30}$/'],
            'description' => ['nullable', 'string', 'max:5000'], 'website' => ['nullable', 'url:http,https', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'], 'nif' => ['nullable', 'string', 'max:64'],
            'representative_name' => ['nullable', 'string', 'max:80'], 'representative_title' => ['nullable', 'string', 'max:120'],
            'representative_phone' => ['nullable', 'regex:/^\+?[0-9][0-9 ().-]{5,30}$/'], 'representative_email' => ['nullable', 'email', 'max:255'],
            'approval_status' => ['required', Rule::enum(EmployerApprovalStatus::class)],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedForCreation(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:150', Rule::unique(EmployerProfile::class)],
            'company_name_ar' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'organisation_type' => ['required', Rule::enum(OrganisationType::class)],
            'domain' => ['required', Rule::in(ReferenceValue::activeCodes('domain'))],
            'company_email' => ['required', 'email', 'max:255'],
            'company_phone' => ['required', 'regex:/^\+?[0-9][0-9 ().-]{5,30}$/'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileData(array $data): array
    {
        return collect($data)->except(['name', 'email', 'approval_status'])->all();
    }

    /** @return array<string, mixed> */
    private function safeSnapshot(EmployerProfile $profile, User $user): array
    {
        return ['account_name' => $user->name, 'account_email' => $user->email, 'company_name' => $profile->company_name, 'company_email' => $profile->company_email, 'approval_status' => $profile->approval_status->value];
    }
}

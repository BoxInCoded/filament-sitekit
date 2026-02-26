<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\OAuth;

use BoxinCode\FilamentSiteKit\Contracts\TokenStore;
use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSetupWizard;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use BoxinCode\FilamentSiteKit\SiteKitLicense;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function __construct(
        protected GoogleOAuthClient $oauthClient,
        protected TokenStore $tokenStore,
        protected SiteKitAccountManager $accountManager,
        protected SiteKitLicense $license,
    ) {
    }

    public function connect(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $state = Str::random(40);
        $request->session()->put('filament_sitekit_oauth_state', $state);
        $request->session()->put('filament_sitekit_oauth_redirect', (string) $request->query('redirect', SiteKitSetupWizard::getUrl(['setup' => 1])));

        return redirect()->away($this->oauthClient->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $expectedState = (string) $request->session()->pull('filament_sitekit_oauth_state', '');
        $state = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            Notification::make()
                ->danger()
                ->title('Google connection failed')
                ->body('Google connection failed. Try again.')
                ->send();

            return redirect($this->redirectUrl($request, true));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            Notification::make()
                ->danger()
                ->title('Google connection failed')
                ->body('Google connection failed. Try again.')
                ->send();

            return redirect($this->redirectUrl($request, true));
        }

        try {
            $tokenPayload = $this->oauthClient->exchangeCodeForToken($code);
            $profile = $this->oauthClient->fetchProfile($tokenPayload['access_token']);
            $workspaceId = $this->accountManager->resolveWorkspaceId($request);
            $userId = (int) Filament::auth()->id();

            $existingCount = SiteKitAccount::query()
                ->where('user_id', $userId)
                ->where('provider', 'google')
                ->count();

            $email = (string) ($profile['email'] ?? '');
            $alreadyExists = SiteKitAccount::query()
                ->where('user_id', $userId)
                ->where('provider', 'google')
                ->where('email', $email)
                ->exists();

            if ($this->license->isFree() && $existingCount >= 1 && ! $alreadyExists) {
                Notification::make()
                    ->warning()
                    ->title('Upgrade to Pro')
                    ->body('Free plan supports only one connected account.')
                    ->send();

                return redirect($this->redirectUrl($request));
            }

            $account = SiteKitAccount::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'workspace_id' => $workspaceId,
                    'provider' => 'google',
                    'email' => $profile['email'],
                ],
                [
                    'name' => $profile['name'],
                    'display_name' => $profile['name'],
                ]
            );

            $account->users()->syncWithoutDetaching([
                $userId => ['role' => 'owner'],
            ]);

            $this->tokenStore->store(
                $account,
                $tokenPayload['access_token'],
                $tokenPayload['refresh_token'],
                $tokenPayload['expires_at'],
                $tokenPayload['scopes']
            );

            $this->accountManager->setCurrent((int) $account->id);

            Notification::make()
                ->success()
                ->title('Google connected')
                ->body('Your Google account is now connected to Site Kit.')
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title('Google connection failed')
                ->body('Google connection failed. Try again.')
                ->send();
        }

        return redirect($this->redirectUrl($request));
    }

    public function disconnect(): RedirectResponse
    {
        $this->authorizeAccess();

        $account = SiteKitAccount::query()
            ->where('provider', 'google')
            ->where('user_id', Filament::auth()->id())
            ->where('id', optional($this->accountManager->current())->id)
            ->first();

        if ($account && Gate::allows('delete', $account)) {
            $account->token()->delete();
            $account->settings()->delete();
            $account->snapshots()->delete();
            $account->users()->detach();
            $account->delete();
        }

        $this->accountManager->clearCurrent();
        $this->accountManager->current();

        Notification::make()
            ->success()
            ->title('Google disconnected')
            ->body('Site Kit has disconnected your Google account.')
            ->send();

        return redirect($this->settingsUrl());
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Filament::auth()->check(), 403);

        $ability = (string) config('filament-sitekit.authorization.gate', 'manageSiteKit');
        abort_unless(Gate::allows($ability), 403);
    }

    protected function settingsUrl(): string
    {
        return \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSettings::getUrl();
    }

    protected function redirectUrl(Request $request, bool $failed = false): string
    {
        $target = (string) $request->session()->pull('filament_sitekit_oauth_redirect', SiteKitSetupWizard::getUrl(['setup' => 1]));

        if ($target === '') {
            $target = SiteKitSetupWizard::getUrl(['setup' => 1]);
        }

        if (! $failed) {
            return $target;
        }

        return str_contains($target, '?') ? ($target . '&google=failed') : ($target . '?google=failed');
    }
}

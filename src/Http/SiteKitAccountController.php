<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Http;

use BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitDashboard;
use BoxinCode\FilamentSiteKit\Models\SiteKitAccount;
use BoxinCode\FilamentSiteKit\SiteKitAccountManager;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class SiteKitAccountController extends Controller
{
    public function switch(Request $request, SiteKitAccountManager $accountManager): RedirectResponse
    {
        abort_unless(Filament::auth()->check(), 403);
        abort_unless(Gate::allows((string) config('filament-sitekit.authorization.gate', 'manageSiteKit')), 403);

        $accountId = (int) $request->query('account_id', 0);
        $account = SiteKitAccount::query()->find($accountId);

        if ($account && Gate::allows('switch', $account) && $accountManager->setCurrent($accountId)) {
            Notification::make()
                ->success()
                ->title('Account switched')
                ->body('Active Site Kit account has been changed.')
                ->send();
        } else {
            Notification::make()
                ->warning()
                ->title('Unable to switch account')
                ->body('Selected account is not available for your user.')
                ->send();
        }

        $redirect = (string) $request->query('redirect', SiteKitDashboard::getUrl());

        return redirect()->to($redirect !== '' ? $redirect : SiteKitDashboard::getUrl());
    }
}

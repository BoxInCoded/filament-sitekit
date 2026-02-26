<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Http;

use BoxinCode\FilamentSiteKit\Support\UpgradeUi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpgradeUiController
{
    public function dismiss(Request $request, UpgradeUi $upgradeUi): RedirectResponse
    {
        $upgradeUi->dismissBannerForDays(7);

        $redirectTo = (string) $request->input('redirect_to', '');

        if ($redirectTo !== '' && str_starts_with($redirectTo, url('/'))) {
            return redirect()->to($redirectTo);
        }

        return redirect()->back();
    }
}

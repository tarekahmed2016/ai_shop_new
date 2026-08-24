<?php

namespace App\Services;

use App\Enums\Marketers\Status as MarketerStatus;
use App\Models\Marketer;
use App\Models\MarketerReferral;
use App\Models\User;
use App\Support\ReferralCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class ReferralAttributionService
{
    public const SESSION_CODE_KEY = 'referral.code';

    public const SESSION_LANDING_KEY = 'referral.landing_path';

    public function captureFromRequest(Request $request): void
    {
        if ($request->user() !== null) {
            return;
        }

        $this->hydrateSessionFromCookie($request);

        $parameter = (string) config('referrals.query_parameter', 'ref');
        if (! $request->query->has($parameter)) {
            return;
        }

        if ($this->capturedCode($request) !== null) {
            return;
        }

        $code = ReferralCode::normalize((string) $request->query($parameter));
        if ($code === null) {
            return;
        }

        $marketer = Marketer::query()->where('referral_code', $code)->first();
        if ($marketer === null || ! $marketer->isActive()) {
            return;
        }

        $request->session()->put(self::SESSION_CODE_KEY, $code);
        $request->session()->put(self::SESSION_LANDING_KEY, $this->pathOnly($request));
        $this->queueCaptureCookie($code);
    }

    public function capturedCode(?Request $request = null): ?string
    {
        $request ??= request();

        $fromSession = ReferralCode::normalize(
            is_string($request->session()->get(self::SESSION_CODE_KEY))
                ? $request->session()->get(self::SESSION_CODE_KEY)
                : null
        );
        if ($fromSession !== null) {
            return $fromSession;
        }

        return ReferralCode::normalize($this->cookieValue($request));
    }

    public function capturedLandingPath(?Request $request = null): ?string
    {
        $request ??= request();
        $path = $request->session()->get(self::SESSION_LANDING_KEY);

        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#')) {
            return null;
        }

        return $path;
    }

    public function attributeNewUser(User $user, ?Request $request = null): bool
    {
        $request ??= request();
        $code = $this->capturedCode($request);
        if ($code === null) {
            return false;
        }

        $marketer = Marketer::query()->where('referral_code', $code)->first();
        if ($marketer === null || $marketer->status !== MarketerStatus::Active) {
            return false;
        }

        if ((int) $marketer->user_id === (int) $user->id) {
            return false;
        }

        if (MarketerReferral::query()->where('referred_user_id', $user->id)->exists()) {
            return false;
        }

        try {
            MarketerReferral::query()->create([
                'marketer_id' => $marketer->id,
                'referred_user_id' => $user->id,
                'referral_code' => $code,
                'landing_path' => $this->capturedLandingPath($request),
                'registered_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return MarketerReferral::query()->where('referred_user_id', $user->id)->exists();
        }

        return true;
    }

    public function forgetCapturedAttribution(Request $request): void
    {
        $request->session()->forget([self::SESSION_CODE_KEY, self::SESSION_LANDING_KEY]);
        Cookie::queue(Cookie::forget(
            (string) config('referrals.cookie_name', 'ref_code'),
            '/',
            config('session.domain')
        ));
    }

    private function hydrateSessionFromCookie(Request $request): void
    {
        if ($this->sessionCode($request) !== null) {
            return;
        }

        $code = ReferralCode::normalize($this->cookieValue($request));
        if ($code === null) {
            return;
        }

        $request->session()->put(self::SESSION_CODE_KEY, $code);
    }

    private function sessionCode(Request $request): ?string
    {
        return ReferralCode::normalize(
            is_string($request->session()->get(self::SESSION_CODE_KEY))
                ? $request->session()->get(self::SESSION_CODE_KEY)
                : null
        );
    }

    private function cookieValue(Request $request): ?string
    {
        $value = $request->cookie((string) config('referrals.cookie_name', 'ref_code'));

        return is_string($value) ? $value : null;
    }

    private function queueCaptureCookie(string $code): void
    {
        $minutes = max(1, (int) config('referrals.cookie_days', 30)) * 24 * 60;
        $sameSite = (string) config('session.same_site', 'lax');

        Cookie::queue(new SymfonyCookie(
            name: (string) config('referrals.cookie_name', 'ref_code'),
            value: $code,
            expire: time() + ($minutes * 60),
            path: '/',
            domain: config('session.domain'),
            secure: (bool) config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: $sameSite === '' ? SymfonyCookie::SAMESITE_LAX : $sameSite,
        ));
    }

    private function pathOnly(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');

        return $path === '//' ? '/' : $path;
    }
}

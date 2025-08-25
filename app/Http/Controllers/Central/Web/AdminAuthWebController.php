<?php

namespace App\Http\Controllers\Central\Web;

use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Controllers\WebController;
use App\Http\Requests\Central\AdminLoginRequest;
use App\Services\Central\AdminAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuthWebController extends WebController
{
    use HandlesExceptions;

    public function __construct(
        private readonly AdminAuthenticationService $authService
    ) {}

    /**
     * Show admin login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if ($this->authService->isSessionValid()) {
            return $this->redirectTo('admin.dashboard');
        }

        return $this->view('admin.auth.login');
    }

    /**
     * Handle admin login request (Web only).
     */
    public function login(AdminLoginRequest $request): RedirectResponse
    {
        return $this->executeForWeb(function () use ($request) {
            $result = $this->authService->authenticate($request->validated());

            // Check if two-factor is required
            if (isset($result['two_factor_required']) && $result['two_factor_required']) {
                return $this->redirectTo('admin.auth.two-factor')
                    ->with('message', 'Please verify your two-factor authentication code.');
            }

            return $this->redirectWithSuccess(
                'admin.dashboard',
                'Welcome back, '.$result['user']->name.'!'
            );
        }, $request, 'authenticating admin user');
    }

    /**
     * Handle admin logout request (Web only).
     */
    public function logout(Request $request): RedirectResponse
    {
        return $this->executeForWeb(function () use ($request) {
            $this->authService->logout($request);

            return $this->redirectWithSuccess(
                'admin.auth.login',
                'You have been logged out successfully.'
            );
        }, $request, 'logging out admin user');
    }

    /**
     * Show two-factor authentication form.
     */
    public function showTwoFactor(): View|RedirectResponse
    {
        if (! $this->authService->requiresTwoFactor()) {
            return $this->redirectTo('admin.auth.login');
        }

        return $this->view('admin.auth.two-factor');
    }

    /**
     * Handle two-factor authentication verification.
     */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        return $this->executeForWeb(function () use ($request) {
            $request->validate([
                'code' => ['required', 'string', 'size:6'],
                'remember' => ['boolean'],
            ]);

            $result = $this->authService->verifyTwoFactor(
                $request->input('code'),
                $request->boolean('remember', false)
            );

            return $this->redirectWithSuccess(
                'admin.dashboard',
                'Two-factor authentication verified successfully.'
            );
        }, $request, 'verifying two-factor authentication');
    }

    /**
     * Show password reset request form.
     */
    public function showForgotPassword(): View
    {
        return $this->view('admin.auth.forgot-password');
    }

    /**
     * Handle password reset request.
     */
    public function forgotPassword(Request $request): RedirectResponse
    {
        return $this->executeForWeb(function () use ($request) {
            $request->validate([
                'email' => ['required', 'email'],
            ]);

            $result = $this->authService->sendPasswordResetLink($request->input('email'));

            if ($result) {
                return $this->redirectBackWithSuccess(
                    'Password reset link sent to your email address.'
                );
            }

            return $this->redirectBackWithError(
                'Email address not found in our records.'
            );
        }, $request, 'processing password reset request');
    }

    /**
     * Show password reset form.
     */
    public function showResetPassword(Request $request): View
    {
        return $this->view('admin.auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->input('email'),
        ]);
    }

    /**
     * Handle password reset.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        return $this->executeForWeb(function () use ($request) {
            $request->validate([
                'token' => ['required'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $result = $this->authService->resetPassword($request->only([
                'email', 'password', 'password_confirmation', 'token',
            ]));

            if ($result) {
                return $this->redirectWithSuccess(
                    'admin.auth.login',
                    'Password reset successfully. Please login with your new password.'
                );
            }

            return $this->redirectBackWithError(
                'Failed to reset password. The reset token may be invalid or expired.'
            );
        }, $request, 'resetting password');
    }
}

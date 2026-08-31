<?php

namespace App\Services;

use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected const SESSION_TTL_DAYS = 30;
    protected const VERIFY_TOKEN_TTL_HOURS = 24;
    protected const RESET_TOKEN_TTL_HOURS = 1;

    public function __construct(
        protected EmailService $emailService,
    ) {}

    /**
     * Register a new user.
     */
    public function signup(string $name, string $email, string $phone, string $password): User
    {
        $existing = User::where('email', $email)->first();

        if ($existing) {
            throw new \RuntimeException('Email sudah terdaftar');
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'passwordHash' => Hash::make($password),
            'emailVerified' => false,
        ]);

        return $user;
    }

    /**
     * Authenticate user, create session, return user + token.
     *
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->passwordHash)) {
            throw new \RuntimeException('Email atau password salah');
        }

        $token = $this->generateToken();
        $tokenHash = hash('sha256', $token);

        Session::create([
            'userId' => (string) $user->_id,
            'tokenHash' => $tokenHash,
            'expiresAt' => now()->addDays(self::SESSION_TTL_DAYS),
        ]);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Delete a session by token (logout).
     */
    public function logout(string $token): void
    {
        $tokenHash = hash('sha256', $token);

        Session::where('tokenHash', $tokenHash)->delete();
    }

    /**
     * Resolve a session token to its user.
     */
    public function getUserByToken(string $token): ?User
    {
        $tokenHash = hash('sha256', $token);

        $session = Session::where('tokenHash', $tokenHash)
            ->where('expiresAt', '>', now())
            ->first();

        if (!$session) {
            return null;
        }

        return User::find($session->userId);
    }

    /**
     * Create an email verification token and send the email.
     */
    public function createVerificationToken(string $userId): string
    {
        $user = User::find($userId);

        if (!$user) {
            throw new \RuntimeException('User tidak ditemukan');
        }

        $token = $this->generateToken();
        $tokenHash = hash('sha256', $token);

        $user->update([
            'emailVerifyToken' => $tokenHash,
            'emailVerifyExpiresAt' => now()->addHours(self::VERIFY_TOKEN_TTL_HOURS),
        ]);

        $verifyUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'))
            . "/auth/verify?token={$token}";

        $this->emailService->sendVerificationEmail([
            'name' => $user->name,
            'email' => $user->email,
            'verifyUrl' => $verifyUrl,
        ]);

        return $token;
    }

    /**
     * Verify a user's email using the token.
     */
    public function verifyEmailToken(string $token): bool
    {
        $tokenHash = hash('sha256', $token);

        $user = User::where('emailVerifyToken', $tokenHash)
            ->where('emailVerifyExpiresAt', '>', now())
            ->first();

        if (!$user) {
            return false;
        }

        $user->update([
            'emailVerified' => true,
            'emailVerifyToken' => null,
            'emailVerifyExpiresAt' => null,
        ]);

        return true;
    }

    /**
     * Create a password reset token and send the email.
     *
     * @return string|null The raw token (sent via email), or null if user not found
     */
    public function createPasswordResetToken(string $email): ?string
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return null;
        }

        $token = $this->generateToken();
        $tokenHash = hash('sha256', $token);

        $user->update([
            'passwordResetToken' => $tokenHash,
            'passwordResetExpiresAt' => now()->addHours(self::RESET_TOKEN_TTL_HOURS),
        ]);

        $resetUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'))
            . "/auth/reset-password?token={$token}";

        $this->emailService->sendPasswordResetEmail([
            'email' => $user->email,
            'resetUrl' => $resetUrl,
        ]);

        return $token;
    }

    /**
     * Reset user password using the token.
     */
    public function resetPassword(string $token, string $password): bool
    {
        $tokenHash = hash('sha256', $token);

        $user = User::where('passwordResetToken', $tokenHash)
            ->where('passwordResetExpiresAt', '>', now())
            ->first();

        if (!$user) {
            return false;
        }

        $user->update([
            'passwordHash' => Hash::make($password),
            'passwordResetToken' => null,
            'passwordResetExpiresAt' => null,
        ]);

        // Invalidate all existing sessions for this user
        Session::where('userId', (string) $user->_id)->delete();

        return true;
    }

    /**
     * Generate a cryptographically secure random token.
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}

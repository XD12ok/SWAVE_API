<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailService
{
    protected string $from;
    protected string $apiKey;

    public function __construct()
    {
        $this->from = config('services.resend.from', env('EMAIL_FROM', 'onboarding@resend.dev'));
        $this->apiKey = config('services.resend.key', env('RESEND_API_KEY', ''));
    }

    /**
     * Send an email via Resend API.
     */
    protected function send(string $to, string $subject, string $html): void
    {
        try {
            Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://api.resend.com/emails', [
                'from' => $this->from,
                'to' => [$to],
                'subject' => $subject,
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[Email] Failed to send email to {$to}: {$e->getMessage()}");
        }
    }

    /**
     * Send invoice email to buyer.
     *
     * @param  array{invoiceNumber: string, buyerName: string, buyerEmail: string, items: array<int, array{name: string, price: float, qty: int, subtotal: float}>, subtotal: float, shippingCost: float, total: float, shippingMethod: string}  $data
     */
    public function sendInvoiceEmail(array $data): void
    {
        $html = $this->buildInvoiceHtml($data);

        $this->send(
            $data['buyerEmail'],
            "Invoice #{$data['invoiceNumber']} — SWAVE",
            $html,
        );

        Log::info("[Email] Invoice sent to {$data['buyerEmail']}");
    }

    /**
     * Send email verification link.
     *
     * @param  array{name: string, email: string, verifyUrl: string}  $data
     */
    public function sendVerificationEmail(array $data): void
    {
        $html = $this->buildVerificationHtml($data);

        $this->send(
            $data['email'],
            'Verifikasi Email — SWAVE',
            $html,
        );

        Log::info("[Email] Verification sent to {$data['email']}");
    }

    /**
     * Send password reset link.
     *
     * @param  array{email: string, resetUrl: string}  $data
     */
    public function sendPasswordResetEmail(array $data): void
    {
        $html = $this->buildPasswordResetHtml($data);

        $this->send(
            $data['email'],
            'Reset Password — SWAVE',
            $html,
        );

        Log::info("[Email] Password reset sent to {$data['email']}");
    }

    // ─── HTML Template Builders ──────────────────────────────────────

    protected function formatRupiah(float $amount): string
    {
        return 'Rp' . number_format($amount, 0, ',', '.');
    }

    protected function buildInvoiceHtml(array $data): string
    {
        $rows = '';
        foreach ($data['items'] as $item) {
            $rows .= <<<HTML
            <tr>
              <td style="padding:8px 0;border-bottom:1px solid #2a2a2a;color:#e0e0e0">{$item['name']} &times; {$item['qty']}</td>
              <td style="padding:8px 0;border-bottom:1px solid #2a2a2a;color:#e0e0e0;text-align:right">{$this->formatRupiah($item['subtotal'])}</td>
            </tr>
HTML;
        }

        $shippingRow = '';
        if ($data['shippingMethod'] === 'DELIVERY') {
            $cost = $this->formatRupiah($data['shippingCost']);
            $shippingRow = <<<HTML
            <tr>
              <td style="padding:4px 0;font-size:13px;color:#888">Shipping</td>
              <td style="padding:4px 0;font-size:13px;color:#e0e0e0;text-align:right">{$cost}</td>
            </tr>
HTML;
        }

        $subtotal = $this->formatRupiah($data['subtotal']);
        $total = $this->formatRupiah($data['total']);
        $year = date('Y');
        $invoiceNumber = e($data['invoiceNumber']);
        $buyerName = e($data['buyerName']);

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#0b0b0b;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0b;padding:40px 16px">
    <tr>
      <td align="center">
        <table width="480" cellpadding="0" cellspacing="0" style="background:#151515;border-radius:16px;border:1px solid #2a2a2a;overflow:hidden">
          <tr>
            <td style="padding:32px 32px 24px;text-align:center;border-bottom:1px solid #2a2a2a">
              <h1 style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:1px">SWAVE</h1>
              <p style="margin:8px 0 0;font-size:13px;color:#888">Invoice #{$invoiceNumber}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 32px">
              <p style="margin:0 0 4px;font-size:13px;color:#888">Customer</p>
              <p style="margin:0 0 20px;font-size:15px;color:#e0e0e0">{$buyerName}</p>

              <table width="100%" cellpadding="0" cellspacing="0">
                <thead>
                  <tr>
                    <th style="text-align:left;padding:0 0 8px;font-size:11px;color:#666;text-transform:uppercase;letter-spacing:1px">Item</th>
                    <th style="text-align:right;padding:0 0 8px;font-size:11px;color:#666;text-transform:uppercase;letter-spacing:1px">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {$rows}
                </tbody>
              </table>

              <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px">
                <tr>
                  <td style="padding:4px 0;font-size:13px;color:#888">Subtotal</td>
                  <td style="padding:4px 0;font-size:13px;color:#e0e0e0;text-align:right">{$subtotal}</td>
                </tr>
                {$shippingRow}
                <tr>
                  <td style="padding:8px 0 0;font-size:15px;font-weight:700;color:#fff;border-top:1px solid #2a2a2a">Total</td>
                  <td style="padding:8px 0 0;font-size:15px;font-weight:700;color:#fff;text-align:right;border-top:1px solid #2a2a2a">{$total}</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 24px;text-align:center">
              <p style="margin:0;font-size:11px;color:#555">Please screenshot this invoice for your records.</p>
              <p style="margin:8px 0 0;font-size:11px;color:#555">SWAVE — {$year}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * @param  array{name: string, email: string, verifyUrl: string}  $data
     */
    protected function buildVerificationHtml(array $data): string
    {
        $name = e($data['name']);
        $verifyUrl = e($data['verifyUrl']);
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#0b0b0b;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0b;padding:40px 16px">
    <tr>
      <td align="center">
        <table width="480" cellpadding="0" cellspacing="0" style="background:#151515;border-radius:16px;border:1px solid #2a2a2a;overflow:hidden">
          <tr>
            <td style="padding:32px 32px 24px;text-align:center;border-bottom:1px solid #2a2a2a">
              <h1 style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:1px">SWAVE</h1>
              <p style="margin:8px 0 0;font-size:13px;color:#888">Verifikasi Email</p>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 32px">
              <p style="margin:0 0 4px;font-size:13px;color:#888">Halo, {$name}!</p>
              <p style="margin:0 0 20px;font-size:15px;color:#e0e0e0">Terima kasih sudah mendaftar di SWAVE. Klik tombol di bawah untuk memverifikasi email kamu dan mulai membuat gelang charm impianmu.</p>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding:8px 0 24px">
                    <a href="{$verifyUrl}" style="display:inline-block;background:#ffffff;color:#000000;text-decoration:none;font-size:15px;font-weight:600;padding:14px 40px;border-radius:999px">Verifikasi Email</a>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 4px;font-size:12px;color:#666">Atau salin link ini ke browser kamu:</p>
              <p style="margin:0;font-size:12px;color:#999;word-break:break-all">{$verifyUrl}</p>
              <p style="margin:16px 0 0;font-size:11px;color:#555">Link berlaku selama 24 jam. Jika kamu tidak mendaftar di SWAVE, abaikan email ini.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 24px;text-align:center">
              <p style="margin:0;font-size:11px;color:#555">SWAVE — {$year}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * @param  array{email: string, resetUrl: string}  $data
     */
    protected function buildPasswordResetHtml(array $data): string
    {
        $resetUrl = e($data['resetUrl']);
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#0b0b0b;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0b;padding:40px 16px">
    <tr>
      <td align="center">
        <table width="480" cellpadding="0" cellspacing="0" style="background:#151515;border-radius:16px;border:1px solid #2a2a2a;overflow:hidden">
          <tr>
            <td style="padding:32px 32px 24px;text-align:center;border-bottom:1px solid #2a2a2a">
              <h1 style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:1px">SWAVE</h1>
              <p style="margin:8px 0 0;font-size:13px;color:#888">Reset Password</p>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 32px">
              <p style="margin:0 0 4px;font-size:13px;color:#888">Halo!</p>
              <p style="margin:0 0 20px;font-size:15px;color:#e0e0e0">Kami menerima permintaan untuk reset password akun SWAVE kamu. Klik tombol di bawah untuk membuat password baru.</p>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center" style="padding:8px 0 24px">
                    <a href="{$resetUrl}" style="display:inline-block;background:#ffffff;color:#000000;text-decoration:none;font-size:15px;font-weight:600;padding:14px 40px;border-radius:999px">Reset Password</a>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 4px;font-size:12px;color:#666">Atau salin link ini ke browser kamu:</p>
              <p style="margin:0;font-size:12px;color:#999;word-break:break-all">{$resetUrl}</p>
              <p style="margin:16px 0 0;font-size:11px;color:#555">Link berlaku selama 1 jam. Jika kamu tidak meminta reset password, abaikan email ini.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 24px;text-align:center">
              <p style="margin:0;font-size:11px;color:#555">SWAVE — {$year}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }
}

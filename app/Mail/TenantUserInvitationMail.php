<?php

namespace App\Mail;

use App\Models\TenantUserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantUserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantUserInvitation $invitation,
        public string $token
    ) {}

    public function build(): self
    {
        $appName = (string) config('app.name', 'Grade');

        return $this
            ->subject("Tenant access invite - {$appName}")
            ->view('emails.tenant-user-invitation', [
                'invitation' => $this->invitation,
                'token' => $this->token,
                'url' => route('tenant.invite.accept', ['token' => $this->token]),
            ]);
    }
}
